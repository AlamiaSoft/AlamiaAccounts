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
            'last_query' => null,
        ];

        if (empty($history)) {
            return $state;
        }

        foreach (array_reverse($history) as $turn) {
            $text = $turn['text'] ?? '';
            $data = $turn['data'] ?? [];

            // 1. Voucher references (e.g., OB-2026-001, SV-2026-112)
            if (empty($state['active_voucher'])) {
                if (!empty($data['reference'])) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($data['reference']),
                        'description' => $data['description'] ?? '',
                    ];
                } elseif (!empty($data['latest_voucher']['reference'])) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($data['latest_voucher']['reference']),
                        'description' => $data['latest_voucher']['description'] ?? '',
                    ];
                } elseif (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $text, $vm)) {
                    $state['active_voucher'] = [
                        'reference' => strtoupper($vm[0]),
                    ];
                }
            }

            // 2. Parties / Organizations (e.g., IZOC, Ali Raza)
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

            // 3. Accounts (e.g., 1130, Meezan Bank)
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

            // 4. Last query
            if (empty($state['last_query']) && !empty($text)) {
                $state['last_query'] = $text;
            }
        }

        return $state;
    }

    /**
     * Resolve conversational references (pronouns, deictic phrases, follow-ups) using active state.
     */
    public function resolveReferences(array $semantic, array $state, string $prompt): array
    {
        $args = $semantic['arguments'] ?? [];
        $party = $args['party'] ?? ($semantic['party'] ?? '');
        $org = $args['organization'] ?? ($semantic['organization'] ?? '');
        $ref = $args['reference'] ?? ($semantic['reference'] ?? '');

        $promptLower = strtolower(trim($prompt));

        // 1. Resolve party / organization pronouns (e.g., "who is she?", "who made that payment?", "what about that company?")
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

        // 2. Resolve voucher reference for actions or follow-ups (e.g., "change the narration", "reverse it", "who entered that voucher?")
        if (empty($ref) && !empty($state['active_voucher']['reference'])) {
            $isVoucherReferencePhrase = preg_match('/\b(the narration|the description|the voucher|that voucher|that payment|that transaction|reverse it|void it|cancel it)\b/i', $promptLower);
            if ($isVoucherReferencePhrase) {
                $vRef = $state['active_voucher']['reference'];
                $semantic['reference'] = $vRef;
                $semantic['arguments']['reference'] = $vRef;
                $semantic['entity_value'] = $vRef;
                $semantic['entity'] = $vRef;
            }
        }

        return $semantic;
    }
}
