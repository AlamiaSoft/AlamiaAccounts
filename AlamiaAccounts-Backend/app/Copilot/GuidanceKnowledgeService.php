<?php

namespace App\Copilot;

use AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry;
use Illuminate\Support\Facades\Log;

class GuidanceKnowledgeService
{
    /**
     * Match a procedural or operational query dynamically to standard and self-learned ERP guidance.
     * All guidance is repository-driven from database (synced from docs/copilot/standard-guidance/*.md
     * or promoted from diagnostics/UI). Zero hardcoded procedural strings in code.
     */
    public function getGuidance(string $query, ?array $context = []): ?array
    {
        $q = strtolower(trim($query));
        $companyCode = $context['company_code'] ?? null;

        try {
            if (!class_exists(CopilotKnowledgeEntry::class)) {
                return null;
            }

            // Fetch active rules: tenant-specific first (higher priority), then universal (null)
            $entries = CopilotKnowledgeEntry::where('is_active', true)
                ->where(function ($queryBuilder) use ($companyCode) {
                    $queryBuilder->whereNull('company_code');
                    if ($companyCode) {
                        $queryBuilder->orWhere('company_code', $companyCode);
                    }
                })
                ->orderByRaw('CASE WHEN company_code IS NOT NULL THEN 0 ELSE 1 END')
                ->orderBy('id', 'desc')
                ->get();

            foreach ($entries as $entry) {
                $triggers = is_array($entry->trigger_keywords) ? $entry->trigger_keywords : [];
                foreach ($triggers as $trigger) {
                    $triggerClean = strtolower(trim($trigger));
                    if ($triggerClean === '') {
                        continue;
                    }

                    // Specificity floor: skip single-word triggers that could shadow primary capabilities
                    $tokens = array_values(array_filter(explode(' ', $triggerClean)));
                    if (count($tokens) < 2) {
                        continue;
                    }

                    // Check exact phrase containment or regex match
                    $matched = str_contains($q, $triggerClean);
                    if (!$matched) {
                        $pattern = '/\b' . preg_quote($triggerClean, '/') . '\b/i';
                        $matched = (bool) @preg_match($pattern, $q);
                    }

                    // If still not matched, check if all tokens of trigger phrase appear in order
                    if (!$matched && count($tokens) >= 2) {
                        $tokenPattern = '/\b' . implode('\b.*?\b', array_map(fn($t) => preg_quote($t, '/'), $tokens)) . '\b/i';
                        $matched = (bool) @preg_match($tokenPattern, $q);
                    }

                    if ($matched) {
                        $actions = is_array($entry->actions) ? $entry->actions : [];

                        // Contextual Enrichment: If voucher_correction and active voucher is in session, enrich actions
                        if ($entry->topic === 'voucher_correction') {
                            $activeRef = $context['state']['active_voucher']['reference'] ?? '';
                            if (!empty($activeRef)) {
                                $alreadyHasView = false;
                                foreach ($actions as $act) {
                                    if (($act['payload']['id'] ?? '') === $activeRef) {
                                        $alreadyHasView = true;
                                        break;
                                    }
                                }
                                if (!$alreadyHasView) {
                                    $actions[] = [
                                        'label' => "👁️ View {$activeRef}",
                                        'action' => 'navigate_page',
                                        'payload' => ['page' => 'voucher-view', 'id' => $activeRef],
                                    ];
                                }
                            }
                        }

                        return [
                            'topic' => $entry->topic,
                            'title' => $entry->title,
                            'summary' => $entry->summary,
                            'steps' => is_array($entry->steps) ? $entry->steps : [],
                            'note' => $entry->note,
                            'actions' => $actions,
                            'is_custom_kb' => !empty($entry->source_diagnostic_id) || !empty($entry->company_code),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("GuidanceKnowledgeService error: " . $e->getMessage(), ['exception' => $e]);
        }

        // Confidence Floor: Query did not match any indexed guidance rule
        return null;
    }
}
