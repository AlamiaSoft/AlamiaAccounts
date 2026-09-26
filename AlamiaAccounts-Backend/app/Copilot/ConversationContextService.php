<?php

namespace App\Copilot;

class ConversationContextService
{
    /**
     * Extract structured conversational state from chat history.
     */
    public function extractState(array $history): array
    {
        $state = [
            'active_subject' => null,
            'active_party' => null,
            'active_voucher' => null,
            'active_account' => null,
            'last_capability' => null,
            'last_policy' => null,
            'last_card_type' => null,
            'last_query' => null,
            'turn_count_since_voucher' => 0,
        ];

        if (empty($history)) {
            return $state;
        }

        $turnsSinceVoucher = 0;
        $foundVoucher = false;

        foreach (array_reverse($history) as $turn) {
            $text = $turn['text'] ?? ($turn['message'] ?? '');
            $data = $turn['data'] ?? [];
            $cardType = $turn['card_type'] ?? '';

            // 1. Capture last capability & policy from cards
            if (empty($state['last_capability'])) {
                if (!empty($turn['intent'])) {
                    $state['last_capability'] = $turn['intent'];
                } elseif (!empty($cardType)) {
                    $state['last_capability'] = $cardType;
                }
            }

            if (empty($state['last_policy']) && (!empty($data['policy']) || $cardType === 'safety_policy')) {
                $state['last_policy'] = $data['policy'] ?? 'LEDGER_ENTRY_IMMUTABILITY';
                $state['last_card_type'] = $cardType ?: 'safety_policy';
            }

            // 2. Voucher references (e.g., OB-2026-001, SV-2026-112)
            if (empty($state['active_voucher'])) {
                if (!empty($data['reference'])) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($data['reference']),
                        'description' => $data['description'] ?? '',
                        'amount' => $data['amount'] ?? null,
                    ];
                    $foundVoucher = true;
                } elseif (!empty($data['latest_voucher']['reference'])) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($data['latest_voucher']['reference']),
                        'description' => $data['latest_voucher']['description'] ?? '',
                        'amount' => $data['latest_voucher']['amount'] ?? null,
                    ];
                    $foundVoucher = true;
                } elseif (!empty($data['voucher']['reference'])) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($data['voucher']['reference']),
                        'description' => $data['voucher']['description'] ?? '',
                        'amount' => $data['voucher']['amount'] ?? null,
                    ];
                    $foundVoucher = true;
                } elseif (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $text, $vm)) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($vm[0]),
                    ];
                    $foundVoucher = true;
                }
            }

            if (!$foundVoucher) {
                $turnsSinceVoucher++;
            }

            // 3. Parties / Organizations (e.g., IZOC, Ali Raza)
            if (empty($state['active_party'])) {
                if (!empty($data['name']) && in_array($data['type'] ?? '', ['party', 'contact'])) {
                    $state['active_party'] = [
                        'name' => $data['name'],
                        'entity_type' => $data['entity_type'] ?? 'person',
                    ];
                } elseif (preg_match('/(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+(?:ltd|pvt|inc|corp|company|ali raza|izoc))/i', $text, $pm)) {
                    $pName = trim($pm[0]);
                    $state['active_party'] = [
                        'name' => $pName,
                        'entity_type' => preg_match('/\b(ltd|pvt|inc|corp|company|izoc)\b/i', $pName) ? 'organization' : 'person',
                    ];
                }
            }

            // 4. Accounts (e.g., 1130, Meezan Bank)
            if (empty($state['active_account'])) {
                if (!empty($data['code']) && ($data['type'] ?? '') === 'account') {
                    $state['active_account'] = [
                        'code' => $data['code'],
                        'name' => $data['name'] ?? '',
                    ];
                } elseif (preg_match('/\b(\d{4})\b/', $text, $am)) {
                    $state['active_account'] = ['code' => $am[1]];
                }
            }

            // 5. Last query
            if (empty($state['last_query']) && !empty($text)) {
                $state['last_query'] = $text;
            }
        }

        $state['turn_count_since_voucher'] = $turnsSinceVoucher;

        return $state;
    }

    /**
     * Enforce explicit lifecycle expiry and domain-switch invalidation rules.
     */
    public function applyExpiryRules(array &$state, string $prompt): void
    {
        $promptLower = strtolower(trim($prompt));

        // If prompt explicitly mentions a voucher reference, update active_voucher
        if (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $promptLower, $vm)) {
            $state['active_voucher'] = [
                'reference' => strtoupper($vm[0]),
            ];
            $state['turn_count_since_voucher'] = 0;
            return;
        }

        // Rule 1: Clear active voucher/policy if prompt explicitly addresses a new account or organization
        $cleanedForAccount = preg_replace('/\b(19|20)\d{2}\b/', '', $promptLower);
        $isAccountSwitch = (bool) preg_match('/\b(meezan|alfalah|hbl|mcb|ubl|cash in hand|bank account|chart of accounts|\b\d{4}\b)\b/i', $cleanedForAccount);
        $isNewEntitySwitch = (bool) preg_match('/\b(who is dog|dog pvt|tell me about dog|who is ali|who is [a-z0-9\s]+(?:ltd|pvt|inc|corp))\b/i', $promptLower);
        $isReportQuery = (bool) preg_match('/\b(trial balance|profit and loss|profit & loss|balance sheet|income statement|general help|situations)\b/i', $promptLower);

        if ($isAccountSwitch || $isNewEntitySwitch || $isReportQuery) {
            $state['active_voucher'] = null;
            $state['last_policy'] = null;
            $state['last_card_type'] = null;
        }

        // Rule 2: Turn Decay - Expire ephemeral safety remediation context if > 2 turns have elapsed without voucher reference
        if (($state['turn_count_since_voucher'] ?? 0) > 2) {
            $state['active_voucher'] = null;
            $state['last_policy'] = null;
            $state['last_card_type'] = null;
        }
    }

    /**
     * Deterministically extract and validate numeric amounts from text, detecting ambiguities.
     */
    public function extractValidatedAmount(string $prompt): array
    {
        $promptTrimmed = trim($prompt);

        // Strip dates (e.g. 15 March 2026, 15 Mar 2026)
        $cleaned = preg_replace('/\b[0-9]{1,2}\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|january|february|march|april|may|june|july|august|september|october|november|december)(?:\s+[0-9]{4})?\b/i', '', $promptTrimmed);
        // Strip voucher codes (e.g. SV-2026-112)
        $cleaned = preg_replace('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', '', $cleaned);
        // Strip negated / original amounts (e.g. "never got 100,000", "originally 100k", "was 100,000")
        $cleaned = preg_replace('/\b(?:never\s+got|originally|was|not)\s*(?:rs\.?|pkr|\$)?\s*[0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?\b/i', '', $cleaned);
        // Strip 4-digit years (1980-2030)
        $cleaned = preg_replace('/\b(19[89][0-9]|20[0-2][0-9]|2030)\b/', '', $cleaned);

        // Find explicit target amount patterns first (e.g., "correct amount is 50,000", "should be 50,000")
        $explicitMatches = [];
        if (preg_match_all('/(?:correct\s+(?:amount\s+)?(?:.*?)\s+is|correct\s+amount\s+is|amount\s+should\s+be|amount\s+is|change\s+to|fix\s+to|is\s+actually)\s*(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $cleaned, $targetMatches)) {
            foreach ($targetMatches[1] as $m) {
                $val = (float) str_replace(',', '', $m);
                if ($val > 0) {
                    $explicitMatches[] = $val;
                }
            }
        }

        // Find all remaining numeric candidate amounts in cleaned string
        $allCandidates = [];
        if (preg_match_all('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $cleaned, $candMatches)) {
            foreach ($candMatches[1] as $c) {
                $val = (float) str_replace(',', '', $c);
                if ($val > 0) {
                    $allCandidates[] = $val;
                }
            }
        }

        $allCandidates = array_values(array_unique($allCandidates));

        // If multiple distinct candidate numbers appear (e.g., "50,000" and "40,000"), it is ambiguous!
        if (count($allCandidates) > 1) {
            return [
                'amount' => null,
                'is_ambiguous' => true,
                'candidate_count' => count($allCandidates),
                'candidates' => $allCandidates,
                'confidence' => 0.60,
            ];
        }

        if (count($allCandidates) === 1) {
            return [
                'amount' => $allCandidates[0],
                'is_ambiguous' => false,
                'candidate_count' => 1,
                'candidates' => $allCandidates,
                'confidence' => 0.95,
            ];
        }

        return [
            'amount' => null,
            'is_ambiguous' => false,
            'candidate_count' => 0,
            'candidates' => [],
            'confidence' => 0.0,
        ];
    }

    /**
     * Resolve conversational references (pronouns, deictic phrases, follow-ups) using active state.
     */
    public function resolveReferences(array $semantic, array $state, string $prompt): array
    {
        // Apply lifecycle rules before reference resolution
        $this->applyExpiryRules($state, $prompt);

        $args = $semantic['arguments'] ?? [];
        $party = $args['party'] ?? ($semantic['party'] ?? '');
        $org = $args['organization'] ?? ($semantic['organization'] ?? '');
        $ref = $args['reference'] ?? ($semantic['reference'] ?? '');

        $promptTrimmed = trim($prompt);
        $promptLower = strtolower($promptTrimmed);

        // 1. Procedural Repair & Guided Correction Follow-Up
        // e.g., "but we never got 100,000, correct amount is 50,000; how do i fix that?", "how do I fix that?", "how to correct this?"
        $isProceduralRepair = preg_match('/\b(how\s+(?:do\s+i|can\s+i|to|should\s+i)\s+(?:fix|correct|reverse|adjust|change)|how\s+to\s+fix|how\s+to\s+correct|how\s+to\s+reverse|ok\s+how|yes\s+how|how\s+do\s+we\s+fix|what\s+to\s+do\s+now)\b/i', $promptLower) ||
            preg_match('/\b(?:correct\s+amount\s+is|amount\s+is|should\s+be)\s+[0-9,.]+\s*(?:;|,)?\s*(?:how\s+do\s+i\s+fix|how\s+to\s+fix|how\s+to\s+correct)/i', $promptLower);

        if ($isProceduralRepair && !empty($state['active_voucher']['reference'])) {
            $vRef = $state['active_voucher']['reference'];
            
            // Extract target amount deterministically with ambiguity detection
            $amtAnalysis = $this->extractValidatedAmount($promptTrimmed);
            $targetAmount = $amtAnalysis['amount'];
            $isAmbiguous = $amtAnalysis['is_ambiguous'];

            if ($targetAmount !== null && $targetAmount > 0 && !$isAmbiguous) {
                $semantic['capability'] = 'voucher.correct_amount';
                $semantic['intent'] = 'VOUCHER_ACTION';
                $semantic['action'] = 'correct_amount';
                $semantic['reference'] = $vRef;
                $semantic['amount'] = $targetAmount;
                $semantic['is_ambiguous_amount'] = false;
                $semantic['arguments']['reference'] = $vRef;
                $semantic['arguments']['amount'] = $targetAmount;
                $semantic['entity_value'] = $vRef;
                $semantic['entity'] = $vRef;
                $semantic['confidence'] = 0.95;
                $semantic['safety_flag'] = null; // Routed into safe guided workflow
                return $semantic;
            } elseif ($isAmbiguous) {
                $semantic['capability'] = 'voucher.correct_amount';
                $semantic['intent'] = 'VOUCHER_ACTION';
                $semantic['action'] = 'correct_amount';
                $semantic['reference'] = $vRef;
                $semantic['amount'] = null;
                $semantic['is_ambiguous_amount'] = true;
                $semantic['arguments']['reference'] = $vRef;
                $semantic['arguments']['amount'] = null;
                $semantic['entity_value'] = $vRef;
                $semantic['entity'] = $vRef;
                $semantic['confidence'] = 0.85;
                $semantic['safety_flag'] = null;
                return $semantic;
            } elseif (in_array($state['last_policy'], ['LEDGER_ENTRY_IMMUTABILITY', 'HISTORICAL_LEDGER_IMMUTABILITY', 'VOUCHER_DESCRIPTION_IMMUTABILITY']) || $state['last_card_type'] === 'safety_policy') {
                $semantic['capability'] = 'voucher.reverse';
                $semantic['intent'] = 'VOUCHER_ACTION';
                $semantic['action'] = 'reverse_voucher';
                $semantic['reference'] = $vRef;
                $semantic['arguments']['reference'] = $vRef;
                $semantic['entity_value'] = $vRef;
                $semantic['entity'] = $vRef;
                $semantic['confidence'] = 0.95;
                $semantic['safety_flag'] = null;
                return $semantic;
            }
        }


        // 2. Resolve party / organization pronouns (e.g., "who is she?", "who made that payment?", "what about that company?")
        $isPronoun = in_array(strtolower($party), ['he', 'she', 'him', 'her', 'it', 'them', 'this', 'that', 'this company', 'that company', 'this person']) ||
            in_array(strtolower($org), ['he', 'she', 'him', 'her', 'it', 'them', 'this', 'that', 'this company', 'that company']) ||
            (empty($party) && empty($org) && preg_match('/\b(who is (?:he|she|this|that|them|it|this person|that person|this company|that company)|who made that payment|who paid that|about that company|about that client)\b/i', $promptLower));

        if ($isPronoun && !empty($state['active_party'])) {
            $resolvedName = $state['active_party']['name'] ?? '';
            $resolvedType = $state['active_party']['entity_type'] ?? 'person';
            if ($resolvedType === 'organization') {
                $semantic['organization'] = $resolvedName;
                $semantic['arguments']['organization'] = $resolvedName;
                $semantic['party'] = '';
                $semantic['arguments']['party'] = '';
                $semantic['entity_type'] = 'organization';
            } else {
                $semantic['party'] = $resolvedName;
                $semantic['arguments']['party'] = $resolvedName;
                $semantic['entity_type'] = 'person';
            }
            $semantic['entity_value'] = $resolvedName;
            $semantic['entity'] = $resolvedName;
        }

        // 3. Resolve voucher reference for actions or follow-ups (e.g., "change the narration", "reverse it", "who worked on this voucher?", "who created it?")
        if (empty($ref) && !empty($state['active_voucher']['reference'])) {
            $isVoucherReferencePhrase = preg_match('/\b(the narration|the description|the voucher|that voucher|this voucher|the transaction|that transaction|this transaction|that payment|this payment|who worked on this|who worked on that|who created this|who created that|who entered this|who entered that|who posted this|who posted that|reverse it|void it|cancel it|its voucher|its details|its narration)\b/i', $promptLower);
            if ($isVoucherReferencePhrase) {
                $vRef = $state['active_voucher']['reference'];
                $semantic['reference'] = $vRef;
                $semantic['arguments']['reference'] = $vRef;
                $semantic['entity_value'] = $vRef;
                $semantic['entity'] = $vRef;
                if ($semantic['capability'] === 'unknown' || $semantic['capability'] === 'transaction.search') {
                    $semantic['capability'] = 'voucher.lookup';
                }
                if (preg_match('/\b(who worked|who created|who entered|who posted)\b/i', $promptLower)) {
                    $semantic['requested_information'] = array_unique(array_merge($semantic['requested_information'] ?? [], ['created_by', 'workforce']));
                    $semantic['capability'] = 'voucher.lookup';
                }
            }
        }

        return $semantic;
    }
}
