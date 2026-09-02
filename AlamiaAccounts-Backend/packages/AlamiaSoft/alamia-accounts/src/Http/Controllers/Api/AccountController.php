<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;

/**
 * @OA\Tag(name="Accounts", description="Operations related to Chart of Accounts")
 */
class AccountController extends Controller
{
    protected $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
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
        ]);

        try {
            $account = $this->accountService->createAccount($validated);
            
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
            'category' => 'nullable|string',
        ]);

        $account = $this->accountService->updateAccount(
            $code,
            $validated['name'],
            $validated['parent_code'] ?? null,
            $validated['category'] ?? null
        );

        return response()->json(['data' => $account]);
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
