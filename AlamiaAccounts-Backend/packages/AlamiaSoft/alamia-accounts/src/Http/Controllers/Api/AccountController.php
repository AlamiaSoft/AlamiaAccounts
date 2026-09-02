<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;

/**
 * @OA\Tag(name="Accounts", description="Operations related to Chart of Accounts")
 */
class AccountController extends Controller
{
    protected $accountService;
    protected $voucherService;

    public function __construct(AccountService $accountService, VoucherService $voucherService)
    {
        $this->accountService = $accountService;
        $this->voucherService = $voucherService;
    }

    /**
     * @OA\Get(
     *     path="/accounts",
     *     summary="Get all accounts",
     *     tags={"Accounts"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object", @OA\Property(property="data", type="array", @OA\Items(type="object")))
     *     )
     * )
     */

    public function index()
    {
        $accounts = $this->accountService->getChartOfAccountsFormatted();
        
        return response()->json(['data' => $accounts]);
    }

    /**
     * @OA\Post(
     *     path="/accounts",
     *     summary="Create a new account",
     *     tags={"Accounts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string", example="1000"),
     *             @OA\Property(property="name", type="string", example="Cash"),
     *             @OA\Property(property="parent_code", type="string", example="1000"),
     *             @OA\Property(property="category", type="boolean", example=true),
     *             @OA\Property(property="debit", type="boolean", example=true),
     *             @OA\Property(property="credit", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Account created successfully"),
     *     @OA\Response(response=400, description="Bad Request")
     * )
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
            'parent_code' => 'nullable|string',
            'category' => 'nullable|boolean',
            'debit' => 'nullable|boolean',
            'credit' => 'nullable|boolean',
            'type' => 'nullable|string',
        ]);

        $code = $validated['code'];
        $type = $request->input('type');

        // Auto-infer parent_code if not explicitly set
        if (empty($validated['parent_code'])) {
            if (str_starts_with($code, '1')) {
                $validated['parent_code'] = '1000';
            } elseif (str_starts_with($code, '2')) {
                $validated['parent_code'] = '2000';
            } elseif (str_starts_with($code, '3')) {
                $validated['parent_code'] = '3000';
            } elseif (str_starts_with($code, '4')) {
                $validated['parent_code'] = '4000';
            } elseif (str_starts_with($code, '5')) {
                $validated['parent_code'] = '5000';
            }
        }

        // Auto-infer normal debit/credit balance if not specified
        if (!isset($validated['debit']) && !isset($validated['credit'])) {
            $isDebit = str_starts_with($code, '1') || str_starts_with($code, '4') || in_array($type, ['Asset', 'Bank', 'Cash', 'Expense']);
            $validated['debit'] = $isDebit;
            $validated['credit'] = !$isDebit;
        }

        try {
            $account = $this->accountService->createAccount($validated);
            
            if (!empty($request->input('opening_balance')) && floatval($request->input('opening_balance')) > 0) {
                $this->applyOpeningBalance($account->code, floatval($request->input('opening_balance')), $request->input('opening_balance_date'));
            }

            return response()->json([
                'success' => true,
                'data' => $account,
                'message' => 'Account created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/accounts/{code}",
     *     summary="Get account by code",
     *     tags={"Accounts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Account not found")
     * )
     */

    public function show($code)
    {
        $account = $this->accountService->getAccount($code);
        
        return response()->json(['data' => $account]);
    }

    /**
     * @OA\Put(
     *     path="/accounts/{code}",
     *     summary="Update account",
     *     tags={"Accounts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Cash Updated"),
     *             @OA\Property(property="parent_code", type="string", example="1000"),
     *             @OA\Property(property="category", type="string", example="asset")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Account updated successfully"),
     *     @OA\Response(response=400, description="Bad Request")
     * )
     */

    public function update(Request $request, $code)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_code' => 'nullable|string',
            'category' => 'nullable', // Allows boolean, string or int
            'opening_balance' => 'nullable|numeric|min:0',
            'opening_balance_date' => 'nullable|date',
        ]);

        $account = $this->accountService->updateAccount(
            $code,
            $validated['name'],
            $validated['parent_code'] ?? null,
            $validated['category'] ?? null
        );

        if (!empty($validated['opening_balance']) && floatval($validated['opening_balance']) > 0) {
            $this->applyOpeningBalance($code, floatval($validated['opening_balance']), $validated['opening_balance_date'] ?? null);
        }

        return response()->json(['data' => $account]);
    }

    /**
     * Set or update opening balance for an account with double-entry equity offset.
     */
    public function setOpeningBalance(Request $request, $code)
    {
        $validated = $request->validate([
            'opening_balance' => 'required|numeric|min:0.01',
            'date' => 'nullable|date',
        ]);

        $voucher = $this->applyOpeningBalance($code, floatval($validated['opening_balance']), $validated['date'] ?? null);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply opening balance. Ensure account exists and is not a category folder.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Opening balance successfully recorded with offsetting equity entry.',
            'voucher_reference' => $voucher->reference ?? null,
            'data' => $this->accountService->getChartOfAccountsFormatted()
        ]);
    }

    /**
     * Record an opening balance journal entry with automatic double-entry equity offset.
     */
    protected function applyOpeningBalance(string $code, float $amount, ?string $date = null)
    {
        $account = $this->accountService->getAccountByCode($code);
        if (!$account || $account->category) {
            return null;
        }

        // Find Capital offset account (5100 Owner's Capital / 5000 Equity / 3000)
        $capitalAccount = $this->accountService->getAccountByCode('5100')
            ?? $this->accountService->getAccountByCode('5000')
            ?? $this->accountService->getAccountByCode('3000');
        $capitalCode = $capitalAccount ? $capitalAccount->code : '5100';

        $isDebitAccount = (bool)$account->debit;
        $entryDate = $date ?? date('Y-01-01');
        $accountName = $account->names->first()->name ?? $code;

        // Assets/Expenses: Debit the account, Credit Capital
        // Liabilities/Equity: Credit the account, Debit Capital
        $entries = [
            [
                'account_code' => $code,
                'amount' => $amount,
                'type' => $isDebitAccount ? 'debit' : 'credit',
                'description' => "Opening Balance for {$accountName}"
            ],
            [
                'account_code' => $capitalCode,
                'amount' => $amount,
                'type' => $isDebitAccount ? 'credit' : 'debit',
                'description' => "Opening Balance Equity Offset for {$accountName}"
            ]
        ];

        $ref = 'OB-' . $code . '-' . strtoupper(substr(uniqid(), -4));

        return $this->voucherService->createJournalEntry([
            'date' => $entryDate,
            'reference' => $ref,
            'description' => "Opening Balance recorded for {$accountName}",
            'currency' => 'PKR',
            'entries' => $entries,
            'opening' => 1,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/accounts/{code}",
     *     summary="Delete account",
     *     tags={"Accounts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Account deleted successfully"),
     *     @OA\Response(response=404, description="Account not found")
     * )
     */

    public function destroy($code)
    {
        $this->accountService->deleteAccount($code);
        
        return response()->json(null, 204);
    }
}
