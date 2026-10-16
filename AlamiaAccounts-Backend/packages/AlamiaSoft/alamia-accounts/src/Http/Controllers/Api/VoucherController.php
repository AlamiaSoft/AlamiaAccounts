<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;

/**
 * @OA\Tag(name="Vouchers", description="Operations related to Journal Vouchers")
 */
class VoucherController extends Controller
{
    protected $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * @OA\Get(
     *     path="/vouchers",
     *     summary="Get all vouchers",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="from_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function index(Request $request)
    {
        // Get vouchers with optional filtering
        $vouchers = $this->voucherService->getVouchers(
            $request->input('from_date'),
            $request->input('to_date')
        );
        
        return response()->json(['data' => $vouchers]);
    }

    /**
     * @OA\Post(
     *     path="/vouchers",
     *     summary="Create a new journal voucher",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"date", "reference", "currency", "entries"},
     *             @OA\Property(property="date", type="string", format="date", example="2023-10-01"),
     *             @OA\Property(property="reference", type="string", example="JV-001"),
     *             @OA\Property(property="description", type="string", example="Monthly adjustment"),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="entries", type="array", @OA\Items(
     *                 @OA\Property(property="account_code", type="string", example="1000"),
     *                 @OA\Property(property="amount", type="number", example=100.50),
     *                 @OA\Property(property="type", type="string", enum={"debit", "credit"}),
     *                 @OA\Property(property="description", type="string", example="Entry description")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Voucher created successfully")
     * )
     */

    public function store(Request $request)
    {
        // 1. Normalize currency to current company's configured default currency
        if (!$request->filled('currency')) {
            $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');
            $request->merge([
                'currency' => \AlamiaSoft\AlamiaAccounts\Services\DomainContext::getDefaultCurrency($companyCode)
            ]);
        }

        // 2. Normalize entries from lineItems, details, or line_items if entries is absent
        if (!$request->filled('entries') && ($request->filled('lineItems') || $request->filled('details') || $request->filled('line_items'))) {
            $rawLines = $request->input('lineItems') ?? $request->input('details') ?? $request->input('line_items') ?? [];
            $normalizedEntries = [];
            foreach ($rawLines as $line) {
                $accountCode = $line['account_code'] ?? $line['account'] ?? '';
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);
                $amt = (float) ($line['amount'] ?? 0);
                if ($amt <= 0) {
                    $amt = $debit > 0 ? $debit : $credit;
                }
                $type = $line['type'] ?? ($debit > 0 ? 'debit' : 'credit');
                $desc = $line['description'] ?? $line['memo'] ?? $request->input('narration') ?? $request->input('description') ?? null;

                $normalizedEntries[] = [
                    'account_code' => (string) $accountCode,
                    'amount' => $amt,
                    'type' => strtolower($type) === 'credit' ? 'credit' : 'debit',
                    'description' => $desc,
                ];
            }
            $request->merge(['entries' => $normalizedEntries]);
        }

        // 3. Normalize description from narration if absent
        if (!$request->filled('description') && $request->filled('narration')) {
            $request->merge(['description' => $request->input('narration')]);
        }

        // 4. Normalize reference from number if absent
        if (!$request->filled('reference') && $request->filled('number')) {
            $request->merge(['reference' => $request->input('number')]);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string',
            'description' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'entries' => 'required|array|min:2',
            'entries.*.account_code' => 'required|string',
            'entries.*.amount' => 'required|numeric|min:0.01',
            'entries.*.type' => 'required|in:debit,credit',
            'entries.*.description' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'voucher_type' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        try {
            $voucher = $this->voucherService->createJournalEntry([
                'date' => $validated['date'],
                'reference' => $validated['reference'],
                'description' => $validated['description'] ?? '',
                'currency' => $validated['currency'],
                'entries' => $validated['entries'],
                'custom_fields' => $request->input('custom_fields'),
                'voucher_type' => $request->input('voucher_type') ?? $request->input('type'),
                'type' => $request->input('type'),
            ]);

            return response()->json(['data' => $voucher], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/vouchers/{reference}",
     *     summary="Get voucher details",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Voucher not found")
     * )
     */

    public function show($reference)
    {
        $voucher = $this->voucherService->getVoucher($reference);
        
        return response()->json(['data' => $voucher]);
    }

    /**
     * @OA\Delete(
     *     path="/vouchers/{reference}",
     *     summary="Delete a voucher",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Voucher deleted successfully"),
     *     @OA\Response(response=404, description="Voucher not found")
     * )
     */

    public function destroy($reference)
    {
        return response()->json([
            'success' => false,
            'message' => 'Posted accounting vouchers cannot be physically deleted. Use voucher reversal to maintain double-entry audit history.',
            'error' => 'CANNOT_DELETE_POSTED_VOUCHER',
        ], 422);
    }

    /**
     * Reverse a posted voucher (creates compensating REV- voucher)
     */
    public function reverse($reference, Request $request)
    {
        try {
            $date = $request->input('date');
            $reason = $request->input('reason');
            $reversed = $this->voucherService->reverseVoucher($reference, $date, $reason);

            return response()->json([
                'message' => "Voucher {$reference} reversed successfully",
                'data' => $reversed,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Clear / Reset all transactions for the current tenant company.
     * Strictly disabled in production. Requires explicit feature flag, elevated admin,
     * tenant verification, and confirmation token.
     */
    public function clearAll(Request $request): JsonResponse
    {
        // P0 Security Guard 1: Hard Environment Check
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Destructive bulk ledger reset is strictly prohibited in production environments.',
                'error' => 'production_reset_prohibited',
            ], 403);
        }

        // P0 Security Guard 2: Feature Flag Check
        if (!config('alamia-accounts.allow_destructive_reset', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Destructive bulk ledger reset feature flag is disabled.',
                'error' => 'feature_flag_disabled',
            ], 403);
        }

        // P0 Security Guard 3: Elevated Administrator Role Check
        $user = $request->user() ?? auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
                'error' => 'authentication_required',
            ], 401);
        }

        $role = strtolower((string) ($user->role ?? ''));
        if (!in_array($role, ['admin', 'owner', 'super_admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrative privileges required for destructive ledger reset.',
                'error' => 'insufficient_privileges',
            ], 403);
        }

        // P0 Security Guard 4: Tenant Boundary Enforcement
        $effectiveCompany = strtoupper(trim((string) (
            $request->attributes->get('tenant_company_code') ??
            \AlamiaSoft\AlamiaAccounts\Services\DomainContext::get() ??
            ''
        )));

        $requestedCompany = strtoupper(trim((string) (
            $request->input('company_code') ??
            $request->header('X-Company-Code') ??
            ''
        )));

        if ($requestedCompany !== '' && $requestedCompany !== $effectiveCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied for the requested company.',
                'error' => 'company_access_denied',
            ], 403);
        }

        // P0 Security Guard 5: Explicit Confirmation Token
        $confirmationToken = $request->input('confirmation_token');
        $expectedToken = 'CONFIRM_RESET_' . $effectiveCompany;
        if ($confirmationToken !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => "Invalid confirmation token. You must explicitly pass confirmation_token: '{$expectedToken}'.",
                'error' => 'invalid_confirmation_token',
            ], 422);
        }

        try {
            $count = $this->voucherService->clearAllTransactions(
                $effectiveCompany,
                $confirmationToken,
                (string) ($user->id ?? 'admin')
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully cleared {$count} transactions for company {$effectiveCompany}.",
                'cleared_count' => $count,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'reset_failed',
            ], 422);
        }
    }
}
