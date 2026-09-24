<?php

namespace Alamia360\Context;

/**
 * A deliberately bounded, selective bundle of context for reasoning
 * (human or AI) — never "the whole database". Serializes cleanly for
 * prompting an AI provider or rendering to a human UI.
 */
class OperationalContext
{
    public function __construct(
        public readonly ?array $actor = null,
        public readonly ?array $situation = null,
        public readonly array $entities = [],
        public readonly array $responsibilities = [],
        public readonly array $availableCapabilities = [],
        public readonly array $recentEvents = [],
        public readonly array $policies = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'actor' => $this->actor,
            'situation' => $this->situation,
            'entities' => $this->entities,
            'responsibilities' => $this->responsibilities,
            'available_capabilities' => $this->availableCapabilities,
            'recent_events' => $this->recentEvents,
            'policies' => $this->policies,
        ];
    }
}
