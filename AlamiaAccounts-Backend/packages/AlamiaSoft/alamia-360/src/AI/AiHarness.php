<?php

namespace Alamia360\AI;

use Alamia360\Context\OperationalContext;
use Alamia360\Contracts\AiProviderContract;

/**
 * Thin facade in front of whichever AiProviderContract is bound — this is
 * the seam where a future AI Router (local/cloud/frontier model selection)
 * can be inserted without touching the rest of the architecture.
 */
class AiHarness
{
    public function __construct(protected AiProviderContract $provider)
    {
    }

    public function isAvailable(): bool
    {
        return $this->provider->isAvailable();
    }

    public function reasonAbout(string $prompt, OperationalContext $context): string
    {
        return $this->provider->reason($prompt, $context->toArray());
    }

    public function classify(string $input, array $labels, OperationalContext $context): array
    {
        return $this->provider->classify($input, $labels, $context->toArray());
    }
}
