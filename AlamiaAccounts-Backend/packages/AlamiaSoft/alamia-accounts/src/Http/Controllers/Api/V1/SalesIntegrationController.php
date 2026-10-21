<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\V1;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Services\SalesIntegrationService;
use AlamiaSoft\AlamiaAccounts\Models\OperationalSale;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SalesIntegrationController extends Controller
{
    protected SalesIntegrationService $salesService;

    public function __construct(SalesIntegrationService $salesService)
    {
        $this->salesService = $salesService;
    }

    /**
     * Create a sales / POS checkout transaction and auto-post double-entry vouchers.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Auto-normalize flat payload from simple POS / Frontline forms
        if (!isset($payload['line_items']) && (isset($payload['amount']) || isset($payload['customer_name']))) {
            $custName = $payload['customer_name'] ?? 'Walk-in Client';
            $custPassport = $payload['customer_passport'] ?? $payload['passport_number'] ?? null;
            $amt = (float) ($payload['amount'] ?? $payload['total_amount'] ?? 0);
            $paid = (float) ($payload['paid_amount'] ?? $payload['payment_received'] ?? 0);
            $service = $payload['service_type'] ?? 'General Service';

            $payload['customer'] = [
                'name' => $custName,
                'cnic_or_ntn' => $custPassport,
                'subledger_code' => $payload['customer_subledger'] ?? '1200',
            ];
            $payload['line_items'] = [
                [
                    'description' => $payload['description'] ?? "{$service} for {$custName}" . ($custPassport ? " (P.No: {$custPassport})" : ''),
                    'quantity' => 1,
                    'unit_price' => $amt,
                    'revenue_account_code' => $payload['revenue_account'] ?? '3100',
                ]
            ];
            $payload['payments'] = $paid > 0 ? [
                [
                    'method' => $payload['payment_method'] ?? 'cash',
                    'amount' => $paid,
                    'deposit_account_code' => $payload['payment_account'] ?? '1110',
                    'reference_note' => $payload['payment_note'] ?? "Cash received at POS counter",
                ]
            ] : [];
            $payload['workflow'] = [
                'mode' => $payload['workflow_mode'] ?? 'instant_post',
            ];
        }

        $validator = Validator::make($payload, [
            'customer' => 'required|array',
            'customer.name' => 'required|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'nullable|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'payments' => 'nullable|array',
            'workflow' => 'nullable|array',
            'client_reference_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Context supplied by AuthenticateSalesOrSanctum middleware
        $companyCode = $request->attributes->get('tenant_company_code');
        if (!$companyCode) {
            return response()->json([
                'success' => false,
                'error' => 'company_context_required',
                'message' => 'An authorized company context is required.',
            ], 403);
        }

        $user = $request->user();
        $userId = $user ? (string) $user->id : ($request->input('agent_id') ?? 'counter_agent');
        $userName = $user ? $user->name : ($request->input('agent_name') ?? 'Counter Staff');

        $payload['idempotency_key'] = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        try {
            $result = $this->salesService->processSale($payload, $companyCode, $userId, $userName);
            $statusCode = !empty($result['is_idempotent_replay']) ? 200 : 201;

            return response()->json([
                'success' => true,
                'message' => ($result['status'] === 'posted') ? 'Sale posted and vouchers created successfully.' : 'Sale staged pending manager approval.',
                'data' => $result,
            ], $statusCode);
        } catch (Throwable $e) {
            Log::error('Sale processing failed', [
                'company_code' => $companyCode,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'sale_processing_failed',
                'message' => 'Failed to process sale. Please verify transaction details and try again.',
            ], 422);
        }
    }

    /**
     * List sales transactions with staff and tenant scoping.
     */
    public function index(Request $request): JsonResponse
    {
        // Context supplied by AuthenticateSalesOrSanctum middleware
        $companyCode = $request->attributes->get('tenant_company_code');
        if (!$companyCode) {
            return response()->json([
                'success' => false,
                'error' => 'company_context_required',
                'message' => 'An authorized company context is required.',
            ], 403);
        }

        $user = $request->user();
        $currentUserId = $user ? (string) $user->id : ($request->input('agent_id') ?? null);
        $isManager = (bool) $request->attributes->get('is_manager', false);

        $filters = [
            'status' => $request->input('status'),
            'search' => $request->input('search'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'scope' => $request->input('scope', 'mine'),
        ];

        $perPage = (int) $request->input('per_page', 25);
        $sales = $this->salesService->listSales($filters, $companyCode, $currentUserId, $isManager, $perPage);

        return response()->json([
            'success' => true,
            'data' => $sales->items(),
            'pagination' => [
                'total' => $sales->total(),
                'per_page' => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
            ],
        ]);
    }

    /**
     * Get details of a single sale.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $companyCode = $request->attributes->get('tenant_company_code');
        if (!$companyCode) {
            return response()->json([
                'success' => false,
                'error' => 'company_context_required',
                'message' => 'An authorized company context is required.',
            ], 403);
        }

        $query = OperationalSale::with('items')
            ->where('company_code', $companyCode)
            ->whereKey((int) $id);
        $user = $request->user();
        if ($user && !$request->attributes->get('is_manager', false)) {
            $query->where('created_by_user_id', (string) $user->id);
        }

        $sale = $query->first();
        if (!$sale) {
            return response()->json([
                'success' => false,
                'error' => 'sale_not_found',
                'message' => 'Sale not found.',
            ], 404);
        }

        return response()->json(['success' => true, 'data' => $sale]);
    }

    /**
     * Approve a staged sale and commit vouchers to ledger.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $companyCode = $request->attributes->get('tenant_company_code');
        if (!$companyCode) {
            return response()->json([
                'success' => false,
                'error' => 'company_context_required',
                'message' => 'An authorized company context is required.',
            ], 403);
        }
        if (!$request->attributes->get('is_manager', false)) {
            return response()->json([
                'success' => false,
                'error' => 'approval_forbidden',
                'message' => 'User does not possess manager or administrator approval privileges.',
            ], 403);
        }

        $user = $request->user();
        $approverName = $user ? $user->name : 'Authorized POS approver';
        try {
            $result = $this->salesService->approveSale((int) $id, $approverName, $companyCode);
            return response()->json([
                'success' => true,
                'message' => 'Sale approved and committed to ledger.',
                'data' => $result,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'sale_not_found',
                'message' => 'Sale not found.',
            ], 404);
        } catch (DomainException $e) {
            $isAlreadyApproved = str_contains(strtolower($e->getMessage()), 'already been approved');
            return response()->json([
                'success' => false,
                'error' => $isAlreadyApproved ? 'sale_already_approved' : 'invalid_sale_state',
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Sale approval failed', [
                'sale_id' => $id,
                'company_code' => $companyCode,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'sale_approval_failed',
                'message' => 'The sale could not be approved. Verify its company and current status.',
            ], 422);
        }
    }

    /**
     * Compute end-of-shift cashier cash drawer reconciliation.
     */
    public function reconcileShift(Request $request): JsonResponse
    {
        $companyCode = $request->attributes->get('tenant_company_code');
        if (!$companyCode) {
            return response()->json([
                'success' => false,
                'error' => 'company_context_required',
                'message' => 'An authorized company context is required.',
            ], 403);
        }

        $user = $request->user();
        $isManager = (bool) $request->attributes->get('is_manager', false);

        if ($user && !$isManager) {
            $requestedAgent = $request->input('agent_id');
            if ($requestedAgent !== null && (string) $requestedAgent !== (string) $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'shift_reconciliation_forbidden',
                    'message' => 'Staff members are only permitted to reconcile their own shift.',
                ], 403);
            }
            $userId = (string) $user->id;
        } else {
            // Managers can reconcile all cashiers across the company (null) or a specific cashier
            $requestedAgent = $request->input('agent_id');
            $userId = ($requestedAgent !== null && $requestedAgent !== 'all' && $requestedAgent !== '')
                ? (string) $requestedAgent
                : null;
        }

        $rawDate = $request->input('date');
        $date = null;
        if ($rawDate && is_string($rawDate)) {
            try {
                $date = Carbon::parse($rawDate)->toDateString();
            } catch (Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_date_format',
                    'message' => 'The provided reconciliation date is invalid.',
                ], 422);
            }
        }

        $cashInput = $request->input('actual_cash') ?? $request->input('actual_cash_counted') ?? 0;
        if (!is_numeric($cashInput) || (float) $cashInput < 0) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_cash_amount',
                'message' => 'Actual cash counted must be a non-negative number.',
            ], 422);
        }
        $cashCounted = (float) $cashInput;

        try {
            $report = $this->salesService->reconcileShift($companyCode, $date, $userId, $cashCounted);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (Throwable $e) {
            Log::error('Shift reconciliation failed', [
                'company_code' => $companyCode,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'reconciliation_failed',
                'message' => 'Shift reconciliation could not be computed.',
            ], 422);
        }
    }

    /**
     * Print thermal receipt (58mm or 80mm).
     */
    public function printReceipt(Request $request, $id): Response|JsonResponse
    {
        $companyCode = $request->attributes->get('tenant_company_code');
        if (!$companyCode) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'company_context_required',
                    'message' => 'An authorized company context is required.',
                ], 403);
            }
            return response('An authorized company context is required.', 403);
        }

        $query = OperationalSale::with('items')
            ->where('company_code', $companyCode)
            ->whereKey((int) $id);
        $user = $request->user();
        if ($user && !$request->attributes->get('is_manager', false)) {
            $query->where('created_by_user_id', (string) $user->id);
        }

        $sale = $query->first();
        if (!$sale) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'sale_not_found',
                    'message' => 'Sale receipt not found.',
                ], 404);
            }
            return response('Sale receipt not found.', 404);
        }

        $paperWidth = $request->query('width', '80mm');
        if (!in_array($paperWidth, ['58mm', '80mm'], true)) {
            $paperWidth = '80mm';
        }
        $html = $this->salesService->renderThermalReceiptHtml($sale, $paperWidth);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
