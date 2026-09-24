<?php

namespace Alamia360;

use Alamia360\Actors\ActorRegistry;
use Alamia360\Capabilities\Capability;
use Alamia360\Capabilities\CapabilityExecutor;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Context\ContextBuilder;
use Alamia360\Observation\Observer;
use Alamia360\Orchestration\Orchestrator;
use Alamia360\Responsibilities\Responsibility;
use Alamia360\Responsibilities\ResponsibilityRegistry;
use Alamia360\Semantic\EntityDefinition;
use Alamia360\Semantic\SemanticRegistry;
use Alamia360\Situations\SituationRegistry;

/**
 * Single, ergonomic entry point (bound as 'alamia360' / Alamia360 facade)
 * over the individual registries. Purely a convenience facade over the
 * real architecture — every method here just delegates.
 */
class Alamia360Manager
{
    public function __construct(
        protected SemanticRegistry $semantics,
        protected CapabilityRegistry $capabilities,
        protected ResponsibilityRegistry $responsibilities,
        protected ActorRegistry $actors,
        protected Observer $observer,
        protected SituationRegistry $situations,
        protected Orchestrator $orchestrator,
        protected ContextBuilder $context,
        protected CapabilityExecutor $executor,
    ) {
    }

    public function entity(string $name): EntityDefinition
    {
        return $this->semantics->entity($name);
    }

    public function capability(string $name): Capability
    {
        return $this->capabilities->define($name);
    }

    public function responsibility(string $situationType): Responsibility
    {
        return $this->responsibilities->define($situationType);
    }

    public function actors(): ActorRegistry
    {
        return $this->actors;
    }

    public function observer(): Observer
    {
        return $this->observer;
    }

    public function situations(): SituationRegistry
    {
        return $this->situations;
    }

    public function orchestrator(): Orchestrator
    {
        return $this->orchestrator;
    }

    public function context(): ContextBuilder
    {
        return $this->context;
    }

    public function capabilities(): CapabilityExecutor
    {
        return $this->executor;
    }

    public function semantics(): SemanticRegistry
    {
        return $this->semantics;
    }

    public function capabilityRegistry(): CapabilityRegistry
    {
        return $this->capabilities;
    }
}
