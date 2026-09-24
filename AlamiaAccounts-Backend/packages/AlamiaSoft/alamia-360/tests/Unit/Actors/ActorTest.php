<?php

namespace Alamia360\Tests\Unit\Actors;

use Alamia360\Actors\Actor;
use Alamia360\Actors\ActorRegistry;
use Alamia360\Actors\ActorType;
use Alamia360\Tests\TestCase;

class ActorTest extends TestCase
{
    public function test_human_factory_sets_correct_type(): void
    {
        $actor = Actor::human('user_1', 'manager');
        $this->assertEquals(ActorType::Human, $actor->type());
        $this->assertEquals('user_1', $actor->id());
        $this->assertEquals('manager', $actor->role());
    }

    public function test_ai_factory_sets_correct_type(): void
    {
        $actor = Actor::ai('ai_agent', 'clinical_assistant');
        $this->assertEquals(ActorType::Ai, $actor->type());
        $this->assertEquals('ai', $actor->type()->value);
    }

    public function test_system_factory_sets_correct_type(): void
    {
        $actor = Actor::system('notification_engine');
        $this->assertEquals(ActorType::System, $actor->type());
        $this->assertNull($actor->role());
    }

    public function test_is_not_authorized_when_capability_not_in_list(): void
    {
        $actor = Actor::human('user_2')->withCapabilities(['read_reports']);
        $this->assertFalse($actor->isAuthorizedFor('send_notification'));
    }

    public function test_is_authorized_when_capability_in_list_and_no_authorizer(): void
    {
        $actor = Actor::human('user_3')->withCapabilities(['read_reports']);
        $this->assertTrue($actor->isAuthorizedFor('read_reports'));
    }

    public function test_custom_authorizer_is_consulted_when_set(): void
    {
        $actor = Actor::human('user_4')
            ->withCapabilities(['special_cap'])
            ->authorizeUsing(fn ($actor, $cap, $subject) => $subject === 'allowed');

        $this->assertTrue($actor->isAuthorizedFor('special_cap', 'allowed'));
        $this->assertFalse($actor->isAuthorizedFor('special_cap', 'denied'));
    }

    public function test_custom_authorizer_not_called_if_capability_not_in_list(): void
    {
        $authorizerCalled = false;
        $actor = Actor::human('user_5')
            ->withCapabilities([])
            ->authorizeUsing(function () use (&$authorizerCalled) {
                $authorizerCalled = true;
                return true;
            });

        $result = $actor->isAuthorizedFor('missing_cap');
        $this->assertFalse($result);
        $this->assertFalse($authorizerCalled, 'Authorizer should not be called if capability is not in list');
    }

    public function test_actor_type_enum_values(): void
    {
        $this->assertEquals('human', ActorType::Human->value);
        $this->assertEquals('ai', ActorType::Ai->value);
        $this->assertEquals('system', ActorType::System->value);
    }

    public function test_actor_registry_find_by_id(): void
    {
        $registry = $this->app->make(ActorRegistry::class);
        $actor = Actor::human('reg_user_1');
        $registry->register($actor);

        $this->assertSame($actor, $registry->find('reg_user_1'));
        $this->assertNull($registry->find('non_existent'));
    }

    public function test_actor_registry_by_type(): void
    {
        $registry = $this->app->make(ActorRegistry::class);
        $registry->register(Actor::human('h1'));
        $registry->register(Actor::human('h2'));
        $registry->register(Actor::ai('a1'));

        $humans = $registry->byType(ActorType::Human);
        $this->assertCount(2, $humans);

        $aiActors = $registry->byType(ActorType::Ai);
        $this->assertCount(1, $aiActors);
    }

    public function test_actor_registry_by_role(): void
    {
        $registry = $this->app->make(ActorRegistry::class);
        $registry->register(Actor::human('v1', 'veterinarian'));
        $registry->register(Actor::human('v2', 'veterinarian'));
        $registry->register(Actor::human('m1', 'manager'));

        $vets = $registry->byRole('veterinarian');
        $this->assertCount(2, $vets);
    }
}
