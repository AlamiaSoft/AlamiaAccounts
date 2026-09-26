<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Copilot\CopilotService;
use Alamia360\Facades\Alamia360;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CopilotController extends Controller
{
    protected CopilotService $copilot;

    public function __construct(CopilotService $copilot)
    {
        $this->copilot = $copilot;
    }

    /**
     * Handle conversational message to Copilot.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required_without:context.action|string|nullable',
            'company_code' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $prompt = (string) ($request->input('prompt') ?? '');
        $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');
        $context = $request->input('context') ?? [];

        try {
            $response = $this->copilot->handleChat($prompt, $companyCode, $context);
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'sender' => 'Taliya',
                'message' => 'An error occurred while executing this capability: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get registered capabilities for the Copilot.
     */
    public function capabilities(): JsonResponse
    {
        $registry = app(\Alamia360\Capabilities\CapabilityRegistry::class);
        $caps = $registry->all();
        $list = [];

        foreach ($caps as $cap) {
            $list[] = [
                'name' => $cap->name(),
                'description' => $cap->description(),
                'side_effect' => $cap->sideEffect(),
                'allowed_actor_types' => $cap->allowedActorTypes(),
                'input_schema' => $cap->inputSchema(),
                'output_schema' => $cap->outputSchema(),
            ];
        }

        return response()->json([
            'success' => true,
            'capabilities' => $list,
        ]);
    }

    /**
     * Get active operational situations.
     */
    public function situations(): JsonResponse
    {
        $situations = Alamia360::situations()->open();
        $list = [];

        foreach ($situations as $s) {
            $list[] = [
                'type' => $s->type,
                'summary' => $s->summary,
                'priority' => $s->priority->value,
                'status' => $s->status()->value,
                'subject_type' => $s->subjectType,
                'subject_id' => $s->subjectId,
                'recommended_action' => $s->recommendedAction(),
                'detected_at' => $s->detectedAt()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'situations' => $list,
            'count' => count($list),
        ]);
    }

    /**
     * Execute a registered Alamia 360 capability directly (used by sidecars, Parlant, and MCP).
     */
    public function executeCapability(Request $request, string $capability): JsonResponse
    {
        $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');
        if ($companyCode) {
            \AlamiaSoft\AlamiaAccounts\Services\DomainContext::set($companyCode);
        }

        $input = $request->input('input', $request->except(['company_code']));
        $actorId = $request->input('actor_id', 'taliya_copilot');
        
        $actor = Alamia360::actors()->find($actorId)
            ?? \Alamia360\Actors\Actor::ai($actorId, 'accounting_copilot');

        try {
            $result = Alamia360::capabilities()->execute($capability, $input, $actor);
            return response()->json([
                'success' => true,
                'capability' => $capability,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'capability' => $capability,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get diagnostic traces with telemetry stats and filters.
     */
    public function diagnostics(Request $request): JsonResponse
    {
        $diagnosticsService = app(\App\Copilot\CopilotDiagnosticsService::class);
        
        $filters = [
            'company_code' => $request->input('company_code') ?? $request->header('X-Company-Code'),
            'status' => $request->input('status'),
            'classifier_mode' => $request->input('classifier_mode'),
            'search' => $request->input('search'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'anomaly_flag' => $request->input('anomaly_flag'),
            'entry_point' => $request->input('entry_point'),
        ];

        $perPage = (int) $request->input('per_page', 25);
        $traces = $diagnosticsService->listTraces($filters, $perPage);
        $stats = $diagnosticsService->getStats($filters['company_code'] ?? null);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'data' => $traces->items(),
            'pagination' => [
                'total' => $traces->total(),
                'per_page' => $traces->perPage(),
                'current_page' => $traces->currentPage(),
                'last_page' => $traces->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single diagnostic trace.
     */
    public function diagnosticTrace(int $id): JsonResponse
    {
        $diagnosticsService = app(\App\Copilot\CopilotDiagnosticsService::class);
        $trace = $diagnosticsService->getTrace($id);

        if (!$trace) {
            return response()->json(['success' => false, 'message' => 'Trace not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $trace,
        ]);
    }

    /**
     * Update developer review status and notes on a diagnostic trace.
     */
    public function updateDiagnosticFeedback(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:unreviewed,verified,needs_fix,promoted_to_kb',
            'developer_notes' => 'nullable|string',
        ]);

        $diagnosticsService = app(\App\Copilot\CopilotDiagnosticsService::class);
        $trace = $diagnosticsService->updateFeedback($id, $request->input('status'), $request->input('developer_notes'));

        return response()->json([
            'success' => true,
            'data' => $trace,
        ]);
    }

    /**
     * Promote a diagnostic trace to the self-learning knowledgebase.
     */
    public function promoteDiagnosticToKnowledge(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'topic' => 'nullable|string',
            'trigger_keywords' => 'required|array|min:1',
            'domain' => 'nullable|string',
            'title' => 'required|string',
            'summary' => 'required|string',
            'steps' => 'nullable|array',
            'note' => 'nullable|string',
            'actions' => 'nullable|array',
        ]);

        $diagnosticsService = app(\App\Copilot\CopilotDiagnosticsService::class);
        $entry = $diagnosticsService->promoteToKnowledge($id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Diagnostic trace successfully promoted to Knowledgebase',
            'data' => $entry,
        ]);
    }

    /**
     * Export diagnostic dataset in JSON or JSONL format for dev agents and offline evaluation.
     */
    public function exportDiagnostics(Request $request)
    {
        $diagnosticsService = app(\App\Copilot\CopilotDiagnosticsService::class);
        $format = $request->input('format', 'jsonl');

        $filters = [
            'company_code' => $request->input('company_code') ?? $request->header('X-Company-Code'),
            'status' => $request->input('status'),
            'classifier_mode' => $request->input('classifier_mode'),
        ];

        $output = $diagnosticsService->exportTraces($filters, $format);

        if ($format === 'json') {
            return response()->json($output);
        }

        if ($format === 'csv') {
            $filename = 'copilot_diagnostic_eval_' . date('Ymd_His') . '.csv';
            return response($output, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $filename = 'copilot_diagnostic_eval_' . date('Ymd_His') . '.jsonl';
        return response($output, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Prune or clear diagnostic logs.
     */
    public function pruneDiagnostics(Request $request): JsonResponse
    {
        $diagnosticsService = app(\App\Copilot\CopilotDiagnosticsService::class);
        $beforeDate = $request->input('before_date');
        $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');

        $deleted = $diagnosticsService->pruneLogs($beforeDate, $companyCode);

        return response()->json([
            'success' => true,
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * List custom self-learned knowledgebase items.
     */
    public function knowledgeList(Request $request): JsonResponse
    {
        $companyCode = $request->input('company_code');
        $entries = \AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry::query()
            ->when($companyCode && $companyCode !== 'all', function ($q) use ($companyCode) {
                $q->where(function ($qb) use ($companyCode) {
                    $qb->whereNull('company_code')->orWhere('company_code', $companyCode);
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Create a new knowledgebase entry directly.
     */
    public function knowledgeStore(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string',
            'trigger_keywords' => 'required|array|min:1',
            'domain' => 'nullable|string',
            'title' => 'required|string',
            'summary' => 'required|string',
            'steps' => 'nullable|array',
            'note' => 'nullable|string',
            'actions' => 'nullable|array',
        ]);

        // Trigger keyword specificity enforcement (min 2 tokens)
        $triggerKeywords = $request->input('trigger_keywords', []);
        foreach ($triggerKeywords as $trigger) {
            $tokenCount = count(array_filter(explode(' ', trim($trigger))));
            if ($tokenCount < 2) {
                return response()->json([
                    'success' => false,
                    'message' => "Trigger keyword \"{$trigger}\" is too generic. Each trigger must contain at least 2 words to avoid shadowing primary copilot capabilities.",
                ], 422);
            }
        }

        $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');

        $entry = \AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry::create([
            'company_code' => $companyCode,
            'topic' => $request->input('topic'),
            'trigger_keywords' => $request->input('trigger_keywords'),
            'domain' => $request->input('domain', 'general'),
            'title' => $request->input('title'),
            'summary' => $request->input('summary'),
            'steps' => $request->input('steps', []),
            'note' => $request->input('note'),
            'actions' => $request->input('actions', []),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $entry,
        ]);
    }

    /**
     * Delete a knowledgebase entry.
     */
    public function knowledgeDelete(int $id): JsonResponse
    {
        $entry = \AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry::findOrFail($id);
        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Knowledge base entry deleted',
        ]);
    }
}

