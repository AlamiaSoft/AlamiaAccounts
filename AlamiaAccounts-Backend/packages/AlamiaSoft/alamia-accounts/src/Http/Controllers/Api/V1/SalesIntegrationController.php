<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\V1;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Services\SalesIntegrationService;
use AlamiaSoft\AlamiaAccounts\Models\OperationalSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

        $validator = \Illuminate\Support\Facades\Validator::make($payload, [
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
        $companyCode = $request->attributes->get('tenant_company_code') ?? 'MAIN';
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
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sale: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List sales transactions with staff and tenant scoping.
     */
    public function index(Request $request): JsonResponse
    {
        // Context supplied by AuthenticateSalesOrSanctum middleware
        $companyCode = $request->attributes->get('tenant_company_code') ?? 'MAIN';
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
    public function show($id): JsonResponse
    {
        $sale = OperationalSale::with('items')->find((int)$id);
        if (!$sale) {
            return response()->json(['success' => false, 'message' => 'Sale not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    /**
     * Approve a staged sale and commit vouchers to ledger.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $approverName = $user ? $user->name : ($request->input('approver_name') ?? 'Manager');

        try {
            $result = $this->salesService->approveSale((int)$id, $approverName);
            return response()->json([
                'success' => true,
                'message' => 'Sale approved and committed to ledger.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve sale: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Compute end-of-shift cashier cash drawer reconciliation.
     */
    public function reconcileShift(Request $request): JsonResponse
    {
        $companyCode = $request->attributes->get('tenant_company_code') ?? $request->input('company_code') ?? 'MAIN';
        $user = $request->user();
        $userId = $user ? (string) $user->id : ($request->input('agent_id') ?? null);
        $date = $request->input('date') ?? date('Y-m-d');
        $cashCounted = (float) ($request->input('actual_cash') ?? $request->input('actual_cash_counted') ?? 0);

        $report = $this->salesService->reconcileShift($companyCode, $date, $userId, $cashCounted);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Print thermal receipt (58mm or 80mm).
     */
    public function printReceipt(Request $request, $id): Response
    {
        $sale = OperationalSale::with('items')->find((int)$id);
        if (!$sale) {
            return response('Sale receipt not found.', 404);
        }

        $paperWidth = $request->query('width', '80mm');
        $html = $this->salesService->renderThermalReceiptHtml($sale, $paperWidth);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
