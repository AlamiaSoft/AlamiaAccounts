<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\AccountingIntegrityCheckpoint;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingIntegrityGuard
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Evaluate all fundamental accounting invariants at a transaction/voucher boundary
     * and record an immutable checkpoint with transition state analysis.
     *
     * @param string $domainUuid The domain uuid where posting occurred
     * @param string $eventType e.g. 'POST_VOUCHER', 'REVERSE_VOUCHER', 'POST_OPENING_BALANCE'
     * @param string|null $voucherReference e.g. 'JV-2026-001', 'SF-2026-0042', 'OB-2026-001'
     * @param string|null $voucherType e.g. 'JV', 'CPV', 'Semester Fee Voucher', 'Airticket Sale Voucher'
     * @param int|null $journalEntryId The underlying journal entry id
     * @param string|null $asOfDate The transaction date (Y-m-d)
     * @param string $currency Default 'PKR'
     * @param array $metadata Extra contextual attributes
     * @return AccountingIntegrityCheckpoint
     */
    public function recordCheckpoint(
        string $domainUuid,
        string $eventType,
        ?string $voucherReference = null,
        ?string $voucherType = null,
        ?int $journalEntryId = null,
        ?string $asOfDate = null,
        string $currency = 'PKR',
        array $metadata = []
    ): AccountingIntegrityCheckpoint {
        $effectiveDate = $asOfDate ?: Carbon::today()->toDateString();
        $violations = [];

        // 1. Invariant: Individual Journal Balance (Dr = Cr) if journal_entry_id provided
        $journalBalanced = true;
        if ($journalEntryId) {
            $journalTotals = DB::table('journal_details')
                ->where('journalEntryId', $journalEntryId)
                ->selectRaw('SUM(CASE WHEN CAST(amount AS DECIMAL(15,2)) > 0 THEN CAST(amount AS DECIMAL(15,2)) ELSE 0 END) as total_debit, SUM(CASE WHEN CAST(amount AS DECIMAL(15,2)) < 0 THEN ABS(CAST(amount AS DECIMAL(15,2))) ELSE 0 END) as total_credit')
                ->first();

            if ($journalTotals) {
                $jDr = round((float)($journalTotals->total_debit ?? 0), 2);
                $jCr = round((float)($journalTotals->total_credit ?? 0), 2);
                if (abs($jDr - $jCr) >= 0.01) {
                    $journalBalanced = false;
                    $violations[] = 'JOURNAL_UNBALANCED';
                }
            }
        }

        // 2. Invariant: Cumulative Trial Balance Invariant (Sum Dr = Sum Cr)
        $tbTotals = DB::table('journal_details')
            ->join('domain_journal_entries', 'journal_details.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $domainUuid)
            ->where('journal_entries.currency', $currency)
            ->whereDate('journal_entries.transDate', '<=', $effectiveDate)
            ->selectRaw('SUM(CASE WHEN CAST(journal_details.amount AS DECIMAL(15,2)) > 0 THEN CAST(journal_details.amount AS DECIMAL(15,2)) ELSE 0 END) as total_debit, SUM(CASE WHEN CAST(journal_details.amount AS DECIMAL(15,2)) < 0 THEN ABS(CAST(journal_details.amount AS DECIMAL(15,2))) ELSE 0 END) as total_credit')
            ->first();

        $tbDebit = round((float)($tbTotals->total_debit ?? 0), 2);
        $tbCredit = round((float)($tbTotals->total_credit ?? 0), 2);
        $tbDiff = round(abs($tbDebit - $tbCredit), 2);
        $tbBalanced = ($tbDiff < 0.01);

        if (!$tbBalanced) {
            $violations[] = 'TRIAL_BALANCE_UNBALANCED';
        }

        // 3. Invariant: Balance Sheet Equation (Assets = Liabilities + Equity + PeriodNetProfit)
        // Ensure domain context is set for ReportService
        $domain = LedgerDomain::where('domainUuid', $domainUuid)->first();
        if ($domain) {
            DomainContext::set($domain->code);
        }

        $bsReport = $this->reportService->getBalanceSheet($effectiveDate, $currency);
        $totalAssets = round((float)($bsReport['total_assets'] ?? 0.0), 2);
        $totalLiabEquity = round((float)($bsReport['total_liabilities_and_equity'] ?? 0.0), 2);
        $bsDiff = round(abs($totalAssets - $totalLiabEquity), 2);
        $bsBalanced = ($bsDiff < 0.01);

        if (!$bsBalanced) {
            $violations[] = 'BALANCE_SHEET_UNBALANCED';
        }

        // Overall Checkpoint Pass Status
        $invariantsPassed = $journalBalanced && $tbBalanced && $bsBalanced;

        // Retrieve Previous Checkpoint for State Transition Analysis
        $previousCheckpoint = AccountingIntegrityCheckpoint::where('domain_uuid', $domainUuid)
            ->latest('id')
            ->first();

        $prevPassed = $previousCheckpoint ? (bool)$previousCheckpoint->invariants_passed : true;

        if ($prevPassed && $invariantsPassed) {
            $transitionState = 'PASS_TO_PASS';
        } elseif ($prevPassed && !$invariantsPassed) {
            $transitionState = 'PASS_TO_FAIL'; // 🚨 First Causal Trigger Event!
        } elseif (!$prevPassed && !$invariantsPassed) {
            $transitionState = 'FAIL_TO_FAIL'; // Imbalance persists
        } else {
            $transitionState = 'FAIL_TO_PASS'; // 🎉 Remediation / Self-Correction
        }

        return AccountingIntegrityCheckpoint::create([
            'domain_uuid' => $domainUuid,
            'event_type' => $eventType,
            'voucher_reference' => $voucherReference,
            'voucher_type' => $voucherType,
            'journal_entry_id' => $journalEntryId,
            'as_of_date' => $effectiveDate,
            'currency' => $currency,
            'trial_balance_debit' => $tbDebit,
            'trial_balance_credit' => $tbCredit,
            'trial_balance_difference' => $tbDiff,
            'assets' => $totalAssets,
            'liabilities' => round((float)($bsReport['total_liabilities'] ?? 0.0), 2),
            'equity' => round((float)($bsReport['total_equity'] ?? 0.0), 2),
            'bs_difference' => $bsDiff,
            'invariants_passed' => $invariantsPassed,
            'transition_state' => $transitionState,
            'violations' => !empty($violations) ? $violations : null,
            'metadata' => !empty($metadata) ? $metadata : null,
            'previous_checkpoint_id' => $previousCheckpoint ? $previousCheckpoint->id : null,
        ]);
    }
}
