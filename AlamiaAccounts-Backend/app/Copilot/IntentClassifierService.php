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
Analyze the user query inside <user_query>...</user_query> and return JSON ONLY matching this exact schema:
{
  "intent": "FIND_TRANSACTION" | "INQUIRE_VOUCHER" | "VOUCHER_ACTION" | "INQUIRE_ENTITY" | "INQUIRE_ACCOUNT" | "INQUIRE_REPORT" | "DRAFT_VOUCHER" | "RESTRICTED_ACTION" | "LIST_SITUATIONS" | "GENERAL_SEARCH" | "GREETING" | "HELP" | "UNKNOWN",
  "party": "extracted contact person / party name (e.g. 'Ali Raza') or empty string",
  "organization": "extracted company / client / vendor name (e.g. 'Izoc Ltd') or empty string",
  "entity_type": "person" | "organization" | "account" | "voucher" | null,
  "target_object": "transaction" | "voucher" | "account" | "report" | "contact" | null,
  "reference": "extracted voucher reference (e.g. 'OB-2026-001', 'JV-102') or empty string",
  "account": "extracted account code or account name (e.g. 'Meezan Bank', '1130', 'Cash in Hand') or empty string",
  "action": "edit_narration" | "delete_narration" | "modify_amount" | "delete_voucher" | "reverse_voucher" | "view_voucher" | "delete_account" | null,
  "direction": "incoming" | "outgoing" | "any" | null,
  "action_type": "payment" | "receipt" | "transfer" | "journal" | "adjustment" | null,
  "amount": float or int or null,
  "currency": "PKR" | "USD" | "EUR" | "GBP" | null,
  "date_filter": {
    "from": "YYYY-MM-DD or text or null",
    "to": "YYYY-MM-DD or text or null"
  } or null,
  "report_type": "trial-balance" | "profit-loss" | "balance-sheet" | "cash-flow" | null,
  "is_correction": true or false,
  "confidence": float between 0.0 and 1.0
}

Intents & Semantic Rules:
1. INQUIRE_ENTITY: Inquiries asking about an individual person, vendor, client, contact, or organization (e.g. "Who is Ali Raza?", "Who is IZOC?", "Who is this IZOC???", "Tell me about Ali Raza", "Tell me about IZOC").
   - Strip deictic words ("this", "that", "the") from extracted party/organization names (e.g. "this IZOC" -> "IZOC").
   - entity_type: "person" or "organization".
2. FIND_TRANSACTION: Searching for historical transactions involving a person, contact, vendor, or organization.
   - target_object: "transaction" by default (or "voucher" if user explicitly asks for voucher e.g. "show voucher for...").
   - direction: "outgoing" (paid to / spent on), "incoming" (received from / customer paid), "any" (general).
3. INQUIRE_VOUCHER: Inquiries retrieving a specific voucher's details or narration (e.g. "Show OB-2026-001", "What is the narration of OB-2026-001?").
4. VOUCHER_ACTION: Action requests targeting a voucher (e.g. "delete the wrong narration entered in voucher number: ob-2026-001", "change the narration of OB-2026-001", "reverse OB-2026-001", "remove narration from OB-2026-001").
   - action: "edit_narration" | "delete_narration" | "reverse_voucher" | "modify_amount" | "delete_voucher"
5. INQUIRE_ACCOUNT: Explicit inquiries about ledger accounts, chart of accounts, or account balances.
   - Requires explicit account language (e.g. "balance of...", "account 1130", "Cash in Hand balance", "show chart of accounts").
   - Do NOT classify queries containing person/party names as INQUIRE_ACCOUNT simply because "bank" or "cash" is mentioned in passing.
6. INQUIRE_REPORT: Financial reports ("Trial Balance", "Profit & Loss", "Balance Sheet").
7. DRAFT_VOUCHER: Recording or preparing a new transaction with an amount and intent to post.
8. RESTRICTED_ACTION: Destructive or prohibited requests (e.g. "delete all accounts", "Delete voucher OB-2026-001", "wipe ledger", "change voucher amount to 500k").
9. LIST_SITUATIONS: Operational alerts, approvals, or anomalies.
10. GREETING: "Hello", "Hi", "Salam", "Hey"
11. HELP: "Help", "What can you do?"

Examples:
- "Who is Ali Raza?" -> {"intent": "INQUIRE_ENTITY", "party": "Ali Raza", "organization": "", "entity_type": "person", "target_object": "contact", "reference": "", "account": "", "action": null, "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.98}
- "Who is this IZOC???" -> {"intent": "INQUIRE_ENTITY", "party": "", "organization": "IZOC", "entity_type": "organization", "target_object": "contact", "reference": "", "account": "", "action": null, "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.98}
- "Who is IZOC Pvt Ltd?" -> {"intent": "INQUIRE_ENTITY", "party": "", "organization": "IZOC Pvt Ltd", "entity_type": "organization", "target_object": "contact", "reference": "", "account": "", "action": null, "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.98}
- "delete the wrong narration entered in voucher number: ob-2026-001" -> {"intent": "VOUCHER_ACTION", "party": "", "organization": "", "entity_type": null, "target_object": "voucher", "reference": "OB-2026-001", "account": "", "action": "edit_narration", "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.99}
- "reverse OB-2026-001" -> {"intent": "VOUCHER_ACTION", "party": "", "organization": "", "entity_type": null, "target_object": "voucher", "reference": "OB-2026-001", "account": "", "action": "reverse_voucher", "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.99}
- "What is the balance of Meezan Bank?" -> {"intent": "INQUIRE_ACCOUNT", "party": "", "organization": "", "entity_type": "account", "target_object": "account", "reference": "", "account": "Meezan Bank", "action": null, "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.98}
- "delete all accounts" -> {"intent": "RESTRICTED_ACTION", "party": "", "organization": "", "entity_type": null, "target_object": "account", "reference": "", "account": "", "action": "delete_account", "direction": null, "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.99}
- "Transaction with Mr. Ali Raza of Izoc Ltd" -> {"intent": "FIND_TRANSACTION", "party": "Ali Raza", "organization": "Izoc Ltd", "entity_type": null, "target_object": "transaction", "reference": "", "account": "", "action": null, "direction": "any", "action_type": null, "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.95}
- "What did we pay Ali Raza?" -> {"intent": "FIND_TRANSACTION", "party": "Ali Raza", "organization": "", "entity_type": null, "target_object": "transaction", "reference": "", "account": "", "action": null, "direction": "outgoing", "action_type": "payment", "amount": null, "currency": null, "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.95}
- "Paid Rs. 25,000 for office supplies via Meezan Bank" -> {"intent": "DRAFT_VOUCHER", "party": "", "organization": "", "entity_type": null, "target_object": "voucher", "reference": "", "account": "Meezan Bank", "action": null, "direction": "outgoing", "action_type": "payment", "amount": 25000, "currency": "PKR", "date_filter": null, "report_type": null, "is_correction": false, "confidence": 0.95}

{$contextSnippet}<user_query>
{$prompt}
</user_query>
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
                    'num_predict' => 200,
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

                        // Clean deictic words from party / organization
                        $party = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $party));
                        $org = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $org));

                        // Typed entity value for generic display fallback
                        $entityType = $parsed['entity_type'] ?? (!empty($acc) ? 'account' : (!empty($ref) ? 'reference' : (!empty($party) ? 'party' : (!empty($org) ? 'organization' : null))));
                        $entityValue = !empty($acc) ? $acc : (!empty($ref) ? $ref : (!empty($party) ? $party : $org));

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
                            'action' => $parsed['action'] ?? null,
                            'entity_type' => $entityType,
                            'entity_value' => $entityValue,
                            'entity' => $entityValue,
                            'direction' => $parsed['direction'] ?? null,
                            'action_type' => $parsed['action_type'] ?? null,
                            'amount' => isset($parsed['amount']) && is_numeric($parsed['amount']) ? (float) $parsed['amount'] : null,
                            'currency' => $parsed['currency'] ?? null,
                            'date_filter' => $parsed['date_filter'] ?? null,
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => null,
                'entity_value' => '',
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => null,
                'entity_value' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.99,
            ];
        }

        // 3. Voucher Actions (Narration modification, Reversals, Amount Mutation, Deletion)
        if (preg_match('/\b(delete|remove|change|modify|correct|update|clear|erase)\s+(?:the\s+)?(?:wrong\s+)?(?:narration|description|memo|note|text)\s+(?:entered\s+in|of|in|from|for)?\s*(?:voucher\s+(?:number:?|no:?|#)?)?\s*([a-z0-9-]+)?/i', $promptTrimmed, $nMatch)) {
            $ref = !empty($nMatch[2]) && preg_match('/^[a-z0-9-]+$/i', $nMatch[2]) ? $nMatch[2] : '';
            if (empty($ref) && preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $vm)) {
                $ref = $vm[0];
            }
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'VOUCHER_ACTION',
                'party' => '',
                'organization' => '',
                'target_object' => 'voucher',
                'reference' => strtoupper($ref),
                'account' => '',
                'action' => 'edit_narration',
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'reference',
                'entity_value' => strtoupper($ref),
                'entity' => strtoupper($ref),
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.99,
            ];
        }

        if (preg_match('/\b(reverse|void|cancel)\s+(?:the\s+)?(?:voucher\s+)?([a-z0-9-]+)\b/i', $promptTrimmed, $revMatch)) {
            $ref = strtoupper($revMatch[2]);
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'VOUCHER_ACTION',
                'party' => '',
                'organization' => '',
                'target_object' => 'voucher',
                'reference' => $ref,
                'account' => '',
                'action' => 'reverse_voucher',
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'reference',
                'entity_value' => $ref,
                'entity' => $ref,
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.99,
            ];
        }

        // 3.5 Restricted & Destructive Actions (MUST PRECEDE VOUCHER/ACCOUNT REGEX MATCHES)
        if (
            preg_match('/\b(delete|remove|purge|erase|drop|wipe|destroy)\s+(?:all\s+)?(accounts?|chart\s+of\s+accounts?|ledger|vouchers?|database|company|entries|data)\b/i', $promptTrimmed) ||
            preg_match('/\b(delete|remove|purge|erase|drop)\s+(?:the\s+)?account\s+(\d{4}|[a-z0-9\s]+)/i', $promptTrimmed) ||
            preg_match('/\b(delete|remove|purge|erase|drop)\s+(?:the\s+)?voucher\s+([a-z0-9-]+)/i', $promptTrimmed, $delMatch) ||
            preg_match('/\b(change|modify|update|edit|alter)\s+(?:the\s+)?(?:voucher\s+)?(?:amount|total|lines?)\b/i', $promptTrimmed)
        ) {
            $ref = !empty($delMatch[2]) ? $delMatch[2] : '';
            $isMutation = (bool) preg_match('/\b(change|modify|update|edit|alter)\s+(?:the\s+)?(?:voucher\s+)?(?:amount|total|lines?)\b/i', $promptTrimmed);
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'RESTRICTED_ACTION',
                'party' => '',
                'organization' => '',
                'target_object' => !empty($ref) ? 'voucher' : (str_contains($promptLower, 'account') ? 'account' : null),
                'reference' => $ref,
                'account' => '',
                'action' => $isMutation ? 'modify_amount' : (!empty($ref) ? 'delete_voucher' : 'delete_account'),
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => !empty($ref) ? 'reference' : null,
                'entity_value' => $ref,
                'entity' => $ref,
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.99,
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'report',
                'entity_value' => 'trial-balance',
                'entity' => 'trial-balance',
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'report',
                'entity_value' => 'profit-loss',
                'entity' => 'profit-loss',
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'report',
                'entity_value' => 'balance-sheet',
                'entity' => 'balance-sheet',
                'report_type' => 'balance-sheet',
                'is_correction' => false,
                'confidence' => 0.95,
            ];
        }

        // 5. Voucher Drafting with financial action AND amount (e.g. "Paid Rs. 25,000 for office supplies", "Create payment of Rs. 10,000 to Ali")
        $isQuestionInquiry = (bool) preg_match('/^(what was|what did|what is|how much was|did we|was there|show|find|lookup|tell me about|check)\b/i', $promptTrimmed);

        // Strip date patterns (e.g. "15 March", "25 Sep") before checking for currency amounts
        $promptWithoutDates = preg_replace('/\b[0-9]{1,2}\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|january|february|march|april|may|june|july|august|september|october|november|december)\b/i', '', $promptTrimmed);

        if (
            !$isQuestionInquiry &&
            (str_contains($promptLower, 'paid') ||
             str_contains($promptLower, 'pay ') ||
             str_contains($promptLower, 'payment') ||
             str_contains($promptLower, 'received') ||
             str_contains($promptLower, 'receipt') ||
             str_contains($promptLower, 'spent') ||
             str_contains($promptLower, 'transfer') ||
             str_contains($promptLower, 'record') ||
             str_contains($promptLower, 'draft voucher') ||
             str_contains($promptLower, 'create payment') ||
             str_contains($promptLower, 'create receipt')) &&
            preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $promptWithoutDates, $amtMatch)
        ) {
            $amt = (float) str_replace(',', '', $amtMatch[1]);
            $action = (str_contains($promptLower, 'received') || str_contains($promptLower, 'receipt')) ? 'receipt' :
                      ((str_contains($promptLower, 'transfer')) ? 'transfer' : 'payment');
            $dir = $action === 'receipt' ? 'incoming' : 'outgoing';

            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'DRAFT_VOUCHER',
                'party' => '',
                'organization' => '',
                'target_object' => 'voucher',
                'reference' => '',
                'account' => '',
                'direction' => $dir,
                'action_type' => $action,
                'amount' => $amt,
                'currency' => 'PKR',
                'date_filter' => null,
                'entity_type' => null,
                'entity_value' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.90,
            ];
        }

        // 6. Voucher Reference Match (e.g. OB-2026-001, JV-1002)
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'reference',
                'entity_value' => $matches[0],
                'entity' => $matches[0],
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.95,
            ];
        }

        // 7. Account Inquiries & Balances (Explicit account vocabulary or account names without transaction context)
        $hasTransactionContext = str_contains($promptLower, 'transaction') ||
            str_contains($promptLower, 'voucher') ||
            str_contains($promptLower, 'paid') ||
            str_contains($promptLower, 'payment') ||
            str_contains($promptLower, 'receipt') ||
            str_contains($promptLower, 'spent') ||
            str_contains($promptLower, 'received');

        $cleanedAccount = preg_replace('/^(what is the balance of|what is the balance in|what is the balance for|what is the balance|what is in|how much is in|how much in|balance of|balance in|balance for|balance|tell me about account|tell me about|show me account|show me|details of account|details of|account)\s+(the\s+|account\s+)?/i', '', $promptTrimmed);
        $cleanedAccount = trim($cleanedAccount, " ?.\"'");

        $isExplicitAccountQuery = (
            preg_match('/^[0-9]{4}$/', $cleanedAccount) ||
            str_starts_with($promptLower, 'balance') ||
            str_contains($promptLower, 'balance of') ||
            str_contains($promptLower, 'balance in') ||
            str_starts_with($promptLower, 'account ') ||
            str_contains($promptLower, 'chart of accounts') ||
            str_contains($promptLower, 'ledger statement') ||
            (!$hasTransactionContext && (
                str_contains($promptLower, 'cash in hand') ||
                str_contains($promptLower, 'cash') ||
                str_contains($promptLower, 'meezan') ||
                str_contains($promptLower, 'alfalah') ||
                str_contains($promptLower, 'bank') ||
                str_contains($promptLower, 'payable') ||
                str_contains($promptLower, 'receivable') ||
                str_contains($promptLower, 'equity') ||
                str_contains($promptLower, 'capital')
            ))
        ) && !str_contains($promptLower, 'transaction') && !str_contains($promptLower, 'voucher');

        if ($isExplicitAccountQuery && !empty($cleanedAccount)) {
            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'INQUIRE_ACCOUNT',
                'party' => '',
                'organization' => '',
                'target_object' => 'account',
                'reference' => '',
                'account' => $cleanedAccount,
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => 'account',
                'entity_value' => $cleanedAccount,
                'entity' => $cleanedAccount,
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.90,
            ];
        }

        // 7.8 Inquire Entity / Contact / Organization Queries (e.g. "Who is Ali Raza?", "Who is this IZOC???", "Tell me about IZOC", "Who is he?", "What company is Ali Raza associated with?")
        if (
            preg_match('/^who\s+(?:is|are)\s+(?:this\s+|that\s+|the\s+|a\s+|an\s+)?([a-z0-9\s.,-]+?)[\s?!.]*$/i', $promptTrimmed, $whoMatch) ||
            preg_match('/^tell\s+me\s+about\s+(?:party\s+|contact\s+|person\s+|client\s+|vendor\s+|company\s+)?([a-z0-9\s.,-]+?)[\s?!.]*$/i', $promptTrimmed, $tellMatch) ||
            preg_match('/^(?:what\s+company\s+is|what\s+firm\s+is|who\s+is)\s+([a-z0-9\s.,-]+?)\s+(?:associated\s+with|working\s+with|affiliated\s+with)[\s?!.]*$/i', $promptTrimmed, $assocMatch)
        ) {
            $rawCand = !empty($whoMatch[1]) ? $whoMatch[1] : (!empty($tellMatch[1]) ? $tellMatch[1] : ($assocMatch[1] ?? ''));
            $rawCand = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $rawCand), " ?.!\"'");

            $candLower = strtolower($rawCand);
            $isAccountLike = preg_match('/^[0-9]{4}$/', $rawCand) || in_array($candLower, ['cash', 'cash in hand', 'meezan bank', 'bank alfalah', 'bank', 'capital', 'equity', 'payable', 'receivable']);
            $isVoucherLike = preg_match('/^(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+$/i', $rawCand);
            $isReportLike = in_array($candLower, ['trial balance', 'profit and loss', 'balance sheet', 'tb', 'p&l']);

            if (!$isAccountLike && !$isVoucherLike && !$isReportLike && !empty($rawCand)) {
                // Conversational pronoun / deictic resolution from context history
                $resolvedCand = $rawCand;
                if (in_array($candLower, ['he', 'she', 'him', 'her', 'it', 'them', 'this', 'that', 'this company', 'that company', 'this person'])) {
                    if (!empty($context['history'])) {
                        foreach (array_reverse($context['history']) as $h) {
                            $htext = $h['text'] ?? '';
                            if (preg_match('/(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+(?:ltd|pvt|inc|corp|company|ali raza|izoc))/i', $htext, $hm)) {
                                $resolvedCand = trim($hm[0]);
                                break;
                            }
                        }
                    }
                }

                $isOrg = preg_match('/\b(ltd|limited|inc|corp|pvt|co|company|technologies|solutions|services|group|holdings|enterprises)\b/i', $resolvedCand) ||
                    (ctype_upper($resolvedCand) && strlen($resolvedCand) <= 6);

                return [
                    'success' => true,
                    'source' => 'heuristic',
                    'intent' => 'INQUIRE_ENTITY',
                    'party' => $isOrg ? '' : $resolvedCand,
                    'organization' => $isOrg ? $resolvedCand : '',
                    'target_object' => 'contact',
                    'reference' => '',
                    'account' => '',
                    'direction' => null,
                    'action_type' => null,
                    'amount' => null,
                    'currency' => null,
                    'date_filter' => null,
                    'entity_type' => $isOrg ? 'organization' : 'person',
                    'entity_value' => $resolvedCand,
                    'entity' => $resolvedCand,
                    'report_type' => null,
                    'is_correction' => false,
                    'confidence' => 0.95,
                ];
            }
        }

        // 8. Find Transaction for Party or Organization
        if (
            str_contains($promptLower, 'transaction') ||
            str_contains($promptLower, 'transactions') ||
            str_contains($promptLower, 'see its voucher') ||
            str_contains($promptLower, 'show voucher for') ||
            str_contains($promptLower, 'voucher with') ||
            str_contains($promptLower, 'voucher for') ||
            str_contains($promptLower, 'what did we pay') ||
            str_contains($promptLower, 'what payment') ||
            str_contains($promptLower, 'what did') ||
            str_contains($promptLower, 'what was') ||
            str_contains($promptLower, 'payment to') ||
            str_contains($promptLower, 'receipt from') ||
            str_contains($promptLower, 'what we have with') ||
            str_contains($promptLower, 'what do we have with') ||
            str_contains($promptLower, 'show me what we have') ||
            ($isCorrection && (str_contains($promptLower, 'transaction') || str_contains($promptLower, 'voucher') || str_contains($promptLower, 'mr.') || str_contains($promptLower, 'ltd')))
        ) {
            $party = '';
            $org = '';
            if (preg_match('/(?:with|for|paid\s+to|payment\s+to|pay|have\s+with)\s+(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+?)(?:\s+of|\s+from|\s+in|\s+at|\s+make|\s+through|\?|\.|\;|\,|$)/i', $prompt, $pMatch)) {
                $cand = trim($pMatch[1]);
                if (!in_array(strtolower($cand), ['we', 'us', 'me', 'our', 'them', 'him', 'her', 'it'])) {
                    $party = $cand;
                }
            }
            if (empty($party) && preg_match('/(?:did|payment\s+did|payment\s+made\s+by|from)\s+(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+?)(?:\s+pay|\s+make|\s+send|\s+transfer|\s+of|\s+from|\s+in|\s+at|\s+through|\?|\.|\;|\,|$)/i', $prompt, $pMatch2)) {
                $cand = trim($pMatch2[1]);
                if (!in_array(strtolower($cand), ['we', 'us', 'me', 'our', 'them', 'him', 'her', 'it'])) {
                    $party = $cand;
                }
            }
            if (empty($party) && preg_match('/(?:to|from)\s+(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+?)(?:\s+of|\s+from|\s+in|\s+at|\.|\;|\,|$)/i', $prompt, $pMatch3)) {
                $cand = trim($pMatch3[1]);
                if (!in_array(strtolower($cand), ['we', 'us', 'me', 'our', 'them', 'him', 'her', 'it'])) {
                    $party = $cand;
                }
            }
            if (preg_match('/(?:of|from|at|in|company)\s+([a-z0-9\s]+?(?:ltd|limited|inc|corp|pvt|co)?)(?:\.|\;|\,|$|\s+i\s+need)/i', $prompt, $oMatch)) {
                $org = trim($oMatch[1]);
            }

            // Auto-reassign corporate names or uppercase acronyms to organization slot
            if (empty($org) && !empty($party) && (preg_match('/\b(ltd|limited|inc|corp|pvt|co|company|technologies|solutions|services)\b/i', $party) || ctype_upper($party))) {
                $org = $party;
                $party = '';
            }

            $direction = null;
            if (str_contains($promptLower, 'what did we pay') || str_contains($promptLower, 'payment to') || str_contains($promptLower, 'paid to')) {
                $direction = 'outgoing';
            } elseif (str_contains($promptLower, 'pay us') || str_contains($promptLower, 'receipt from') || str_contains($promptLower, 'received from')) {
                $direction = 'incoming';
            }

            $targetObj = (str_contains($promptLower, 'voucher') || str_contains($promptLower, 'see its voucher')) ? 'voucher' : 'transaction';

            return [
                'success' => true,
                'source' => 'heuristic',
                'intent' => 'FIND_TRANSACTION',
                'party' => $party,
                'organization' => $org,
                'target_object' => $targetObj,
                'reference' => '',
                'account' => '',
                'direction' => $direction,
                'action_type' => $direction === 'incoming' ? 'receipt' : ($direction === 'outgoing' ? 'payment' : null),
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => !empty($party) ? 'party' : (!empty($org) ? 'organization' : null),
                'entity_value' => $party ?: $org,
                'entity' => $party ?: $org,
                'report_type' => null,
                'is_correction' => $isCorrection,
                'confidence' => 0.88,
            ];
        }

        // 9. Situations / Approvals
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
                'direction' => null,
                'action_type' => null,
                'amount' => null,
                'currency' => null,
                'date_filter' => null,
                'entity_type' => null,
                'entity_value' => '',
                'entity' => '',
                'report_type' => null,
                'is_correction' => false,
                'confidence' => 0.85,
            ];
        }

        // 10. General Entity Search fallback
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
            'direction' => null,
            'action_type' => null,
            'amount' => null,
            'currency' => null,
            'date_filter' => null,
            'entity_type' => !empty($entity) ? 'search_query' : null,
            'entity_value' => $entity,
            'entity' => $entity,
            'report_type' => null,
            'is_correction' => false,
            'confidence' => 0.70,
        ];
    }
}
