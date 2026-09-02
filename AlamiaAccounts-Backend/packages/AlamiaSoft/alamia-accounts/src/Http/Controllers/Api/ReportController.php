<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\ReportService;

/**
 * @OA\Tag(name="Reports", description="Operations related to Financial Reports")
 */
class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * @OA\Get(
     *     path="/reports/trial-balance",
     *     summary="Get Trial Balance report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="as_of_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="currency", in="query", required=true, @OA\Schema(type="string", example="USD")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function trialBalance(Request $request)
    {
        $validated = $request->validate([
            'as_of_date' => 'required|date',
            'currency' => 'required|string|size:3',
        ]);

        $report = $this->reportService->getTrialBalance(
            $validated['as_of_date'],
            $validated['currency']
        );

        return response()->json(['data' => $report]);
    }

    /**
     * @OA\Get(
     *     path="/reports/profit-loss",
     *     summary="Get Profit and Loss report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="from_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="currency", in="query", required=true, @OA\Schema(type="string", example="USD")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function profitAndLoss(Request $request)
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'currency' => 'required|string|size:3',
        ]);

        $report = $this->reportService->getProfitAndLoss(
            $validated['from_date'],
            $validated['to_date'],
            $validated['currency']
        );

        return response()->json(['data' => $report]);
    }

    /**
     * @OA\Get(
     *     path="/reports/balance-sheet",
     *     summary="Get Balance Sheet report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="as_of_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="currency", in="query", required=true, @OA\Schema(type="string", example="USD")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function balanceSheet(Request $request)
    {
        $validated = $request->validate([
            'as_of_date' => 'required|date',
            'currency' => 'required|string|size:3',
        ]);

        $report = $this->reportService->getBalanceSheet(
            $validated['as_of_date'],
            $validated['currency']
        );

        return response()->json(['data' => $report]);
    }

    /**
     * @OA\Get(
     *     path="/reports/ledger",
     *     summary="Get Account Ledger report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="account_code", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="from_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="currency", in="query", required=true, @OA\Schema(type="string", example="USD")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function ledger(Request $request)
    {
        $validated = $request->validate([
            'account_code' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'currency' => 'required|string|size:3',
        ]);

        $report = $this->reportService->getAccountLedger(
            $validated['account_code'],
            $validated['from_date'],
            $validated['to_date'],
            $validated['currency']
        );

        return response()->json(['data' => $report]);
    }

    /**
     * Get Receivables Subledger Report (Customer balances & movements)
     */
    public function receivables(Request $request)
    {
        $validated = $request->validate([
            'as_of_date' => 'required|date',
            'currency' => 'nullable|string|size:3',
        ]);

        $report = $this->reportService->getReceivablesReport(
            $validated['as_of_date'],
            $validated['currency'] ?? 'PKR'
        );

        return response()->json(['data' => $report]);
    }

    /**
     * Get Payables Subledger Report (Supplier balances & movements)
     */
    public function payables(Request $request)
    {
        $validated = $request->validate([
            'as_of_date' => 'required|date',
            'currency' => 'nullable|string|size:3',
        ]);

        $report = $this->reportService->getPayablesReport(
            $validated['as_of_date'],
            $validated['currency'] ?? 'PKR'
        );

        return response()->json(['data' => $report]);
    }
}
