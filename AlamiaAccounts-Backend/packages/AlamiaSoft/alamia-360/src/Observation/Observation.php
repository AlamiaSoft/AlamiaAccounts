<?php

namespace Alamia360\Observation;

/**
 * A normalized, low-level fact: "something happened" or "something exists
 * in a state". This is NOT yet operationally meaningful — that judgment is
 * made by a SituationDetector, which may turn an Observation into a
 * Situation (or discard it as noise).
 */
class Observation
{
    public readonly \DateTimeImmutable $observedAt;

    public function __construct(
        public readonly string $type,
        public readonly ObservationSource $source,
        public readonly ?string $entityType = null,
        public readonly mixed $entityId = null,
        public readonly array $payload = [],
        ?\DateTimeImmutable $observedAt = null,
    ) {
        $this->observedAt = $observedAt ?? new \DateTimeImmutable();
    }
}
