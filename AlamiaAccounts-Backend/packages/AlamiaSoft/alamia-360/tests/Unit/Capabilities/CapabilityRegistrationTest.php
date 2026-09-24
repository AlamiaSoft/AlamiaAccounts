<?php

namespace Alamia360\Tests\Unit\Capabilities;

use Alamia360\Capabilities\Capability;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Facades\Alamia360;
use Alamia360\Tests\TestCase;

class CapabilityRegistrationTest extends TestCase
{
    public function test_capability_can_be_defined_and_retrieved(): void
    {
        $cap = Alamia360::capability('get_todays_appointments')
            ->describe('Returns all appointments for today');

        $registry = $this->app->make(CapabilityRegistry::class);
        $this->assertTrue($registry->has('get_todays_appointments'));
        $this->assertSame($cap, $registry->get('get_todays_appointments'));
    }

    public function test_capability_name_is_stored(): void
    {
        $cap = Alamia360::capability('notify_doctor');
        $this->assertEquals('notify_doctor', $cap->name());
    }

    public function test_capability_fluent_schema_methods(): void
    {
        $cap = Alamia360::capability('get_pending_lab_results')
            ->describe('Returns lab results pending review')
            ->input(['limit' => ['type' => 'integer']])
            ->output(['results' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai']);

        $this->assertEquals('read', $cap->sideEffect());
        $this->assertEquals(['human', 'ai'], $cap->allowedActorTypes());
        $this->assertArrayHasKey('limit', $cap->inputSchema());
        $this->assertArrayHasKey('results', $cap->outputSchema());
    }

    public function test_capability_without_handler_throws_at_execution(): void
    {
        $cap = new Capability('no_handler_cap');
        $actor = \Alamia360\Actors\Actor::human('u1')->withCapabilities(['no_handler_cap']);

        $this->expectException(\RuntimeException::class);
        $cap->handle([], $actor);
    }

    public function test_unknown_capability_throws_invalid_argument_exception(): void
    {
        $registry = $this->app->make(CapabilityRegistry::class);
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('does_not_exist');
    }

    public function test_capability_all_returns_registered_capabilities(): void
    {
        Alamia360::capability('cap_a');
        Alamia360::capability('cap_b');

        $all = $this->app->make(CapabilityRegistry::class)->all();
        $this->assertArrayHasKey('cap_a', $all);
        $this->assertArrayHasKey('cap_b', $all);
    }
}
