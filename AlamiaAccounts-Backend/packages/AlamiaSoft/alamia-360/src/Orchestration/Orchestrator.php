<?php

namespace Alamia360\Orchestration;

use Alamia360\Actions\Action;
use Alamia360\Actions\ActionOutcome;
use Alamia360\Capabilities\CapabilityExecutor;
use Alamia360\Contracts\ActorContract;
use Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia360\Situations\Situation;

/**
 * Implements the Observe -> Interpret -> Assess -> Plan -> Authorize ->
 * Act -> Verify lifecycle at the point a Situation is actioned.
 *
 * "Observe" happens upstream in Observer; this class picks up from
 * Interpret onward. Deterministic by default — AI is only consulted when a
 * planner is explicitly wired in via planUsing().
 */
class Orchestrator
{
    protected ?\Closure $planner = null;

    public function __construct(
        protected ResponsibilityResolverContract $responsibility,
        protected CapabilityExecutor $capabilities,
    ) {
    }

    /**
     * Optional AI/rule-based planner: fn(Situation $s, ActorContract[] $actors)
     * -> array{capability: string, input: array}|null
     */
    public function planUsing(\Closure $planner): static
    {
        $this->planner = $planner;
        return $this;
    }

    /**
     * Run the lifecycle for a situation up to (and including) execution,
     * if a plan and an authorized actor are available.
     */
    public function process(Situation $situation, ActorContract $performedBy, ?ActorContract $authorizedBy = null): ?Action
    {
        // Interpret + Assess: figure out who is responsible.
        $responsibleActors = $this->responsibility->resolve($situation);
        $situation->assignTo($responsibleActors);

        // Plan: deterministic default is "no automatic plan" — a capability
        // must be explicitly proposed via recommend() or a planner closure.
        $plan = $this->plan($situation, $responsibleActors);

        if (!$plan) {
            return null;
        }

        // Authorize + Act: goes through CapabilityExecutor, which enforces
        // actor-type + host-application authorization unconditionally.
        $action = new Action($situation, $plan['capability'], $plan['input'], $performedBy, $authorizedBy);

        try {
            $result = $this->capabilities->execute($plan['capability'], $plan['input'], $performedBy, $situation->subjectId);
            $action->complete(new ActionOutcome(true, $result, null, new \DateTimeImmutable()));
            $situation->resolve(['action' => $plan['capability'], 'result' => $result]);
        } catch (\Throwable $e) {
            $action->complete(new ActionOutcome(false, null, $e->getMessage(), new \DateTimeImmutable()));
        }

        // Verify: left as an extension point — inspect $action->outcome()
        // and re-observe if needed (e.g. dispatch a follow-up Observation).

        return $action;
    }

    protected function plan(Situation $situation, array $responsibleActors): ?array
    {
        if ($this->planner) {
            return ($this->planner)($situation, $responsibleActors);
        }

        if ($situation->recommendedAction()) {
            return ['capability' => $situation->recommendedAction(), 'input' => $situation->context];
        }

        return null;
    }
}
