<?php

namespace Alamia360\Tests\Unit\Situations;

use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Situations\SituationStatus;
use Alamia360\Tests\TestCase;

class SituationTest extends TestCase
{
    private function makeSituation(
        string $type = 'test.situation',
        SituationPriority $priority = SituationPriority::Normal,
    ): Situation {
        return new Situation(
            type: $type,
            summary: 'Test situation summary',
            priority: $priority,
            subjectType: 'entity',
            subjectId: 42,
            context: ['key' => 'value'],
        );
    }

    public function test_situation_starts_with_open_status(): void
    {
        $situation = $this->makeSituation();
        $this->assertEquals(SituationStatus::Open, $situation->status());
    }

    public function test_transition_to_changes_status(): void
    {
        $situation = $this->makeSituation();
        $situation->transitionTo(SituationStatus::Acknowledged);

        $this->assertEquals(SituationStatus::Acknowledged, $situation->status());
    }

    public function test_resolve_sets_status_to_resolved(): void
    {
        $situation = $this->makeSituation();
        $situation->resolve(['action' => 'reviewed', 'by' => 'user_1']);

        $this->assertEquals(SituationStatus::Resolved, $situation->status());
        $this->assertEquals(['action' => 'reviewed', 'by' => 'user_1'], $situation->resolution());
    }

    public function test_recommend_stores_recommended_action(): void
    {
        $situation = $this->makeSituation();
        $situation->recommend('get_pending_lab_results');

        $this->assertEquals('get_pending_lab_results', $situation->recommendedAction());
    }

    public function test_due_and_due_at_round_trip(): void
    {
        $situation = $this->makeSituation();
        $dueAt = new \DateTimeImmutable('+2 hours');
        $situation->due($dueAt);

        $this->assertEquals($dueAt, $situation->dueAt());
    }

    public function test_detected_at_is_set_at_construction(): void
    {
        $before = new \DateTimeImmutable();
        $situation = $this->makeSituation();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $situation->detectedAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $situation->detectedAt()->getTimestamp());
    }

    public function test_assign_to_stores_responsible_actors(): void
    {
        $situation = $this->makeSituation();
        $actor = \Alamia360\Actors\Actor::human('vet_1', 'veterinarian');
        $situation->assignTo([$actor]);

        $this->assertCount(1, $situation->responsibleActors());
        $this->assertSame($actor, $situation->responsibleActors()[0]);
    }

    public function test_priority_values(): void
    {
        $this->assertEquals('low', SituationPriority::Low->value);
        $this->assertEquals('normal', SituationPriority::Normal->value);
        $this->assertEquals('high', SituationPriority::High->value);
        $this->assertEquals('critical', SituationPriority::Critical->value);
    }

    public function test_status_values(): void
    {
        $this->assertEquals('open', SituationStatus::Open->value);
        $this->assertEquals('acknowledged', SituationStatus::Acknowledged->value);
        $this->assertEquals('in_progress', SituationStatus::InProgress->value);
        $this->assertEquals('escalated', SituationStatus::Escalated->value);
        $this->assertEquals('resolved', SituationStatus::Resolved->value);
        $this->assertEquals('dismissed', SituationStatus::Dismissed->value);
    }
}
