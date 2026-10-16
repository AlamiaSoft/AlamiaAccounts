<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Copilot\CopilotService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Alamia360\Facades\Alamia360;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CopilotController extends Controller
{
    protected CopilotService $copilot;

    public function __construct(CopilotService $copilot)
    {
        $this->copilot = $copilot;
    }

    /**
     * Resolve the effective authorized tenant company code from request context.
     */
    protected function getEffectiveTenantCompany(Request $request): string
    {
        return strtoupper(trim((string) (
            $request->attributes->get('tenant_company_code') ??
            DomainContext::get() ??
            ''
        )));
    }

    /**
     * Validate that caller cannot supply or override an unauthorized cross-tenant company code.
     */
    protected function validateTenantBoundary(Request $request): ?JsonResponse
    {
        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        $requestedCompany = strtoupper(trim((string) (
            $request->input('company_code') ??
            $request->query('company_code') ??
            $request->header('X-Company-Code') ??
            ''
        )));

        if ($requestedCompany !== '' && $requestedCompany !== 'ALL' && $requestedCompany !== $effectiveCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied for the requested company.',
                'error' => 'company_access_denied',
            ], 403);
        }

        return null;
    }

    /**
     * Check if authenticated principal has administrative privileges.
     */
    protected function isPrivilegedAdmin(Request $request): bool
    {
        $user = $request->user() ?? auth('sanctum')->user();
        if (!$user) {
            return false;
        }

        $role = strtolower((string) ($user->role ?? ''));
        return in_array($role, ['admin', 'owner', 'lead_accountant', 'super_admin'], true);
    }

    /**
     * Check if authenticated principal has financial mutation privileges.
     */
    protected function canExecuteFinancialMutations(Request $request): bool
    {
        $user = $request->user() ?? auth('sanctum')->user();
        if (!$user) {
            return false;
        }

        $role = strtolower((string) ($user->role ?? 'viewer'));
        return in_array($role, ['admin', 'owner', 'lead_accountant', 'accountant', 'manager', 'super_admin'], true);
    }

    /**
     * Sanitize internal exceptions to prevent disclosure of stack traces, queries, or secrets.
     */
    protected function sanitizeErrorMessage(\Throwable $e, string $action = 'copilot'): string
    {
        Log::error("Copilot {$action} failure", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $msg = $e->getMessage();

        // Whitelist safe domain validation & rule violation messages
        if (
            $e instanceof \Illuminate\Validation\ValidationException ||
            $e instanceof \InvalidArgumentException ||
            str_contains($msg, 'mandatory for institutional authorization') ||
            str_contains($msg, 'Amount confirmation mismatch') ||
            str_contains($msg, 'Temporal Invariant') ||
            str_contains($msg, 'Category Folder') ||
            str_contains($msg, 'Double-entry imbalance') ||
            str_contains($msg, 'Account code') ||
            str_contains($msg, 'Voucher draft must have') ||
            str_contains($msg, 'Trigger keyword') ||
            str_contains($msg, 'closed or locked') ||
            str_contains($msg, 'PERIOD_CLOSED') ||
            str_contains($msg, 'minimum 15 characters and 3 words')
        ) {
            return $msg;
        }

        return 'An internal error occurred while processing the request. Details have been logged.';
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

        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        DomainContext::set($effectiveCompany);

        $prompt = (string) ($request->input('prompt') ?? '');
        $context = $request->input('context') ?? [];

        try {
            $response = $this->copilot->handleChat($prompt, $effectiveCompany, $context);
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Throwable $e) {
            $safeError = $this->sanitizeErrorMessage($e, 'chat');
            return response()->json([
                'success' => false,
                'error' => $safeError,
                'sender' => 'Taliya',
                'message' => 'An error occurred while executing this capability: ' . $safeError,
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
     * Execute a registered Alamia 360 capability directly with policy, role, and maker-checker validation.
     */
    public function executeCapability(Request $request, string $capability): JsonResponse
    {
        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        DomainContext::set($effectiveCompany);

        // Capability side-effect inspection & role authorization
        $registry = app(\Alamia360\Capabilities\CapabilityRegistry::class);
        $cap = $registry->has($capability) ? $registry->get($capability) : null;
        $isWrite = $cap ? ($cap->sideEffect() === 'write') : in_array($capability, ['post_voucher', 'reverse_voucher'], true);

        if ($isWrite && !$this->canExecuteFinancialMutations($request)) {
            return response()->json([
                'success' => false,
                'capability' => $capability,
                'message' => 'You do not have permission to execute financial mutations.',
                'error' => 'unauthorized_capability_mutation',
            ], 403);
        }

        $user = $request->user() ?? auth('sanctum')->user();
        $input = $request->input('input', $request->except(['company_code', 'actor_id']));

        $capabilities = [
            'lookup_account',
            'draft_voucher',
            'lookup_voucher',
            'account_balance',
            'get_financial_report',
            'list_situations',
            'search_entities',
            'resolve_entity',
        ];

        if ($this->canExecuteFinancialMutations($request)) {
            $capabilities[] = 'post_voucher';
            $capabilities[] = 'reverse_voucher';
        }

        // Anchor actor identity to authenticated principal; cannot bypass policy by claiming AI/system role
        $actor = \Alamia360\Actors\Actor::human(
            (string) ($user->id ?? 'system'),
            $user->role ?? 'viewer'
        )
            ->withCapabilities($capabilities)
            ->authorizeUsing(fn ($a, $cap, $sub) => in_array($cap, $capabilities, true));

        try {
            $result = Alamia360::capabilities()->execute($capability, $input, $actor);
            return response()->json([
                'success' => true,
                'capability' => $capability,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $safeError = $this->sanitizeErrorMessage($e, "capability:{$capability}");
            return response()->json([
                'success' => false,
                'capability' => $capability,
                'error' => $safeError,
                'message' => $safeError,
            ], 422);
        }
    }

    /**
     * Get diagnostic traces with telemetry stats and filters (Administrative Only).
     */
    public function diagnostics(Request $request): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for diagnostic operations.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        $diagnosticsService = app(\AlamiaSoft\AlamiaAccounts\Copilot\CopilotDiagnosticsService::class);
        
        $filters = [
            'company_code' => $effectiveCompany,
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
        $stats = $diagnosticsService->getStats($effectiveCompany);

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
     * Get a single diagnostic trace (Administrative Only).
     */
    public function diagnosticTrace(Request $request, int $id): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for diagnostic operations.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        $diagnosticsService = app(\AlamiaSoft\AlamiaAccounts\Copilot\CopilotDiagnosticsService::class);
        $trace = $diagnosticsService->getTrace($id);

        if (!$trace) {
            return response()->json(['success' => false, 'message' => 'Trace not found'], 404);
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        if (!empty($trace->company_code) && $trace->company_code !== $effectiveCompany && !$this->isPrivilegedAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Access denied for this trace', 'error' => 'company_access_denied'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $trace,
        ]);
    }

    /**
     * Update developer review status and notes on a diagnostic trace (Administrative Only).
     */
    public function updateDiagnosticFeedback(Request $request, int $id): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for diagnostic operations.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        $request->validate([
            'status' => 'required|string|in:unreviewed,verified,needs_fix,promoted_to_kb',
            'developer_notes' => 'nullable|string',
        ]);

        $diagnosticsService = app(\AlamiaSoft\AlamiaAccounts\Copilot\CopilotDiagnosticsService::class);
        $trace = $diagnosticsService->updateFeedback($id, $request->input('status'), $request->input('developer_notes'));

        return response()->json([
            'success' => true,
            'data' => $trace,
        ]);
    }

    /**
     * Promote a diagnostic trace to the self-learning knowledgebase (Administrative Only).
     */
    public function promoteDiagnosticToKnowledge(Request $request, int $id): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for knowledge management.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

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

        $diagnosticsService = app(\AlamiaSoft\AlamiaAccounts\Copilot\CopilotDiagnosticsService::class);
        $entry = $diagnosticsService->promoteToKnowledge($id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Diagnostic trace successfully promoted to Knowledgebase',
            'data' => $entry,
        ]);
    }

    /**
     * Export diagnostic dataset in JSON or JSONL format (Administrative Only).
     */
    public function exportDiagnostics(Request $request)
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for diagnostic operations.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        $diagnosticsService = app(\AlamiaSoft\AlamiaAccounts\Copilot\CopilotDiagnosticsService::class);
        $format = $request->input('format', 'jsonl');

        $filters = [
            'company_code' => $effectiveCompany,
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
     * Prune or clear diagnostic logs (Administrative Only).
     */
    public function pruneDiagnostics(Request $request): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for diagnostic operations.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        $diagnosticsService = app(\AlamiaSoft\AlamiaAccounts\Copilot\CopilotDiagnosticsService::class);
        $beforeDate = $request->input('before_date');

        $deleted = $diagnosticsService->pruneLogs($beforeDate, $effectiveCompany);

        return response()->json([
            'success' => true,
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * List custom self-learned knowledgebase items (Tenant Scoped).
     */
    public function knowledgeList(Request $request): JsonResponse
    {
        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        $entries = \AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry::query()
            ->where(function ($qb) use ($effectiveCompany) {
                $qb->whereNull('company_code')->orWhere('company_code', $effectiveCompany);
            })
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Create a new knowledgebase entry directly (Administrative Only).
     */
    public function knowledgeStore(Request $request): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for knowledge management.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        if ($tenantViolation = $this->validateTenantBoundary($request)) {
            return $tenantViolation;
        }

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

        $effectiveCompany = $this->getEffectiveTenantCompany($request);

        $entry = \AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry::create([
            'company_code' => $effectiveCompany,
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
     * Delete a knowledgebase entry (Administrative Only).
     */
    public function knowledgeDelete(Request $request, int $id): JsonResponse
    {
        if (!$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for knowledge management.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        $effectiveCompany = $this->getEffectiveTenantCompany($request);
        $entry = \AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry::findOrFail($id);

        if (!empty($entry->company_code) && $entry->company_code !== $effectiveCompany && !$this->isPrivilegedAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied for this knowledge entry.',
                'error' => 'company_access_denied',
            ], 403);
        }

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Knowledge base entry deleted',
        ]);
    }
}
