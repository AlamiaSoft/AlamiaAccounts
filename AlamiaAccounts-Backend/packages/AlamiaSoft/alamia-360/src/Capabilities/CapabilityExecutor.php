<?php

namespace Alamia360\Capabilities;

use Alamia360\Contracts\ActorContract;
use Alamia360\Contracts\CapabilityContract;

/**
 * The single execution path for capabilities, used by every adapter
 * (internal API, REST controller, MCP tool, Agent). Enforces:
 *   1. actor-type restriction
 *   2. host-application authorization (never bypassed for AI actors)
 *   3. audit hook
 */
class CapabilityExecutor
{
    public function __construct(
        protected CapabilityRegistry $registry,
        protected ?\Closure $auditor = null,
    ) {
    }

    public function auditUsing(\Closure $auditor): static
    {
        $this->auditor = $auditor;
        return $this;
    }

    public function execute(string $capabilityName, array $input, ActorContract $actor, mixed $subject = null): mixed
    {
        $capability = $this->registry->get($capabilityName);

        $this->assertActorTypeAllowed($capability, $actor);
        $this->assertAuthorized($capability, $actor, $subject);

        $result = null;
        $error = null;

        try {
            $result = $capability->handle($input, $actor);
        } catch (\Throwable $e) {
            $error = $e;
        }

        if ($this->auditor) {
            ($this->auditor)([
                'capability' => $capability->name(),
                'side_effect' => $capability->sideEffect(),
                'actor_id' => $actor->id(),
                'actor_type' => $actor->type()->value,
                'input' => $input,
                'result' => $error ? null : $result,
                'error' => $error?->getMessage(),
                'at' => now(),
            ]);
        }

        if ($error) {
            throw $error;
        }

        return $result;
    }

    protected function assertActorTypeAllowed(CapabilityContract $capability, ActorContract $actor): void
    {
        if (!in_array($actor->type()->value, $capability->allowedActorTypes(), true)) {
            throw new CapabilityAuthorizationException(
                "Actor type [{$actor->type()->value}] is not permitted to invoke [{$capability->name()}]."
            );
        }
    }

    protected function assertAuthorized(CapabilityContract $capability, ActorContract $actor, mixed $subject): void
    {
        if (!$actor->isAuthorizedFor($capability->name(), $subject)) {
            throw new CapabilityAuthorizationException(
                "Actor [{$actor->id()}] is not authorized for capability [{$capability->name()}]."
            );
        }
    }
}
