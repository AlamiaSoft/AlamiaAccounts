<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Services\PeriodService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Illuminate\Http\Request;
use Exception;

class PeriodController extends Controller
{
    protected PeriodService $periodService;

    public function __construct(PeriodService $periodService)
    {
        $this->periodService = $periodService;
    }

    public function index(Request $request)
    {
        $domain = DomainContext::getDomain();
        if (!$domain) {
            return response()->json(['message' => 'Company domain not found.'], 404);
        }

        $year = $request->input('year') ? (int)$request->input('year') : null;
        $periods = $this->periodService->getPeriods($domain->domainUuid, $year);

        return response()->json(['data' => $periods]);
    }

    public function close($id, Request $request)
    {
        $domain = DomainContext::getDomain();
        if (!$domain) {
            return response()->json(['message' => 'Company domain not found.'], 404);
        }

        try {
            $userId = auth()->check() ? (string)auth()->id() : 'accountant';
            $userName = auth()->check() ? auth()->user()->name : 'Accountant';
            $period = $this->periodService->closePeriod((int)$id, $domain->domainUuid, $userId, $userName);

            return response()->json([
                'message' => "Accounting period '{$period->period_name}' closed successfully.",
                'data' => $period,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reopen($id, Request $request)
    {
        $domain = DomainContext::getDomain();
        if (!$domain) {
            return response()->json(['message' => 'Company domain not found.'], 404);
        }

        $reason = $request->input('reason', '');
        if (empty(trim($reason))) {
            return response()->json(['message' => 'A valid business reason is required to reopen an accounting period.'], 422);
        }

        try {
            $userId = auth()->check() ? (string)auth()->id() : 'accountant';
            $userName = auth()->check() ? auth()->user()->name : 'Accountant';
            $period = $this->periodService->reopenPeriod((int)$id, $domain->domainUuid, $reason, $userId, $userName);

            return response()->json([
                'message' => "Accounting period '{$period->period_name}' reopened successfully.",
                'data' => $period,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
