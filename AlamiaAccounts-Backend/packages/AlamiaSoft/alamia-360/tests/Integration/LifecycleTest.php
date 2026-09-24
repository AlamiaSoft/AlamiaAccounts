<?php

namespace Alamia360\Tests\Integration;

use Alamia360\Actors\Actor;
use Alamia360\Facades\Alamia360;
use Alamia360\Observation\Observation;
use Alamia360\Observation\ObservationSource;
use Alamia360\Situations\AbstractSituationDetector;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Situations\SituationStatus;
use Alamia360\Tests\TestCase;

/**
 * Acceptance gate (per current-plan.md §Sprint 7 / §S14).
 *
 * Demonstrates the full deterministic Alamia 360 lifecycle:
 *
 *   Observation (DomainEvent)
 *       ↓ Observer + SituationDetector
 *   Situation (Open, priority=High)
 *       ↓ DefaultResponsibilityResolver
 *   Responsible Actor identified
 *       ↓ Orchestrator.process()
 *   Capability executed (via CapabilityExecutor)
 *       ↓ ActionOutcome (success=true)
 *   Situation status = Resolved
 *       ↓ Audit hook called
 *   AuditEvent recorded (in-memory)
 *
 * CRITICAL: This test must pass with ZERO AI calls.
 * NullAiProvider is the default — if anything calls reason() or classify(),
 * it throws and the test fails.
 */
class LifecycleTest extends TestCase
{
    public function test_full_deterministic_lifecycle_with_zero_ai_calls(): void
    {
        // ─────────────────────────────────────────────────────────────
        // 1. SEMANTIC MODEL: declare what the application means
        // ─────────────────────────────────────────────────────────────
        Alamia360::entity('lab_result')
            ->describe('A diagnostic laboratory result')
            ->attribute('abnormal', ['type' => 'boolean', 'label' => 'Is Abnormal'])
            ->relatesTo('patient', 'patient', 'belongsTo');

        // ─────────────────────────────────────────────────────────────
        // 2. CAPABILITY: business action (delegate to app service)
        // ─────────────────────────────────────────────────────────────
        Alamia360::capability('review_lab_result')
            ->describe('Mark a lab result as reviewed by the responsible veterinarian')
            ->input(['result_id' => ['type' => 'integer', 'required' => true]])
            ->withSideEffect('write')
            ->allowedFor(['human'])
            ->handleUsing(fn (array $input, $actor) => [
                'reviewed'  => true,
                'result_id' => $input['result_id'] ?? null,
                'by'        => $actor->id(),
            ]);

        // ─────────────────────────────────────────────────────────────
        // 3. RESPONSIBILITY: who should act on this type of situation
        // ─────────────────────────────────────────────────────────────
        Alamia360::responsibility('lab_result_review_required')
            ->role('veterinarian')
            ->dueWithin(120);

        // ─────────────────────────────────────────────────────────────
        // 4. ACTOR: register the responsible actor
        // ─────────────────────────────────────────────────────────────
        $vet = Actor::human('vet_dr_ahmed', 'veterinarian')
            ->withCapabilities(['review_lab_result'])
            ->authorizeUsing(fn ($actor, $cap, $subject) => true); // open for test

        Alamia360::actors()->register($vet);

        // ─────────────────────────────────────────────────────────────
        // 5. SITUATION DETECTOR: deterministic rule — no AI
        // ─────────────────────────────────────────────────────────────
        $detector = new class extends AbstractSituationDetector {
            protected function handles(): array
            {
                return ['lab_result.created'];
            }

            protected function evaluate(Observation $observation): ?Situation
            {
                if ($observation->payload['abnormal'] ?? false) {
                    return new Situation(
                        type: 'lab_result_review_required',
                        summary: 'Abnormal lab result requires veterinarian review',
                        priority: SituationPriority::High,
                        subjectType: 'lab_result',
                        subjectId: $observation->entityId,
                        context: ['result_id' => $observation->entityId],
                        source: $observation,
                    );
                }
                return null;
            }
        };

        Alamia360::observer()->addDetector($detector);

        // ─────────────────────────────────────────────────────────────
        // 6. OBSERVE: a domain event arrives (e.g. from a Laravel listener)
        // ─────────────────────────────────────────────────────────────
        Alamia360::observer()->observe(new Observation(
            type: 'lab_result.created',
            source: ObservationSource::DomainEvent,
            entityType: 'lab_result',
            entityId: 99,
            payload: ['abnormal' => true, 'value' => 'HIGH'],
        ));

        // ─────────────────────────────────────────────────────────────
        // 7. VERIFY: situation was detected and recorded
        // ─────────────────────────────────────────────────────────────
        $openSituations = Alamia360::situations()->open();
        $this->assertCount(1, $openSituations, 'Exactly one open situation should have been detected');

        $situation = $openSituations[0];
        $this->assertEquals('lab_result_review_required', $situation->type);
        $this->assertEquals(SituationPriority::High, $situation->priority);
        $this->assertEquals('lab_result', $situation->subjectType);
        $this->assertEquals(99, $situation->subjectId);
        $this->assertEquals(SituationStatus::Open, $situation->status());

        // ─────────────────────────────────────────────────────────────
        // 8. PLAN: recommend an action (deterministic — no AI)
        // ─────────────────────────────────────────────────────────────
        $situation->recommend('review_lab_result');

        // ─────────────────────────────────────────────────────────────
        // 9. TRACK AUDIT: capture the audit event in-memory
        // ─────────────────────────────────────────────────────────────
        $auditCalled = false;
        $capturedAuditEvent = null;

        Alamia360::capabilities()->auditUsing(function (array $event) use (&$auditCalled, &$capturedAuditEvent) {
            $auditCalled = true;
            $capturedAuditEvent = $event;
        });

        // ─────────────────────────────────────────────────────────────
        // 10. ORCHESTRATE: Observe→Interpret→Assess→Plan→Authorize→Act→Verify
        //     The orchestrator runs entirely without AI.
        // ─────────────────────────────────────────────────────────────
        $action = Alamia360::orchestrator()->process($situation, $vet);

        // ─────────────────────────────────────────────────────────────
        // 11. VERIFY: action completed successfully
        // ─────────────────────────────────────────────────────────────
        $this->assertNotNull($action, 'Orchestrator should have produced an action');
        $this->assertNotNull($action->outcome(), 'Action should have an outcome');
        $this->assertTrue($action->outcome()->success, 'Action should have succeeded');
        $this->assertEquals('review_lab_result', $action->capabilityName);
        $this->assertSame($vet, $action->performedBy);

        // ─────────────────────────────────────────────────────────────
        // 12. VERIFY: situation is now resolved
        // ─────────────────────────────────────────────────────────────
        $this->assertEquals(SituationStatus::Resolved, $situation->status());
        $this->assertNotNull($situation->resolution());
        $this->assertEquals(0, count(Alamia360::situations()->open()), 'No open situations should remain');

        // ─────────────────────────────────────────────────────────────
        // 13. VERIFY: audit event was recorded
        // ─────────────────────────────────────────────────────────────
        $this->assertTrue($auditCalled, 'Audit hook should have been called');
        $this->assertEquals('review_lab_result', $capturedAuditEvent['capability']);
        $this->assertEquals('vet_dr_ahmed', $capturedAuditEvent['actor_id']);
        $this->assertEquals('human', $capturedAuditEvent['actor_type']);
        $this->assertNull($capturedAuditEvent['error']);

        // ─────────────────────────────────────────────────────────────
        // 14. VERIFY: AI was never invoked (NullAiProvider)
        //     AiHarness::isAvailable() returns false — that is the proof.
        // ─────────────────────────────────────────────────────────────
        $aiHarness = $this->app->make(\Alamia360\AI\AiHarness::class);
        $this->assertFalse($aiHarness->isAvailable(), 'NullAiProvider should be active — no AI calls made');
    }

    public function test_duplicate_observation_does_not_create_duplicate_situation(): void
    {
        $detector = new class extends AbstractSituationDetector {
            protected function handles(): array { return ['invoice.overdue']; }
            protected function evaluate(Observation $obs): ?Situation {
                return new Situation('invoice_overdue', 'Invoice overdue', SituationPriority::High, 'invoice', $obs->entityId);
            }
        };

        Alamia360::observer()->addDetector($detector);

        $obs = new Observation('invoice.overdue', ObservationSource::StateScan, 'invoice', 77, []);

        Alamia360::observer()->observe($obs);
        Alamia360::observer()->observe($obs); // duplicate — same type+subject
        Alamia360::observer()->observe($obs); // duplicate again

        $this->assertCount(1, Alamia360::situations()->all(), 'Idempotency: only one situation should be recorded');
    }

    public function test_normal_observation_does_not_produce_situation(): void
    {
        $detector = new class extends AbstractSituationDetector {
            protected function handles(): array { return ['lab_result.created']; }
            protected function evaluate(Observation $obs): ?Situation {
                // Only flag abnormal results
                return ($obs->payload['abnormal'] ?? false)
                    ? new Situation('lab_result_review_required', 'Review needed', SituationPriority::High, 'lab_result', $obs->entityId)
                    : null;
            }
        };

        Alamia360::observer()->addDetector($detector);

        Alamia360::observer()->observe(new Observation(
            type: 'lab_result.created',
            source: ObservationSource::DomainEvent,
            entityType: 'lab_result',
            entityId: 50,
            payload: ['abnormal' => false], // normal result
        ));

        $this->assertCount(0, Alamia360::situations()->all(), 'Normal observation should not produce a situation');
    }
}
