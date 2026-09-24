<?php

namespace Alamia360\Contracts;

/**
 * Alamia 360 is LLM-agnostic. Any provider (OpenAI, Anthropic, local model,
 * router) implements this. Architecture stays fully functional without AI
 * via NullAiProvider.
 */
interface AiProviderContract
{
    public function isAvailable(): bool;

    /** @param array<string,mixed> $context */
    public function reason(string $prompt, array $context = []): string;

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function classify(string $input, array $labels, array $context = []): array;
}
