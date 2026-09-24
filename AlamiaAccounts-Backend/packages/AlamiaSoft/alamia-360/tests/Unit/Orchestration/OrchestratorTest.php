<?php

namespace Alamia360\Tests\Unit\Orchestration;

use Alamia360\Actions\Action;
use Alamia360\Actors\Actor;
use Alamia360\Actors\ActorRegistry;
use Alamia360\Capabilities\CapabilityAuthorizationException;
use Alamia360\Capabilities\CapabilityExecutor;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia360\Facades\Alamia360;
use Alamia360\Orchestration\Orchestrator;
use Alamia360\Responsibilities\ResponsibilityRegistry;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Situations\SituationStatus;
use Alamia360\Tests\TestCase;

class OrchestratorTest extends TestCase
{
    private Orchestrator $orchestrator;
    private CapabilityRegistry $capRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = $this->app->make(Orchestrator::class);
        $this->capRegistry  = $this->app->make(CapabilityRegistry::class);
    }

    private function makeSituation(string $type = 'test_situation', ?string $recommended = null): Situation
    {
        $s = new Situation($type, 'Test situation', SituationPriority::Normal, 'entity', 1);
        if ($recommended) {
            $s->recommend($recommended);
        }
        return $s;
    }

    private function registerCap(string $name, mixed $returns = ['ok' => true]): void
    {
        $this->capRegistry->define($name)
            ->allowedFor(['human'])
            ->handleUsing(fn (array $input, $actor) => $returns);
    }

    public function test_process_returns_null_when_no_plan_available(): void
    {
        $situation = $this->makeSituation(); // no recommendedAction, no planner
        $actor = Actor::human('u1')->withCapabilities(['some_cap']);

        $action = $this->orchestrator->process($situation, $actor);

        $this->assertNull($action);
    }

    public function test_process_uses_recommended_action_as_deterministic_plan(): void
    {
        $this->registerCap('do_review');
        $situation = $this->makeSituation('review_needed', 'do_review');
        $actor = Actor::human('u1')->withCapabilities(['do_review']);

        $action = $this->orchestrator->process($situation, $actor);

        $this->assertInstanceOf(Action::class, $action);
        $this->assertTrue($action->outcome()->success);
        $this->assertEquals('do_review', $action->capabilityName);
    }

    public function test_process_resolves_situation_after_successful_action(): void
    {
        $this->registerCap('resolve_cap');
        $situation = $this->makeSituation('resolvable', 'resolve_cap');
        $actor = Actor::human('u1')->withCapabilities(['resolve_cap']);

        $this->orchestrator->process($situation, $actor);

        $this->assertEquals(SituationStatus::Resolved, $situation->status());
    }

    public function test_process_uses_custom_planner_closure(): void
    {
        $this->registerCap('planned_cap');
        $situation = $this->makeSituation(); // no recommended action

        $this->orchestrator->planUsing(fn (Situation $s, array $actors) => [
            'capability' => 'planned_cap',
            'input'      => ['from_planner' => true],
        ]);

        $actor = Actor::human('u1')->withCapabilities(['planned_cap']);
        $action = $this->orchestrator->process($situation, $actor);

        $this->assertNotNull($action);
        $this->assertEquals('planned_cap', $action->capabilityName);
    }

    public function test_process_records_failed_outcome_when_capability_throws(): void
    {
        $this->capRegistry->define('failing_cap')
            ->allowedFor(['human'])
            ->handleUsing(fn () => throw new \RuntimeException('cap error'));

        $situation = $this->makeSituation('fail_case', 'failing_cap');
        $actor = Actor::human('u1')->withCapabilities(['failing_cap']);

        // Orchestrator is resilient: it catches handler exceptions, records a
        // failed ActionOutcome, and returns the Action — it does NOT re-throw.
        $action = $this->orchestrator->process($situation, $actor);

        $this->assertNotNull($action);
        $this->assertNotNull($action->outcome());
        $this->assertFalse($action->outcome()->success);
        $this->assertEquals('cap error', $action->outcome()->error);
    }

    public function test_responsibility_is_resolved_and_assigned_to_situation(): void
    {
        $respRegistry  = $this->app->make(ResponsibilityRegistry::class);
        $actorRegistry = $this->app->make(ActorRegistry::class);

        $respRegistry->define('assignable_situation')->role('analyst');
        $vet = Actor::human('analyst_1', 'analyst')->withCapabilities(['do_analysis']);
        $actorRegistry->register($vet);

        $this->registerCap('do_analysis');
        $situation = $this->makeSituation('assignable_situation', 'do_analysis');
        $actor = Actor::human('analyst_1')->withCapabilities(['do_analysis']);

        $this->orchestrator->process($situation, $actor);

        // Situation should have been assigned to analyst_1 (via responsibility resolution)
        $this->assertNotEmpty($situation->responsibleActors());
    }
}
