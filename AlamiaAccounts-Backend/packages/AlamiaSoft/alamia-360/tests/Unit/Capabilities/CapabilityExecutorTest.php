<?php

namespace Alamia360\Tests\Unit\Capabilities;

use Alamia360\Actors\Actor;
use Alamia360\Capabilities\CapabilityAuthorizationException;
use Alamia360\Capabilities\CapabilityExecutor;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Facades\Alamia360;
use Alamia360\Tests\TestCase;

class CapabilityExecutorTest extends TestCase
{
    private CapabilityExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = $this->app->make(CapabilityExecutor::class);
    }

    private function registerCap(string $name, string $sideEffect = 'read', array $actorTypes = ['human', 'ai', 'system']): void
    {
        Alamia360::capability($name)
            ->describe("Test capability: {$name}")
            ->withSideEffect($sideEffect)
            ->allowedFor($actorTypes)
            ->handleUsing(fn (array $input, $actor) => ['executed' => true, 'by' => $actor->id()]);
    }

    public function test_authorized_actor_executes_capability_and_returns_result(): void
    {
        $this->registerCap('run_report');
        $actor = Actor::human('user_1')->withCapabilities(['run_report']);

        $result = $this->executor->execute('run_report', [], $actor);

        $this->assertEquals(['executed' => true, 'by' => 'user_1'], $result);
    }

    public function test_actor_type_not_in_allowed_list_throws_authorization_exception(): void
    {
        $this->registerCap('human_only', 'read', ['human']); // AI not allowed

        $aiActor = Actor::ai('ai_agent')->withCapabilities(['human_only']);

        $this->expectException(CapabilityAuthorizationException::class);
        $this->executor->execute('human_only', [], $aiActor);
    }

    public function test_actor_without_capability_in_its_list_throws_authorization_exception(): void
    {
        $this->registerCap('restricted_cap');
        $actor = Actor::human('user_2')->withCapabilities([]); // no capabilities assigned

        $this->expectException(CapabilityAuthorizationException::class);
        $this->executor->execute('restricted_cap', [], $actor);
    }

    public function test_actor_with_custom_authorizer_that_denies_throws_authorization_exception(): void
    {
        $this->registerCap('guarded_cap');
        $actor = Actor::human('user_3')
            ->withCapabilities(['guarded_cap'])
            ->authorizeUsing(fn ($actor, $cap, $subject) => false); // always deny

        $this->expectException(CapabilityAuthorizationException::class);
        $this->executor->execute('guarded_cap', [], $actor);
    }

    public function test_audit_hook_is_called_with_correct_event_shape(): void
    {
        $this->registerCap('audited_cap');
        $actor = Actor::human('user_4')->withCapabilities(['audited_cap']);

        $auditedEvent = null;
        $this->executor->auditUsing(function (array $event) use (&$auditedEvent) {
            $auditedEvent = $event;
        });

        $this->executor->execute('audited_cap', ['param' => 'value'], $actor);

        $this->assertNotNull($auditedEvent);
        $this->assertEquals('audited_cap', $auditedEvent['capability']);
        $this->assertEquals('user_4', $auditedEvent['actor_id']);
        $this->assertEquals('human', $auditedEvent['actor_type']);
        $this->assertEquals(['param' => 'value'], $auditedEvent['input']);
        $this->assertNull($auditedEvent['error']);
        $this->assertNotNull($auditedEvent['at']);
    }

    public function test_audit_hook_captures_error_when_handler_throws(): void
    {
        $registry = $this->app->make(CapabilityRegistry::class);
        $registry->define('failing_cap')
            ->allowedFor(['human'])
            ->handleUsing(fn () => throw new \RuntimeException('handler failed'));

        $actor = Actor::human('user_5')->withCapabilities(['failing_cap']);

        $auditedEvent = null;
        $this->executor->auditUsing(function (array $event) use (&$auditedEvent) {
            $auditedEvent = $event;
        });

        try {
            $this->executor->execute('failing_cap', [], $actor);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNotNull($auditedEvent);
        $this->assertEquals('handler failed', $auditedEvent['error']);
        $this->assertNull($auditedEvent['result']);
    }

    public function test_handler_exception_is_re_thrown_after_auditing(): void
    {
        $registry = $this->app->make(CapabilityRegistry::class);
        $registry->define('re_throw_cap')
            ->allowedFor(['human'])
            ->handleUsing(fn () => throw new \InvalidArgumentException('re-thrown'));

        $actor = Actor::human('user_6')->withCapabilities(['re_throw_cap']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('re-thrown');
        $this->executor->execute('re_throw_cap', [], $actor);
    }
}
