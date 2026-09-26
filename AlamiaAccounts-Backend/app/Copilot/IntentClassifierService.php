<?php

namespace App\Copilot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentClassifierService
{
    /**
     * Classify user prompt into a structured semantic capability request.
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
You are a semantic capability parser for Alamia Accounts double-entry ERP.
Analyze the user query inside <user_query>...</user_query> and return JSON ONLY matching this schema:
{
  "capability": "account.balance" | "account.lookup" | "party.lookup" | "transaction.search" | "voucher.lookup" | "voucher.draft" | "voucher.reverse" | "report.trial_balance" | "report.profit_loss" | "report.balance_sheet" | "alerts.list" | "general.help" | "general.greeting" | "unknown",
  "arguments": {
    "account": "extracted account name or code (e.g. 'Meezan Bank', '1130') or empty string",
    "party": "extracted person contact name (e.g. 'Ali Raza') or empty string (stripped of 'this'/'that')",
    "organization": "extracted organization / vendor / client name (e.g. 'IZOC', 'Dog Pvt Ltd') or empty string (stripped of 'this'/'that')",
    "reference": "extracted voucher reference (e.g. 'OB-2026-001') or empty string",
    "amount": float or null,
    "direction": "incoming" | "outgoing" | "any" | null,
    "date_expression": "extracted natural date expression (e.g. '15 March', 'last month', 'yesterday') or empty string"
  },
  "requested_information": ["reason" | "created_by" | "date" | "amount" | "account" | "balance"],
  "safety_flag": "destructive_account" | "destructive_voucher" | "mutate_ledger" | "mutate_narration" | null
}

Domain Capabilities:
1. party.lookup: Inquiring about a person, contact, vendor, or organization identity (e.g., "Who is Ali Raza?", "Who is this IZOC???", "Tell me about IZOC").
2. transaction.search: Inquiring about historical transactions or reasons (e.g., "Transaction with Mr. Ali Raza of Izoc Ltd", "Why did we pay Dog Pvt Ltd PKR 5,000?", "Show what we have with IZOC", "What did we pay Ali?").
3. account.balance / account.lookup: Inquiring about ledger accounts and balances (e.g., "What is the balance of Meezan Bank?", "Account 1130", "Cash in Hand").
4. voucher.lookup: Inspecting a specific voucher (e.g., "Tell me about voucher OB-2026-001", "Show SV-2026-112", "What is the narration of OB-2026-001?").
5. voucher.draft: Drafting a new transaction with amount (e.g., "Paid Rs. 25,000 for office supplies via Meezan Bank", "Create payment of Rs. 10,000 to Ali Raza").
6. voucher.reverse: Reversing an accounting voucher (e.g., "reverse OB-2026-001", "void JV-102").
7. report.trial_balance / report.profit_loss / report.balance_sheet: Financial statement reports.
8. alerts.list: Operational alerts, anomalies, or pending approvals.
9. Safety Flags:
   - "destructive_account": Requests to delete or purge accounts (e.g., "delete all accounts").
   - "destructive_voucher": Requests to delete posted ledger vouchers (e.g., "delete voucher OB-2026-001").
   - "mutate_ledger": Requests to mutate posted transaction amounts in place (e.g., "change voucher amount to 500k").
   - "mutate_narration": Requests to delete or modify posted narration (e.g., "delete the wrong narration in OB-2026-001", "change narration of OB-2026-001").

{$contextSnippet}<user_query>
{$prompt}
</user_query>
PROMPT;

            $request = Http::timeout($timeout);
            if (!empty($apiKey)) {
                $request = $request->withToken($apiKey);
            }

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
                    if (is_array($parsed) && !empty($parsed['capability'])) {
                        return $this->normalizeSemanticOutput($parsed, 'llm', $model);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("IntentClassifierService LLM classification failed/timed out: " . $e->getMessage());
        }

        return $this->heuristicFallback($prompt, $context);
    }

    /**
     * Normalize parsed semantic array into standard response format.
     */
    protected function normalizeSemanticOutput(array $data, string $source = 'heuristic', ?string $model = null): array
    {
        $cap = $data['capability'] ?? 'unknown';
        $args = $data['arguments'] ?? ($data['entities'] ?? []);
        $filters = $data['filters'] ?? [];
        $safetyFlag = $data['safety_flag'] ?? null;
        $requestedInfo = $data['requested_information'] ?? [];

        $party = trim($args['party'] ?? ($data['party'] ?? ''));
        $org = trim($args['organization'] ?? ($data['organization'] ?? ''));
        $ref = trim($args['reference'] ?? ($data['reference'] ?? ''));
        $acc = trim($args['account'] ?? ($data['account'] ?? ''));
        $amt = isset($args['amount']) && is_numeric($args['amount']) ? (float) $args['amount'] : (isset($filters['amount']) && is_numeric($filters['amount']) ? (float) $filters['amount'] : null);
        $dir = $args['direction'] ?? ($filters['direction'] ?? null);
        $dateExpr = $args['date_expression'] ?? ($filters['date_expression'] ?? '');

        // Clean deictic words from entity names
        $party = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $party));
        $org = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $org));

        $isOrgCandidate = (!empty($org) && empty($party)) ||
            preg_match('/\b(ltd|limited|inc|corp|pvt|co|company|technologies|solutions|services|group|holdings)\b/i', $party . ' ' . $org) ||
            (ctype_upper($party) && strlen($party) <= 6);

        if ($isOrgCandidate && (!empty($party) || !empty($org))) {
            $org = $org ?: $party;
            $party = '';
            $entityType = 'organization';
        } else {
            $entityType = !empty($acc) ? 'account' : (!empty($ref) ? 'reference' : (!empty($party) ? 'person' : (!empty($org) ? 'organization' : null)));
        }

        // Infer primary intent name for backward compatibility with existing tests
        $intent = match ($cap) {
            'general.greeting' => 'GREETING',
            'general.help' => 'HELP',
            'alerts.list' => 'LIST_SITUATIONS',
            'report.trial_balance', 'report.profit_loss', 'report.balance_sheet' => 'INQUIRE_REPORT',
            'voucher.draft' => 'DRAFT_VOUCHER',
            'voucher.reverse', 'voucher.action' => 'VOUCHER_ACTION',
            'voucher.lookup' => 'INQUIRE_VOUCHER',
            'account.balance', 'account.lookup', 'account.ledger' => 'INQUIRE_ACCOUNT',
            'party.lookup' => 'INQUIRE_ENTITY',
            'transaction.search' => 'FIND_TRANSACTION',
            default => !empty($safetyFlag) ? 'RESTRICTED_ACTION' : 'GENERAL_SEARCH',
        };

        if ($safetyFlag === 'mutate_narration') {
            $intent = 'VOUCHER_ACTION';
        } elseif (!empty($safetyFlag)) {
            $intent = 'RESTRICTED_ACTION';
        }

        $entityValue = !empty($acc) ? $acc : (!empty($ref) ? $ref : (!empty($party) ? $party : $org));

        $action = match ($safetyFlag) {
            'mutate_narration' => 'edit_narration',
            'destructive_account' => 'delete_account',
            'destructive_voucher' => 'delete_voucher',
            'mutate_ledger' => 'modify_amount',
            default => ($cap === 'voucher.reverse' ? 'reverse_voucher' : null),
        };

        return [
            'success' => true,
            'source' => $source,
            'model' => $model,
            'capability' => $cap,
            'intent' => $intent,
            'arguments' => [
                'party' => $party,
                'organization' => $org,
                'account' => $acc,
                'reference' => strtoupper($ref),
                'amount' => $amt,
                'direction' => $dir,
                'date_expression' => $dateExpr,
            ],
            'requested_information' => $requestedInfo,
            'party' => $party,
            'organization' => $org,
            'entity_type' => $entityType,
            'entity_value' => $entityValue,
            'entity' => $entityValue,
            'target_object' => !empty($acc) ? 'account' : (!empty($ref) ? 'voucher' : (!empty($party) || !empty($org) ? 'contact' : null)),
            'reference' => strtoupper($ref),
            'account' => $acc,
            'action' => $action,
            'safety_flag' => $safetyFlag,
            'direction' => $dir,
            'action_type' => $cap === 'voucher.draft' ? ($dir === 'incoming' ? 'receipt' : 'payment') : null,
            'amount' => $amt,
            'currency' => 'PKR',
            'date_filter' => null,
            'report_type' => match ($cap) {
                'report.trial_balance' => 'trial-balance',
                'report.profit_loss' => 'profit-loss',
                'report.balance_sheet' => 'balance-sheet',
                default => null,
            },
            'confidence' => 0.95,
        ];
    }

    /**
     * Minimal, bounded heuristic fallback for offline or basic environments.
     *
     * @param string $prompt
     * @param array $context
     * @return array
     */
    public function heuristicFallback(string $prompt, array $context = []): array
    {
        $promptTrimmed = trim($prompt);
        $promptLower = strtolower($promptTrimmed);

        // 1. Safety Guardrails (Immutability & COA Protection)
        if (preg_match('/\b(delete|remove|change|modify|correct|clear|erase)\s+(?:the\s+)?(?:wrong\s+)?(?:narration|description|memo|note)\b/i', $promptTrimmed)) {
            preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $vm);
            return $this->normalizeSemanticOutput([
                'capability' => 'voucher.action',
                'arguments' => ['reference' => $vm[0] ?? ''],
                'safety_flag' => 'mutate_narration',
            ]);
        }

        if (preg_match('/\b(reverse|void|cancel)\s+(?:the\s+)?(?:voucher\s+)?([a-z0-9-]+)\b/i', $promptTrimmed, $revMatch)) {
            return $this->normalizeSemanticOutput([
                'capability' => 'voucher.reverse',
                'arguments' => ['reference' => $revMatch[2]],
            ]);
        }

        if (preg_match('/\b(last century|century ago|18[0-9]{2}|19[0-9]{2}|200 years ago|millennium)\b/i', $promptLower)) {
            return $this->normalizeSemanticOutput([
                'capability' => 'unknown',
                'safety_flag' => 'implausible_temporal_request',
            ]);
        }

        if (
            preg_match('/\b(delete|remove|purge|erase|drop|wipe|destroy)\s+(?:all\s+)?(accounts?|chart\s+of\s+accounts?|ledger|vouchers?|database|company|entries|data)\b/i', $promptTrimmed) ||
            preg_match('/\b(delete|remove|purge|erase|drop)\s+(?:the\s+)?account\s+(\d{4}|[a-z0-9\s]+)/i', $promptTrimmed) ||
            preg_match('/\b(delete|remove|purge|erase|drop)\s+(?:the\s+)?voucher\s+([a-z0-9-]+)/i', $promptTrimmed, $delMatch) ||
            preg_match('/\b(change|modify|update|edit|alter)\s+(?:the\s+)?(?:voucher\s+)?(?:amount|total|lines?)\b/i', $promptTrimmed)
        ) {
            $isMutation = (bool) preg_match('/\b(change|modify|update|edit|alter)\s+(?:the\s+)?(?:voucher\s+)?(?:amount|total|lines?)\b/i', $promptTrimmed);
            $isAcc = str_contains($promptLower, 'account') || str_contains($promptLower, 'chart');
            return $this->normalizeSemanticOutput([
                'capability' => 'unknown',
                'arguments' => ['reference' => $delMatch[2] ?? ''],
                'safety_flag' => $isMutation ? 'mutate_ledger' : ($isAcc ? 'destructive_account' : 'destructive_voucher'),
            ]);
        }

        // 2. Greetings & Help
        if (preg_match('/^(hi|hello|hey|greetings|good morning|good afternoon|good evening|salam|assalam)([\s!,.].*)?$/i', $promptTrimmed)) {
            return $this->normalizeSemanticOutput(['capability' => 'general.greeting']);
        }
        if (preg_match('/^(help|who are you|who is taliya|who taliya|what is taliya|who made you|about taliya|about you|what can you do|how to use|commands|features|\?)[\s?!.]*$/i', $promptTrimmed) || preg_match('/\b(who is taliya|who taliya|what is taliya)\b/i', $promptTrimmed)) {
            return $this->normalizeSemanticOutput(['capability' => 'general.help']);
        }

        // 3. Financial Statements & Reports
        if (str_contains($promptLower, 'trial balance') || str_contains($promptLower, 'tb')) {
            return $this->normalizeSemanticOutput(['capability' => 'report.trial_balance']);
        }
        if (str_contains($promptLower, 'profit') || str_contains($promptLower, 'loss') || str_contains($promptLower, 'p&l')) {
            return $this->normalizeSemanticOutput(['capability' => 'report.profit_loss']);
        }
        if (str_contains($promptLower, 'balance sheet')) {
            return $this->normalizeSemanticOutput(['capability' => 'report.balance_sheet']);
        }

        // 4. Situations & Alerts
        if (str_contains($promptLower, 'situation') || str_contains($promptLower, 'pending') || str_contains($promptLower, 'approval')) {
            return $this->normalizeSemanticOutput(['capability' => 'alerts.list']);
        }

        // 5. Voucher Drafting
        $isQuestionInquiry = (bool) preg_match('/^(what was|what did|what is|how much was|how much did|how much is|why did|why was|why were|why we|why|did we|was there|show|find|lookup|tell me about|check|who worked|who created|who entered|who posted)\b/i', $promptTrimmed) ||
            str_ends_with($promptTrimmed, '?');
        $promptWithoutDates = preg_replace('/\b[0-9]{1,2}\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|january|february|march|april|may|june|july|august|september|october|november|december)\b/i', '', $promptTrimmed);
        if (
            !$isQuestionInquiry &&
            (str_contains($promptLower, 'paid') || str_contains($promptLower, 'pay ') || str_contains($promptLower, 'received') || str_contains($promptLower, 'transfer') || str_contains($promptLower, 'create payment')) &&
            preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $promptWithoutDates, $amtMatch)
        ) {
            return $this->normalizeSemanticOutput([
                'capability' => 'voucher.draft',
                'arguments' => ['amount' => (float) str_replace(',', '', $amtMatch[1])],
            ]);
        }

        // 6. Voucher Reference Lookup
        if (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $vm)) {
            $reqInfo = [];
            if (str_contains($promptLower, 'who worked') || str_contains($promptLower, 'who created') || str_contains($promptLower, 'who posted') || str_contains($promptLower, 'who entered')) {
                $reqInfo = ['created_by', 'workforce'];
            }
            return $this->normalizeSemanticOutput([
                'capability' => 'voucher.lookup',
                'arguments' => ['reference' => $vm[0]],
                'requested_information' => $reqInfo,
            ]);
        }

        // 7. Transaction Search (Party / Org) - MUST PRECEDE ACCOUNT INQUIRIES
        if (
            str_contains($promptLower, 'transaction') ||
            str_contains($promptLower, 'transactions') ||
            str_contains($promptLower, 'what did we pay') ||
            str_contains($promptLower, 'why we paid') ||
            str_contains($promptLower, 'why did we pay') ||
            str_contains($promptLower, 'what payment') ||
            str_contains($promptLower, 'what did') ||
            str_contains($promptLower, 'what was') ||
            str_contains($promptLower, 'who worked') ||
            str_contains($promptLower, 'who created') ||
            str_contains($promptLower, 'payment to') ||
            str_contains($promptLower, 'receipt from') ||
            str_contains($promptLower, 'show me what we have') ||
            str_contains($promptLower, 'what we have with')
        ) {
            $party = '';
            $org = '';
            if (preg_match('/(?:have\s+with|paid\s+to|payment\s+to|pay|with|for)\s+(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+?)(?:\s+of|\s+from|\s+in|\s+at|\s+make|\s+through|\?|\.|\;|\,|$)/i', $prompt, $pMatch)) {
                $cand = trim($pMatch[1]);
                $cand = trim(preg_replace('/^(?:what\s+)?(?:do\s+)?we\s+have\s+with\s+/i', '', $cand));
                if (!in_array(strtolower($cand), ['we', 'us', 'me', 'our', 'them', 'him', 'her', 'it'])) {
                    $party = $cand;
                }
            }
            if (empty($party) && preg_match('/(?:did|payment\s+did|from)\s+(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+?)(?:\s+pay|\s+make|\s+send|\s+transfer|\s+of|\s+from|\?|\.|\;|\,|$)/i', $prompt, $pMatch2)) {
                $cand = trim($pMatch2[1]);
                if (!in_array(strtolower($cand), ['we', 'us', 'me', 'our', 'them', 'him', 'her', 'it'])) {
                    $party = $cand;
                }
            }
            if (preg_match('/\b(?:of|from|at|in|company)\b\s+([a-z0-9\s]+?(?:ltd|limited|inc|corp|pvt|co)?)(?:\.|\;|\,|$|\s+i\s+need)/i', $prompt, $oMatch)) {
                $candidateOrg = trim($oMatch[1]);
                if (!in_array(strtolower($candidateOrg), ['we', 'us', 'me', 'our', 'them', 'him', 'her', 'it'])) {
                    $org = $candidateOrg;
                }
            }

            $direction = null;
            if (str_contains($promptLower, 'what did we pay') || str_contains($promptLower, 'paid to')) {
                $direction = 'outgoing';
            } elseif (str_contains($promptLower, 'pay us') || str_contains($promptLower, 'receipt from')) {
                $direction = 'incoming';
            }

            return $this->normalizeSemanticOutput([
                'capability' => 'transaction.search',
                'arguments' => [
                    'party' => $party,
                    'organization' => $org,
                    'direction' => $direction,
                ],
            ]);
        }

        // 8. Account Balances & Inquiries
        $cleanedAccount = preg_replace('/^(what is the balance of|what is the balance in|what is the balance for|what is the balance|what is in|how much is in|how much in|balance of|balance in|balance for|balance|tell me about account|tell me about|show me account|show me|details of account|details of|account)\s+(the\s+|account\s+)?/i', '', $promptTrimmed);
        $cleanedAccount = trim($cleanedAccount, " ?.\"'");
        if (
            preg_match('/^[0-9]{4}$/', $cleanedAccount) ||
            str_starts_with($promptLower, 'balance') ||
            str_contains($promptLower, 'balance of') ||
            str_starts_with($promptLower, 'account ') ||
            str_contains($promptLower, 'cash in hand') ||
            str_contains($promptLower, 'meezan')
        ) {
            return $this->normalizeSemanticOutput([
                'capability' => 'account.balance',
                'arguments' => ['account' => $cleanedAccount],
            ]);
        }

        // 9. Party & Organization Identity Inquiries
        if (
            preg_match('/^(?:what\s+or\s+who|who\s+or\s+what|who|what)\s+(?:is|are|was|were)?\s+(?:this\s+|that\s+|the\s+|a\s+|an\s+)?([a-z0-9\s.,-]+?)[\s?!.]*$/i', $promptTrimmed, $whoMatch) ||
            preg_match('/^tell\s+me\s+about\s+(?:party\s+|contact\s+|person\s+|client\s+|vendor\s+|company\s+)?([a-z0-9\s.,-]+?)[\s?!.]*$/i', $promptTrimmed, $tellMatch) ||
            preg_match('/^(?:what\s+company\s+is|what\s+firm\s+is|who\s+is)\s+([a-z0-9\s.,-]+?)\s+(?:associated\s+with|working\s+with)[\s?!.]*$/i', $promptTrimmed, $assocMatch)
        ) {
            $rawCand = !empty($whoMatch[1]) ? $whoMatch[1] : (!empty($tellMatch[1]) ? $tellMatch[1] : ($assocMatch[1] ?? ''));
            $rawCand = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $rawCand), " ?.!\"'");
            $candLower = strtolower($rawCand);

            if (in_array($candLower, ['he', 'she', 'him', 'her', 'it', 'them', 'this', 'that', 'this company', 'that company', 'this person'])) {
                if (!empty($context['history'])) {
                    foreach (array_reverse($context['history']) as $h) {
                        $htext = $h['text'] ?? '';
                        if (preg_match('/(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+(?:ltd|pvt|inc|corp|company|ali raza|izoc))/i', $htext, $hm)) {
                            $rawCand = trim($hm[0]);
                            break;
                        }
                    }
                }
            }

            return $this->normalizeSemanticOutput([
                'capability' => 'party.lookup',
                'arguments' => [
                    'party' => $rawCand,
                    'organization' => $rawCand,
                ],
            ]);
        }

        // 10. General Fallback
        $entity = preg_replace('/^(tell me about|what is|how much in|search for|search|lookup|who is|show)\s+/i', '', $promptTrimmed);
        $entity = trim($entity, " ?.\"'");

        return $this->normalizeSemanticOutput([
            'capability' => 'unknown',
            'arguments' => ['party' => $entity],
        ]);
    }
}
