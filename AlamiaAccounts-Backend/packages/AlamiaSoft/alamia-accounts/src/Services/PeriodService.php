<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Models\AccountingPeriod;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;

class PeriodService
{
    /**
     * Auto-initialize 12 monthly periods for a fiscal year.
     */
    public function initializeFiscalYear(string $domainUuid, int $fiscalYear, int $startMonth = 1): Collection
    {
        $periods = collect();

        for ($i = 0; $i < 12; $i++) {
            $month = (($startMonth - 1 + $i) % 12) + 1;
            $year = $fiscalYear + (int)(($startMonth - 1 + $i) / 12);
            
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();
            $periodNumber = $i + 1;
            $periodName = $startDate->format('F Y'); // e.g. "January 2026"

            $period = AccountingPeriod::firstOrCreate(
                [
                    'domain_uuid' => $domainUuid,
                    'fiscal_year' => $fiscalYear,
                    'period_number' => $periodNumber,
                ],
                [
                    'period_name' => $periodName,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'status' => 'open',
                ]
            );

            $periods->push($period);
        }

        return $periods;
    }

    /**
     * Get periods for the current domain.
     */
    public function getPeriods(string $domainUuid, ?int $fiscalYear = null): Collection
    {
        $year = $fiscalYear ?? Carbon::now()->year;
        $periods = AccountingPeriod::where('domain_uuid', $domainUuid)
            ->where('fiscal_year', $year)
            ->orderBy('start_date')
            ->get();

        if ($periods->isEmpty()) {
            $periods = $this->initializeFiscalYear($domainUuid, $year);
        }

        return $periods;
    }

    /**
     * Check whether posting a transaction on the given date is permitted.
     * Throws an exception if the transaction falls into a closed accounting period.
     */
    public function validatePostingDate(string $domainUuid, string $transDate): void
    {
        $date = Carbon::parse($transDate)->toDateString();
        $year = Carbon::parse($transDate)->year;

        // Ensure fiscal year is initialized
        $hasPeriods = AccountingPeriod::where('domain_uuid', $domainUuid)
            ->where('fiscal_year', $year)
            ->exists();

        if (!$hasPeriods) {
            $this->initializeFiscalYear($domainUuid, $year);
        }

        // Find matching period
        $period = AccountingPeriod::where('domain_uuid', $domainUuid)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($period && $period->isClosed()) {
            throw new Exception("Accounting period '{$period->period_name}' is closed. Posting transactions into closed periods is not permitted.");
        }
    }

    /**
     * Close an accounting period.
     */
    public function closePeriod(int $periodId, string $domainUuid, string $userId, ?string $userName = null): AccountingPeriod
    {
        $period = AccountingPeriod::where('id', $periodId)
            ->where('domain_uuid', $domainUuid)
            ->firstOrFail();

        $period->status = 'closed';
        $period->closed_at = now();
        $period->closed_by = $userId;
        $period->save();

        AccountingAuditTrail::record(
            $domainUuid,
            'CLOSE_PERIOD',
            'period',
            $period->period_name,
            [
                'period_id' => $period->id,
                'fiscal_year' => $period->fiscal_year,
                'period_number' => $period->period_number,
                'closed_at' => $period->closed_at->toIso8601String(),
            ],
            $userId,
            $userName
        );

        return $period;
    }

    /**
     * Reopen an accounting period (requires authorized reason).
     */
    public function reopenPeriod(int $periodId, string $domainUuid, string $reason, string $userId, ?string $userName = null): AccountingPeriod
    {
        if (empty(trim($reason))) {
            throw new Exception("A documented business reason is required to reopen a closed accounting period.");
        }

        $period = AccountingPeriod::where('id', $periodId)
            ->where('domain_uuid', $domainUuid)
            ->firstOrFail();

        $period->status = 'open';
        $period->reopened_at = now();
        $period->reopened_by = $userId;
        $period->reopen_reason = $reason;
        $period->save();

        AccountingAuditTrail::record(
            $domainUuid,
            'REOPEN_PERIOD',
            'period',
            $period->period_name,
            [
                'period_id' => $period->id,
                'fiscal_year' => $period->fiscal_year,
                'reopen_reason' => $reason,
                'reopened_at' => $period->reopened_at->toIso8601String(),
            ],
            $userId,
            $userName
        );

        return $period;
    }
}
