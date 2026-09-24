<?php

namespace Alamia360\Responsibilities;

use Alamia360\Actors\ActorRegistry;
use Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia360\Situations\Situation;

/**
 * Deterministic, data-driven responsibility resolution based on the
 * ResponsibilityRegistry. No LLM involved. An AiResponsibilityResolver
 * can implement the same contract for softer cases and be swapped in
 * by rebinding ResponsibilityResolverContract in the host app.
 */
class DefaultResponsibilityResolver implements ResponsibilityResolverContract
{
    public function __construct(
        protected ResponsibilityRegistry $registry,
        protected ActorRegistry $actors,
    ) {
    }

    public function resolve(Situation $situation): array
    {
        // $situation->type is a public readonly property (not a method)
        $rule = $this->registry->for($situation->type);

        if (!$rule) {
            return [];
        }

        // User-specific responsibility takes precedence over role.
        if ($rule->getUserId()) {
            $actor = $this->actors->find($rule->getUserId());
            return $actor ? [$actor] : [];
        }

        if ($rule->getRole()) {
            return $this->actors->byRole($rule->getRole());
        }

        return [];
    }
}
