<?php

namespace App\Copilot;

use AlamiaSoft\AlamiaAccounts\Models\CopilotDiagnosticLog;
use AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry;
use Illuminate\Support\Facades\Log;

class CopilotDiagnosticsService
{
    /**
     * Record a copilot interaction trace.
     */
    public function recordTrace(array $data): ?CopilotDiagnosticLog
    {
        try {
            return CopilotDiagnosticLog::create([
                'session_id' => $data['session_id'] ?? null,
                'company_code' => $data['company_code'] ?? null,
                'user_id' => $data['user_id'] ?? (auth()->check() ? (string) auth()->id() : 'anonymous'),
                'user_name' => $data['user_name'] ?? (auth()->check() ? auth()->user()->name : 'User'),
                'prompt' => $data['prompt'] ?? '',
                'classifier_mode' => $data['classifier_mode'] ?? 'heuristic',
                'classifier_intent' => $data['classifier_intent'] ?? null,
                'classifier_confidence' => $data['classifier_confidence'] ?? null,
                'classifier_output' => $data['classifier_output'] ?? null,
                'context_before' => $data['context_before'] ?? null,
                'context_after' => $data['context_after'] ?? null,
                'safety_evaluations' => $data['safety_evaluations'] ?? null,
                'dispatched_action' => $data['dispatched_action'] ?? null,
                'execution_result' => $data['execution_result'] ?? null,
                'final_response' => $data['final_response'] ?? null,
                'duration_ms' => $data['duration_ms'] ?? null,
                'status' => 'unreviewed',
                'developer_notes' => null,
                'entry_point' => $data['entry_point'] ?? null,
                'anomaly_flag' => $data['anomaly_flag'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to record Copilot diagnostic trace: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * List diagnostic traces with filters.
     */
    public function listTraces(array $filters = [], int $perPage = 25)
    {
        $query = CopilotDiagnosticLog::query()->orderBy('id', 'desc');

        if (!empty($filters['company_code'])) {
            $query->where('company_code', $filters['company_code']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['classifier_mode'])) {
            $query->where('classifier_mode', $filters['classifier_mode']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('prompt', 'like', "%{$search}%")
                  ->orWhere('classifier_intent', 'like', "%{$search}%")
                  ->orWhere('dispatched_action', 'like', "%{$search}%")
                  ->orWhere('developer_notes', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (!empty($filters['anomaly_flag'])) {
            if ($filters['anomaly_flag'] === 'any') {
                $query->whereNotNull('anomaly_flag');
            } else {
                $query->where('anomaly_flag', $filters['anomaly_flag']);
            }
        }

        if (!empty($filters['entry_point'])) {
            $query->where('entry_point', $filters['entry_point']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get aggregate telemetry stats for the dashboard.
     */
    public function getStats(?string $companyCode = null): array
    {
        $base = CopilotDiagnosticLog::query();
        if ($companyCode) {
            $base->where('company_code', $companyCode);
        }

        $total = (clone $base)->count();
        $verified = (clone $base)->where('status', 'verified')->count();
        $needsFix = (clone $base)->where('status', 'needs_fix')->count();
        $promoted = (clone $base)->where('status', 'promoted_to_kb')->count();
        $unreviewed = (clone $base)->where('status', 'unreviewed')
            ->where(function ($q) {
                $q->whereNull('entry_point')
                  ->orWhereNotIn('entry_point', ['deep_link', 'pre_resolved']);
            })->count();

        $avgLatency = (clone $base)->whereNotNull('duration_ms')->avg('duration_ms') ?? 0;

        $modes = (clone $base)
            ->selectRaw('classifier_mode, COUNT(*) as count')
            ->groupBy('classifier_mode')
            ->pluck('count', 'classifier_mode')
            ->toArray();

        $kbCount = CopilotKnowledgeEntry::where('is_active', true)->count();
        $anomalyCount = (clone $base)->whereNotNull('anomaly_flag')->count();

        return [
            'total_traces' => $total,
            'verified_count' => $verified,
            'needs_fix_count' => $needsFix,
            'promoted_to_kb_count' => $promoted,
            'unreviewed_count' => $unreviewed,
            'avg_duration_ms' => round($avgLatency, 1),
            'mode_breakdown' => $modes,
            'active_knowledge_entries' => $kbCount,
            'anomaly_count' => $anomalyCount,
        ];
    }

    /**
     * Get a single trace with related knowledge entries.
     */
    public function getTrace(int $id): ?CopilotDiagnosticLog
    {
        return CopilotDiagnosticLog::with('knowledgeEntries')->find($id);
    }

    /**
     * Update developer review status and notes.
     */
    public function updateFeedback(int $id, string $status, ?string $developerNotes = null): CopilotDiagnosticLog
    {
        $trace = CopilotDiagnosticLog::findOrFail($id);
        $trace->status = $status;
        if ($developerNotes !== null) {
            $trace->developer_notes = $developerNotes;
        }
        $trace->save();

        return $trace;
    }

    /**
     * Promote a diagnostic trace into a persistent knowledgebase entry for self-learning.
     * Includes governance: trigger validation, GAAP invariant check, and audit trail.
     */
    public function promoteToKnowledge(int $id, array $payload): CopilotKnowledgeEntry
    {
        $trace = CopilotDiagnosticLog::findOrFail($id);

        // Governance Gate 1: Block promotion of deep-link/pre-resolved traces
        if (in_array($trace->entry_point, ['deep_link', 'pre_resolved'], true)) {
            throw new \InvalidArgumentException('Deep-link and pre-resolved traces cannot be promoted to the knowledge base. These paths are already handled deterministically.');
        }

        // Governance Gate 2: Trigger keyword specificity floor (minimum 2 tokens)
        $triggers = $payload['trigger_keywords'] ?? [$trace->prompt];
        foreach ($triggers as $trigger) {
            $tokenCount = count(array_filter(explode(' ', trim($trigger))));
            if ($tokenCount < 2) {
                throw new \InvalidArgumentException("Trigger keyword \"{$trigger}\" is too generic (requires at least 2 tokens). Single-word triggers risk shadowing primary classifier capabilities.");
            }
        }

        // Governance Gate 3: GAAP/IFRS invariant contradiction check
        $dangerousPatterns = [
            '/\b(delete|remove|purge|erase)\s+(voucher|journal|entry|transaction|ledger)/i',
            '/\b(post|book)\s+(?:to|into|on)\s+(?:a\s+)?(?:category|folder|group)\s+account/i',
            '/\b(bypass|skip|disable|ignore)\s+(?:maker.?checker|dual.?authorization|approval|reversal|audit)/i',
        ];
        $textToCheck = ($payload['summary'] ?? '') . ' ' . implode(' ', $payload['steps'] ?? []) . ' ' . ($payload['note'] ?? '');
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $textToCheck)) {
                throw new \InvalidArgumentException('Promotion blocked: guidance content appears to contradict GAAP/IFRS institutional invariants (e.g., voucher deletion, category posting, or bypassing maker-checker). Review and rephrase before promoting.');
            }
        }

        $entry = CopilotKnowledgeEntry::create([
            'company_code' => $payload['company_code'] ?? $trace->company_code,
            'topic' => $payload['topic'] ?? ($trace->classifier_intent ?? 'custom_guidance'),
            'trigger_keywords' => $triggers,
            'domain' => $payload['domain'] ?? 'general',
            'title' => $payload['title'] ?? 'Operational Guidance',
            'summary' => $payload['summary'] ?? ($trace->final_response['message'] ?? $trace->prompt),
            'steps' => $payload['steps'] ?? [],
            'note' => $payload['note'] ?? null,
            'actions' => $payload['actions'] ?? [],
            'source_diagnostic_id' => $trace->id,
            'is_active' => true,
            'promoted_by' => auth()->check() ? auth()->user()->name : 'system',
            'promoted_at' => now(),
            'change_reason' => $payload['change_reason'] ?? 'Promoted from diagnostic trace #' . $trace->id,
            'version' => 1,
        ]);

        $trace->status = 'promoted_to_kb';
        $trace->save();

        return $entry;
    }

    /**
     * Export diagnostic dataset formatted for AI agent review and regression tests.
     */
    public function exportTraces(array $filters = [], string $format = 'jsonl'): array|string
    {
        $query = CopilotDiagnosticLog::query()->orderBy('id', 'asc');

        if (!empty($filters['company_code'])) {
            $query->where('company_code', $filters['company_code']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['classifier_mode'])) {
            $query->where('classifier_mode', $filters['classifier_mode']);
        }

        $records = $query->limit(5000)->get();

        if ($format === 'json') {
            return $records->toArray();
        }

        if ($format === 'csv') {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['ID', 'Timestamp', 'Company', 'Prompt', 'Mode', 'Intent', 'Confidence', 'Latency_ms', 'Status', 'Dispatched_Action', 'Developer_Notes']);
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->id,
                    $r->created_at?->toIso8601String(),
                    $r->company_code,
                    $r->prompt,
                    $r->classifier_mode,
                    $r->classifier_intent,
                    $r->classifier_confidence,
                    $r->duration_ms,
                    $r->status,
                    $r->dispatched_action,
                    $r->developer_notes,
                ]);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            return $csv;
        }

        // Default JSON Lines format (1 JSON per line)
        $lines = [];
        foreach ($records as $r) {
            $item = [
                'id' => $r->id,
                'timestamp' => $r->created_at?->toIso8601String(),
                'company_code' => $r->company_code,
                'session_id' => $r->session_id,
                'user_prompt' => $r->prompt,
                'context_state' => $r->context_before,
                'classifier' => [
                    'mode' => $r->classifier_mode,
                    'intent' => $r->classifier_intent,
                    'confidence' => $r->classifier_confidence,
                    'arguments' => $r->classifier_output['arguments'] ?? [],
                    'safety_flag' => $r->classifier_output['safety_flag'] ?? null,
                ],
                'safety_evaluations' => $r->safety_evaluations,
                'dispatched_action' => $r->dispatched_action,
                'duration_ms' => $r->duration_ms,
                'final_output' => [
                    'message' => $r->final_response['message'] ?? null,
                    'card_type' => $r->final_response['card_type'] ?? null,
                    'actions' => $r->final_response['actions'] ?? [],
                    'data' => $r->final_response['data'] ?? null,
                ],
                'evaluation' => [
                    'status' => $r->status,
                    'developer_notes' => $r->developer_notes,
                ],
            ];
            $lines[] = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
    }

    /**
     * Prune logs older than a given date or for a specific company.
     */
    public function pruneLogs(?string $beforeDate = null, ?string $companyCode = null): int
    {
        $query = CopilotDiagnosticLog::query();
        if ($beforeDate) {
            $query->where('created_at', '<', $beforeDate);
        }
        if ($companyCode) {
            $query->where('company_code', $companyCode);
        }

        return $query->delete();
    }
}
