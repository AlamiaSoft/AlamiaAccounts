<?php

namespace App\Copilot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentClassifierService
{
    /**
     * Classify user prompt into structured accounting intent and extracted entities.
     *
     * @param string $prompt
     * @return array
     */
    public function classify(string $prompt): array
    {
        $enabled = config('copilot.enabled', true);
        if (!$enabled) {
            return $this->heuristicFallback($prompt);
        }

        try {
            $endpoint = rtrim(config('copilot.endpoint', 'http://localhost:11434'), '/');
            $model = config('copilot.model', 'qwen3.5:4b');
            $apiKey = config('copilot.api_key');
            $timeout = (int) config('copilot.timeout', 5);

            $systemPrompt = <<<PROMPT
You are an intent classification engine for Alamia Accounts double-entry ERP.
Analyze the user query and return JSON ONLY matching this schema:
{
  "intent": "INQUIRE_VOUCHER" | "INQUIRE_ACCOUNT" | "INQUIRE_REPORT" | "DRAFT_VOUCHER" | "LIST_SITUATIONS" | "GENERAL_SEARCH" | "UNKNOWN",
  "entity": "extracted account name, code, contact name, or voucher reference, or empty string",
  "report_type": "trial-balance" | "profit-loss" | "balance-sheet" | "cash-flow" | null,
  "confidence": float between 0.0 and 1.0
}

Intents:
- INQUIRE_VOUCHER: User asking for voucher details or reference (e.g. "Tell me about voucher OB-2026-001", "JV-102", "Show voucher details")
- INQUIRE_ACCOUNT: User asking for account balance, details, or ledger (e.g. "What is the balance of Meezan Bank?", "Account 1130", "Cash account")
- INQUIRE_REPORT: User asking for financial statements (e.g. "Show Trial Balance", "Profit and loss summary", "Balance sheet")
- DRAFT_VOUCHER: User wants to record/draft a financial entry (e.g. "Paid Rs. 25,000 for rent", "Received 50,000 from Ali")
- LIST_SITUATIONS: User asking for pending approvals, alerts, or anomalies
- GENERAL_SEARCH: User searching generally for contacts, names, or items

User query: "$prompt"
PROMPT;

            $request = Http::timeout($timeout);
            if (!empty($apiKey)) {
                $request = $request->withToken($apiKey);
            }

            // Ollama /api/generate format
            $response = $request->post("{$endpoint}/api/generate", [
                'model' => $model,
                'prompt' => $systemPrompt,
                'stream' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0.0,
                    'num_predict' => 120,
                ],
            ]);

            if ($response->successful()) {
                $rawOutput = $response->json('response');
                if (is_string($rawOutput)) {
                    $parsed = json_decode($rawOutput, true);
                    if (is_array($parsed) && !empty($parsed['intent'])) {
                        return [
                            'success' => true,
                            'source' => 'llm',
                            'model' => $model,
                            'intent' => strtoupper(trim($parsed['intent'])),
                            'entity' => trim($parsed['entity'] ?? ''),
                            'report_type' => $parsed['report_type'] ?? null,
                            'confidence' => (float) ($parsed['confidence'] ?? 0.95),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("IntentClassifierService LLM classification failed/timed out: " . $e->getMessage());
        }

        // Graceful fallback to rule-based parser when LLM is unavailable or unconfident
        return $this->heuristicFallback($prompt);
    }

    /**
     * Fast regex & keyword heuristic fallback.
     *
     * @param string $prompt
     * @return array
     */
    protected function heuristicFallback(string $prompt): array
    {
        $promptLower = strtolower(trim($prompt));

        // 1. Voucher Reference Match (e.g. OB-2026-001, JV-1002)
        if (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $matches)) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_VOUCHER',
                'entity' => $matches[0],
                'report_type' => null,
                'confidence' => 0.90,
            ];
        }

        // 2. Financial Reports
        if (str_contains($promptLower, 'trial balance') || str_contains($promptLower, 'tb')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_REPORT',
                'entity' => '',
                'report_type' => 'trial-balance',
                'confidence' => 0.90,
            ];
        }
        if (str_contains($promptLower, 'profit') || str_contains($promptLower, 'loss') || str_contains($promptLower, 'p&l')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_REPORT',
                'entity' => '',
                'report_type' => 'profit-loss',
                'confidence' => 0.90,
            ];
        }
        if (str_contains($promptLower, 'balance sheet') || str_contains($promptLower, 'position')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_REPORT',
                'entity' => '',
                'report_type' => 'balance-sheet',
                'confidence' => 0.90,
            ];
        }

        // 3. Voucher Drafting
        if (
            (str_contains($promptLower, 'paid') ||
             str_contains($promptLower, 'received') ||
             str_contains($promptLower, 'transfer') ||
             str_contains($promptLower, 'draft voucher')) &&
            preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $prompt)
        ) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'DRAFT_VOUCHER',
                'entity' => '',
                'report_type' => null,
                'confidence' => 0.85,
            ];
        }

        // 4. Situations / Approvals
        if (str_contains($promptLower, 'situation') || str_contains($promptLower, 'pending') || str_contains($promptLower, 'approval')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'LIST_SITUATIONS',
                'entity' => '',
                'report_type' => null,
                'confidence' => 0.85,
            ];
        }

        // 5. Account or General Entity Search
        $entity = preg_replace('/^(tell me about|what is the balance of|balance in|what is|how much in|search for|search|lookup)\s+/i', '', trim($prompt));
        $entity = trim($entity, " ?.\"'");

        if (preg_match('/^[0-9]{4}$/', $entity) || str_starts_with($promptLower, 'balance') || str_starts_with($promptLower, 'account')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_ACCOUNT',
                'entity' => $entity,
                'report_type' => null,
                'confidence' => 0.80,
            ];
        }

        return [
            'success' => true,
            'source' => 'heuristic',
            'intent' => 'GENERAL_SEARCH',
            'entity' => $entity,
            'report_type' => null,
            'confidence' => 0.70,
        ];
    }
}
