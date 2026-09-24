<?php

namespace Alamia360\Tests\Unit\Responsibilities;

use Alamia360\Actors\Actor;
use Alamia360\Actors\ActorRegistry;
use Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia360\Responsibilities\DefaultResponsibilityResolver;
use Alamia360\Responsibilities\ResponsibilityRegistry;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Tests\TestCase;

class ResponsibilityResolutionTest extends TestCase
{
    private ResponsibilityRegistry $respRegistry;
    private ActorRegistry $actorRegistry;
    private DefaultResponsibilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->respRegistry  = $this->app->make(ResponsibilityRegistry::class);
        $this->actorRegistry = $this->app->make(ActorRegistry::class);
        $this->resolver      = new DefaultResponsibilityResolver($this->respRegistry, $this->actorRegistry);
    }

    private function makeSituation(string $type): Situation
    {
        return new Situation($type, 'Test situation', SituationPriority::Normal);
    }

    public function test_resolves_actors_by_role(): void
    {
        $this->respRegistry->define('lab_result_review_required')->role('veterinarian');
        $this->actorRegistry->register(Actor::human('vet_1', 'veterinarian'));
        $this->actorRegistry->register(Actor::human('vet_2', 'veterinarian'));
        $this->actorRegistry->register(Actor::human('mgr_1', 'manager'));

        $actors = $this->resolver->resolve($this->makeSituation('lab_result_review_required'));

        $this->assertCount(2, $actors);
        foreach ($actors as $actor) {
            $this->assertEquals('veterinarian', $actor->role());
        }
    }

    public function test_resolves_specific_user_when_set(): void
    {
        $this->respRegistry->define('invoice_approval')->user('user_finance_42');
        $this->actorRegistry->register(Actor::human('user_finance_42', 'accountant'));

        $actors = $this->resolver->resolve($this->makeSituation('invoice_approval'));

        $this->assertCount(1, $actors);
        $this->assertEquals('user_finance_42', $actors[0]->id());
    }

    public function test_user_id_takes_precedence_over_role(): void
    {
        // Both user and role set — user should win
        $resp = $this->respRegistry->define('mixed_responsibility');
        $resp->user('specific_user')->role('some_role');

        $this->actorRegistry->register(Actor::human('specific_user', 'some_role'));
        $this->actorRegistry->register(Actor::human('other_user', 'some_role'));

        $actors = $this->resolver->resolve($this->makeSituation('mixed_responsibility'));

        $this->assertCount(1, $actors);
        $this->assertEquals('specific_user', $actors[0]->id());
    }

    public function test_returns_empty_array_when_no_responsibility_defined(): void
    {
        $actors = $this->resolver->resolve($this->makeSituation('unknown_situation_type'));
        $this->assertEmpty($actors);
    }

    public function test_returns_empty_array_when_actor_not_found_for_user_id(): void
    {
        $this->respRegistry->define('orphan_responsibility')->user('ghost_user');
        // ghost_user is not registered in ActorRegistry

        $actors = $this->resolver->resolve($this->makeSituation('orphan_responsibility'));
        $this->assertEmpty($actors);
    }

    public function test_responsibility_escalation_data_is_accessible(): void
    {
        $resp = $this->respRegistry->define('urgent_task')
            ->role('nurse')
            ->escalatesTo('head_nurse', 60)
            ->dueWithin(120);

        $this->assertEquals(60, $resp->getEscalation()['after_minutes']);
        $this->assertEquals('head_nurse', $resp->getEscalation()['to']);
        $this->assertEquals(120, $resp->getDueWithinMinutes());
    }

    public function test_resolver_contract_is_bound_correctly(): void
    {
        $resolver = $this->app->make(ResponsibilityResolverContract::class);
        $this->assertInstanceOf(DefaultResponsibilityResolver::class, $resolver);
    }
}
