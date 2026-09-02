<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Services\OpeningBalanceService;
use Illuminate\Http\Request;
use Exception;

class OpeningBalanceController extends Controller
{
    protected OpeningBalanceService $openingBalanceService;

    public function __construct(OpeningBalanceService $openingBalanceService)
    {
        $this->openingBalanceService = $openingBalanceService;
    }

    public function index()
    {
        try {
            $status = $this->openingBalanceService->getOpeningBalanceStatus();
            return response()->json(['data' => $status]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'balance_date' => 'required|date',
            'entries' => 'required|array|min:2',
            'entries.*.account_code' => 'required|string',
            'entries.*.amount' => 'required|numeric|min:0',
            'entries.*.type' => 'required|in:debit,credit',
            'entries.*.description' => 'nullable|string',
            'balancing_account_code' => 'nullable|string',
        ]);

        try {
            $result = $this->openingBalanceService->postOpeningBalances($validated);
            return response()->json(['data' => $result], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
