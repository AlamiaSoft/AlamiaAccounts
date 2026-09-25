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
     * @param array $context
     * @return array
     */
    public function classify(string $prompt, array $context = []): array
    {
        $enabled = config('copilot.enabled', true);
        if (!$enabled) {
            return $this->heuristicFallback($prompt, $context);
        }

        try {
            $endpoint = rtrim(config('copilot.endpoint', 'http://host.docker.internal:11434'), '/');
            $model = config('copilot.model', 'qwen3.5:4b');
            $apiKey = config('copilot.api_key');
            $timeout = (int) config('copilot.timeout', 12);

            $contextSnippet = "";
            if (!empty($context['history']) && is_array($context['history'])) {
                $recent = array_slice($context['history'], -3);
                $contextLines = [];
                foreach ($recent as $h) {
                    $sender = $h['sender'] ?? 'user';
                    $text = substr(trim($h['text'] ?? ''), 0, 100);
                    if ($text) {
                        $contextLines[] = "{$sender}: {$text}";
                    }
                }
                if (!empty($contextLines)) {
                    $contextSnippet = "Recent conversation context:\n" . implode("\n", $contextLines) . "\n\n";
                }
            }

            $systemPrompt = <<<PROMPT
You are an intent and entity classification engine for Alamia Accounts double-entry ERP.
Analyze the user query and return JSON ONLY matching this exact schema:
{
  "intent": "FIND_TRANSACTION" | "INQUIRE_VOUCHER" | "INQUIRE_ACCOUNT" | "INQUIRE_REPORT" | "DRAFT_VOUCHER" | "LIST_SITUATIONS" | "GENERAL_SEARCH" | "GREETING" | "HELP" | "UNKNOWN",
  "party": "extracted contact person / party name (e.g. 'Ali Raza') or empty string",
  "organization": "extracted company / client / vendor name (e.g. 'Izoc Ltd') or empty string",
  "target_object": "voucher" | "account" | "report" | "contact" | null,
  "reference": "extracted voucher reference (e.g. 'OB-2026-001', 'JV-102') or empty string",
  "account": "extracted account code or account name (e.g. 'Meezan Bank', '1130', 'Cash in Hand') or empty string",
  "report_type": "trial-balance" | "profit-loss" | "balance-sheet" | "cash-flow" | null,
  "is_correction": true or false,
  "confidence": float between 0.0 and 1.0
}

Intents & Guidelines:
- INQUIRE_ACCOUNT: Questions about account balances, ledgers, banks, cash, or chart of accounts (e.g. "What is the balance of Meezan Bank?", "Balance of Meezan Bank", "Account 1130", "Cash in hand balance", "Ledger for 1110")
- INQUIRE_VOUCHER: Inquiries about a specific voucher reference or voucher details (e.g. "Tell me about voucher OB-2026-001", "Show JV-2026-001")
- FIND_TRANSACTION: Searching for transactions/vouchers involving a person, contact, client, or organization (e.g. "Transaction with Mr. Ali Raza of Izoc Ltd", "Voucher with Ali", "Find payment to Izoc")
- INQUIRE_REPORT: Financial reports (e.g. "Show Trial Balance", "Profit and Loss", "Balance Sheet")
- DRAFT_VOUCHER: Recording or drafting a financial transaction with an amount (e.g. "Paid Rs. 25,000 for office supplies via Meezan Bank", "Received 50,000 from customer")
- LIST_SITUATIONS: Unresolved alerts or approvals (e.g. "Show situations", "Any pending alerts?")
- GREETING: "Hello", "Hi", "Salam", "Hey"
- HELP: "Help", "What can you do?", "Who are you?"

Examples:
- "What is the balance of Meezan Bank?" -> {"intent": "INQUIRE_ACCOUNT", "party": "", "organization": "", "target_object": "account", "reference": "", "account": "Meezan Bank", "report_type": null, "is_correction": false, "confidence": 0.98}
- "Account 1130" -> {"intent": "INQUIRE_ACCOUNT", "party": "", "organization": "", "target_object": "account", "reference": "", "account": "1130", "report_type": null, "is_correction": false, "confidence": 0.98}
- "Tell me about voucher OB-2026-001" -> {"intent": "INQUIRE_VOUCHER", "party": "", "organization": "", "target_object": "voucher", "reference": "OB-2026-001", "account": "", "report_type": null, "is_correction": false, "confidence": 0.98}
- "Transaction with Mr. Ali Raza of Izoc Ltd" -> {"intent": "FIND_TRANSACTION", "party": "Ali Raza", "organization": "Izoc Ltd", "target_object": "voucher", "reference": "", "account": "", "report_type": null, "is_correction": false, "confidence": 0.95}
- "Paid Rs. 25,000 for office supplies via Meezan Bank" -> {"intent": "DRAFT_VOUCHER", "party": "", "organization": "", "target_object": "voucher", "reference": "", "account": "Meezan Bank", "report_type": null, "is_correction": false, "confidence": 0.95}

{$contextSnippet}User query: "$prompt"
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
                    'num_predict' => 150,
                ],
            ]);

            if ($response->successful()) {
                $rawOutput = $response->json('response');
                if (is_string($rawOutput)) {
                    $parsed = json_decode($rawOutput, true);
                    if (is_array($parsed) && !empty($parsed['intent'])) {
                        $party = trim($parsed['party'] ?? '');
                        $org = trim($parsed['organization'] ?? '');
                        $ref = trim($parsed['reference'] ?? '');
                        $acc = trim($parsed['account'] ?? '');

                        // Derive typed non-empty entity
                        $entity = !empty($acc) ? $acc : (!empty($ref) ? $ref : (!empty($party) ? $party : $org));

                        return [
                            'success' => true,
                            'source' => 'llm',
                            'model' => $model,
                            'intent' => strtoupper(trim($parsed['intent'])),
                            'party' => $party,
                            'organization' => $org,
                            'target_object' => $parsed['target_object'] ?? null,
                            'reference' => $ref,
                            'account' => $acc,
                            'entity' => $entity,
                            'report_type' => $parsed['report_type'] ?? null,
                            'is_correction' => (bool) ($parsed['is_correction'] ?? false),
                            'confidence' => (float) ($parsed['confidence'] ?? 0.95),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("IntentClassifierService LLM classification failed/timed out: " . $e->getMessage());
        }

        // Graceful fallback to rule-based parser when LLM is unavailable or unconfident
        return $this->heuristicFallback($prompt, $context);
    }

    /**
     * Fast regex & keyword heuristic fallback.
     *
     * @param string $prompt
     * @param array $context
     * @return array
     */
    protected function heuristicFallback(string $prompt, array $context = []): array
    {
        $promptTrimmed = trim($prompt);
        $promptLower = strtolower($promptTrimmed);
        $isCorrection = str_starts_with($promptLower, 'no') || str_starts_with($promptLower, 'actually') || str_starts_with($promptLower, 'i mean');

        // 1. Greetings (e.g. "hello", "hi", "hey", "hello Ali Raza", "salam")
        if (preg_match('/^(hi|hello|hey|greetings|good morning|good afternoon|good evening|salam|assalam)([\s!,.].*)?$/i', $promptTrimmed)) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'GREETING',
                'party' => '',
                'organization' => '',
                'target_object' => null,
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.99,
            ];
        }

        // 2. Help & Guidance (e.g. "help", "who are you", "what can you do", "?")
        if (preg_match('/^(help|who are you|what can you do|how to use|commands|features|\?)$/i', $promptTrimmed)) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'HELP',
                'party' => '',
                'organization' => '',
                'target_object' => null,
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.99,
            ];
        }

        // 3. Voucher Reference Match (e.g. OB-2026-001, JV-1002)
        if (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $matches)) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_VOUCHER',
                'party' => '',
                'organization' => '',
                'target_object' => 'voucher',
                'reference' => $matches[0],
                'account' => '',
                'entity' => $matches[0],
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.95,
            ];
        }

        // 4. Financial Reports
        if (str_contains($promptLower, 'trial balance') || str_contains($promptLower, 'tb')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_REPORT',
                'party' => '',
                'organization' => '',
                'target_object' => 'report',
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => 'trial-balance',
                'is_correction' => false,
                'confidence' => 0.95,
            ];
        }
        if (str_contains($promptLower, 'profit') || str_contains($promptLower, 'loss') || str_contains($promptLower, 'p&l')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_REPORT',
                'party' => '',
                'organization' => '',
                'target_object' => 'report',
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => 'profit-loss',
                'is_correction' => false,
                'confidence' => 0.95,
            ];
        }
        if (str_contains($promptLower, 'balance sheet') || str_contains($promptLower, 'financial position')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_REPORT',
                'party' => '',
                'organization' => '',
                'target_object' => 'report',
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => 'balance-sheet',
                'is_correction' => false,
                'confidence' => 0.95,
            ];
        }

        // 5. Voucher Drafting with financial action AND amount (e.g. "Paid Rs. 25,000 for office supplies")
        if (
            (str_contains($promptLower, 'paid') ||
             str_contains($promptLower, 'received') ||
             str_contains($promptLower, 'spent') ||
             str_contains($promptLower, 'transfer') ||
             str_contains($promptLower, 'draft voucher')) &&
            preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $prompt)
        ) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'DRAFT_VOUCHER',
                'party' => '',
                'organization' => '',
                'target_object' => 'voucher',
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.90,
            ];
        }

        // 6. Account Inquiries & Balances (e.g. "What is the balance of Meezan Bank?", "Balance of Meezan Bank", "Account 1130", "Cash in Hand")
        $cleanedAccount = preg_replace('/^(what is the balance of|what is the balance in|what is the balance for|what is the balance|what is in|how much is in|how much in|balance of|balance in|balance for|balance|tell me about account|tell me about|show me account|show me|details of account|details of|account)\s+(the\s+|account\s+)?/i', '', $promptTrimmed);
        $cleanedAccount = trim($cleanedAccount, " ?.\"'");

        $isAccountIntent = (
            preg_match('/^[0-9]{4}$/', $cleanedAccount) ||
            str_starts_with($promptLower, 'balance') ||
            str_contains($promptLower, 'balance of') ||
            str_contains($promptLower, 'balance in') ||
            str_starts_with($promptLower, 'account') ||
            str_contains($promptLower, 'bank') ||
            str_contains($promptLower, 'cash') ||
            str_contains($promptLower, 'ledger')
        ) && !str_contains($promptLower, 'transaction') && !str_contains($promptLower, 'voucher');

        if ($isAccountIntent && !empty($cleanedAccount)) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_ACCOUNT',
                'party' => '',
                'organization' => '',
                'target_object' => 'account',
                'reference' => '',
                'account' => $cleanedAccount,
                'entity' => $cleanedAccount,
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.90,
            ];
        }

        // 7. Find Transaction / Voucher for Party or Organization
        if (
            str_contains($promptLower, 'transaction') ||
            str_contains($promptLower, 'transactions') ||
            str_contains($promptLower, 'see its voucher') ||
            str_contains($promptLower, 'show voucher for') ||
            str_contains($promptLower, 'voucher with') ||
            str_contains($promptLower, 'voucher for') ||
            str_contains($promptLower, 'payment to') ||
            str_contains($promptLower, 'receipt from') ||
            ($isCorrection && (str_contains($promptLower, 'transaction') || str_contains($promptLower, 'voucher') || str_contains($promptLower, 'mr.') || str_contains($promptLower, 'ltd')))
        ) {
            $party = '';
            $org = '';
            if (preg_match('/(?:with|for|to|from)\s+(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+?)(?:\s+of|\s+from|\s+in|\s+at|\.|\;|\,|$)/i', $prompt, $pMatch)) {
                $party = trim($pMatch[1]);
            }
            if (preg_match('/(?:of|from|at|in|company)\s+([a-z0-9\s]+?(?:ltd|limited|inc|corp|pvt|co)?)(?:\.|\;|\,|$|\s+i\s+need)/i', $prompt, $oMatch)) {
                $org = trim($oMatch[1]);
            }

            // Auto-reassign corporate names to organization slot
            if (empty($org) && !empty($party) && preg_match('/\b(ltd|limited|inc|corp|pvt|co|company|technologies|solutions|services)\b/i', $party)) {
                $org = $party;
                $party = '';
            }

            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'FIND_TRANSACTION',
                'party' => $party,
                'organization' => $org,
                'target_object' => 'voucher',
                'reference' => '',
                'account' => '',
                'entity' => $party ?: $org,
                'report_type' => null,
                'is_correction' => $isCorrection,
                'confidence' => 0.88,
            ];
        }

        // 8. Situations / Approvals
        if (str_contains($promptLower, 'situation') || str_contains($promptLower, 'pending') || str_contains($promptLower, 'approval')) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'LIST_SITUATIONS',
                'party' => '',
                'organization' => '',
                'target_object' => null,
                'reference' => '',
                'account' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.85,
            ];
        }

        // 9. General Entity Search fallback
        $entity = preg_replace('/^(tell me about|what is|how much in|search for|search|lookup|who is|show)\s+/i', '', $promptTrimmed);
        $entity = trim($entity, " ?.\"'");

        return [
            'success' => true,
            'source' => 'heuristic',
            'intent' => 'GENERAL_SEARCH',
            'party' => '',
            'organization' => '',
            'target_object' => null,
            'reference' => '',
            'account' => '',
            'entity' => $entity,
            'report_type' => null,
            'is_correction' => false,
            'confidence' => 0.70,
        ];
    }
}
