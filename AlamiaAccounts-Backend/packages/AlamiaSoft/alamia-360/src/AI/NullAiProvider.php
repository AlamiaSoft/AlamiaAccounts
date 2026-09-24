<?php

namespace Alamia360\AI;

use Alamia360\Contracts\AiProviderContract;

/**
 * Default provider when no AI is configured. Guarantees Alamia 360 remains
 * fully functional (deterministic observation, situations, responsibility,
 * capability execution) without any AI dependency.
 */
class NullAiProvider implements AiProviderContract
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function reason(string $prompt, array $context = []): string
    {
        throw new \RuntimeException('No AI provider is configured. Bind Alamia360\Contracts\AiProviderContract to enable AI reasoning.');
    }

    public function classify(string $input, array $labels, array $context = []): array
    {
        throw new \RuntimeException('No AI provider is configured. Bind Alamia360\Contracts\AiProviderContract to enable AI classification.');
    }
}
