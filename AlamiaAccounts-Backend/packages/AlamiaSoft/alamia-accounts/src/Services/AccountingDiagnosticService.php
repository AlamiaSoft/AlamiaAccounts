<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use AlamiaSoft\AlamiaAccounts\Enums\AccountClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingDiagnosticService
{
    protected ReportService $reportService;
    protected AccountService $accountService;

    public function __construct(ReportService $reportService, AccountService $accountService)
    {
        $this->reportService = $reportService;
        $this->accountService = $accountService;
    }

    protected function getCurrentDomain(): LedgerDomain
    {
        $code = DomainContext::get();
        if (!$code) {
            $domain = LedgerDomain::first();
            if ($domain) {
                DomainContext::set($domain->code);
                return $domain;
            }
            throw new \Exception('No domain found.');
        }
        $domain = LedgerDomain::where('code', $code)->first();
        if (!$domain) {
            throw new \Exception("Domain with code {$code} not found");
        }
        return $domain;
    }

    /**
     * Comprehensive multi-vector diagnostic audit of the Balance Sheet.
     * Identifies exact anomalies, assigns signed effect vectors, tracks evidence footprints,
     * and performs non-overlapping compound discrepancy reconciliation.
     */
    public function diagnoseBalanceSheet(string $asOfDate, string $currency = 'PKR'): array
    {
        $bs = $this->reportService->getBalanceSheet($asOfDate, $currency);

        $totalAssets = (float)($bs['total_assets'] ?? 0.0);
        $totalLiabEquity = (float)($bs['total_liabilities_and_equity'] ?? 0.0);
        $discrepancy = round(abs($totalAssets - $totalLiabEquity), 2);
        $isBalanced = $discrepancy < 0.01;
        $direction = $isBalanced ? 'balanced' : ($totalAssets > $totalLiabEquity ? 'assets_exceed' : 'liabilities_equity_exceed');

        // Execute all P0 Imbalance Forensic Vectors
        $rawFindings = [];

        // Vector 0: Causal State-Transition Timeline Analyzer (First Failing Invariant Checkpoint)
        $causalFindings = $this->auditCausalStateTransition($asOfDate, $currency);
        if (!empty($causalFindings)) {
            $rawFindings = array_merge($rawFindings, $causalFindings);
        }

        // Vector 1: Corrupted / Single-Legged Vouchers (Dr != Cr)
        $unbalancedVouchers = $this->auditUnbalancedVouchers($asOfDate, $currency);
        if (!empty($unbalancedVouchers)) {
            $rawFindings = array_merge($rawFindings, $unbalancedVouchers);
        }

        // Vector 2: Omitted / Unclassified Posting Leaf Accounts with active balances
        $unclassifiedAnomalies = $this->auditOrphanAndUnclassifiedAccounts($asOfDate, $currency);
        if (!empty($unclassifiedAnomalies)) {
            $rawFindings = array_merge($rawFindings, $unclassifiedAnomalies);
        }

        // Vector 3: Opening Balance / Opening Position Mismatch
        $openingMismatch = $this->auditOpeningEquityAndPositionMismatch($asOfDate, $currency);
        if (!empty($openingMismatch)) {
            $rawFindings = array_merge($rawFindings, $openingMismatch);
        }

        // Vector 4: Dynamic Retained Earnings Reconciled Equation Mismatch
        $retainedEarningsDrift = $this->auditDynamicRetainedEarningsMismatch($asOfDate, $currency);
        if (!empty($retainedEarningsDrift)) {
            $rawFindings = array_merge($rawFindings, $retainedEarningsDrift);
        }

        // Vector 5: Account Classification and Tree Aggregation Errors
        $aggregationAnomalies = $this->auditAccountClassificationAndAggregation($asOfDate, $currency);
        if (!empty($aggregationAnomalies)) {
            $rawFindings = array_merge($rawFindings, $aggregationAnomalies);
        }

        // Reconcile findings against the mathematical discrepancy using non-overlapping evidence subsets
        $reconciliation = $this->reconcileDiscrepancyNonOverlapping($rawFindings, $discrepancy, $isBalanced, $direction);
        $findings = $reconciliation['findings'];
        $diagnosis = $reconciliation['diagnosis'];

        // Compile synthesized accountant summary and actionable remediation guidance
        $summaryText = $this->compileSummaryText($totalAssets, $totalLiabEquity, $discrepancy, $direction, $findings, $diagnosis);
        $guidanceSteps = $this->compileGuidanceSteps($findings, $discrepancy, $direction, $diagnosis);

        return [
            'is_balanced' => $isBalanced,
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'total_assets' => $totalAssets,
            'total_liabilities_and_equity' => $totalLiabEquity,
            'discrepancy' => $discrepancy,
            'direction' => $direction,
            'findings_count' => count($findings),
            'anomalies_count' => count($findings), // Backwards compatibility alias
            'findings' => $findings,
            'anomalies' => $findings, // Backwards compatibility alias
            'diagnosis' => $diagnosis,
            'summary_text' => $summaryText,
            'step_by_step_guidance' => $guidanceSteps,
        ];
    }

    /**
     * Vector 0: Audit transaction-level invariant checkpoints to identify the exact
     * first causal state transition event (PASS -> FAIL) that introduced the imbalance.
     */
    public function auditCausalStateTransition(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();

        // Retrieve chronological checkpoints for this domain up to asOfDate
        $checkpoints = \AlamiaSoft\AlamiaAccounts\Models\AccountingIntegrityCheckpoint::where('domain_uuid', $currentDomain->domainUuid)
            ->whereDate('as_of_date', '<=', $asOfDate)
            ->orderBy('id', 'asc')
            ->get();

        if ($checkpoints->isEmpty()) {
            // Auto-backfill checkpoints chronologically for existing historical vouchers
            $guard = app(AccountingIntegrityGuard::class);
            $domainEntries = DB::table('domain_journal_entries')
                ->join('journal_entries', 'domain_journal_entries.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->orderBy('journal_entries.transDate', 'asc')
                ->orderBy('journal_entries.journalEntryId', 'asc')
                ->select(
                    'journal_entries.journalEntryId',
                    'journal_entries.transDate',
                    'journal_entries.description',
                    'journal_entries.extra'
                )
                ->get();

            foreach ($domainEntries as $entry) {
                $extra = json_decode($entry->extra ?? '', true) ?: [];
                $vchRef = $extra['reference'] ?? ($extra['voucher_number'] ?? ('JV-' . $entry->journalEntryId));
                $vchType = !empty($extra['voucher_type']) ? ucfirst($extra['voucher_type']) : 'Journal Voucher';
                $transDate = Carbon::parse($entry->transDate)->toDateString();

                $guard->recordCheckpoint(
                    $currentDomain->domainUuid,
                    'POST_VOUCHER',
                    $vchRef,
                    $vchType,
                    $entry->journalEntryId,
                    $transDate,
                    $currency,
                    ['description' => $entry->description]
                );
            }

            // Re-fetch checkpoints
            $checkpoints = \AlamiaSoft\AlamiaAccounts\Models\AccountingIntegrityCheckpoint::where('domain_uuid', $currentDomain->domainUuid)
                ->whereDate('as_of_date', '<=', $asOfDate)
                ->orderBy('id', 'asc')
                ->get();
        }

        if ($checkpoints->isEmpty()) {
            return [];
        }

        // Find the first checkpoint where invariants_passed == false
        $firstFailing = $checkpoints->firstWhere('invariants_passed', false);
        if (!$firstFailing) {
            return [];
        }

        $vchRef = $firstFailing->voucher_reference ?? ("JV-" . ($firstFailing->journal_entry_id ?? $firstFailing->id));
        if (is_numeric($vchRef)) {
            $vchRef = "JV-{$vchRef}";
        }
        $vchType = $firstFailing->voucher_type ?? 'Journal Voucher';
        $meta = is_array($firstFailing->metadata) ? $firstFailing->metadata : (json_decode($firstFailing->metadata ?? '', true) ?: []);
        $desc = $meta['description'] ?? '';
        $descSnippet = !empty($desc) ? " ({$desc})" : "";

        $diff = (float)$firstFailing->bs_difference;
        if ($diff <= 0.0) {
            $diff = (float)$firstFailing->trial_balance_difference;
        }

        $findings = [];
        $findings[] = [
            'id' => "CAUSAL-TRANSITION-{$firstFailing->id}",
            'vector' => 'CAUSAL_STATE_TRANSITION',
            'type' => 'FIRST_CAUSAL_STATE_TRANSITION',
            'title' => "First Causal Imbalance Transition at {$vchType} {$vchRef}" . (!empty($desc) ? " - {$desc}" : ""),
            'severity' => 'critical',
            'amount' => $diff,
            'impact_amount' => $diff,
            'signed_effect' => ($firstFailing->assets > ($firstFailing->liabilities + $firstFailing->equity)) ? 'assets_plus' : 'liabilities_plus',
            'voucher_reference' => $vchRef,
            'voucher_type' => $vchType,
            'journal_entry_id' => $firstFailing->journal_entry_id,
            'narration' => $desc,
            'event_type' => $firstFailing->event_type,
            'occurred_at' => $firstFailing->created_at ? $firstFailing->created_at->toDateTimeString() : null,
            'evidence_keys' => ['voucher_' . $vchRef],
            'description' => "The Balance Sheet became unbalanced immediately after {$vchType} '{$vchRef}'{$descSnippet} was posted on {$firstFailing->as_of_date->format('Y-m-d')}. The voucher introduced a Rs. " . number_format($diff, 2) . " debit/credit difference. No earlier checkpoint showed an imbalance. This voucher is therefore the primary causal event, with 100% discrepancy coverage.",
            'violations' => $firstFailing->violations ?? ['BALANCE_SHEET_UNBALANCED'],
            'transition_state' => $firstFailing->transition_state,
            'suggested_fix' => "Inspect or reverse {$vchType} '{$vchRef}'{$descSnippet} to restore double-entry balance.",
            'remediation' => "Inspect or reverse {$vchType} '{$vchRef}'{$descSnippet} to restore double-entry balance.",
            'remediation_link' => "/vouchers?search=" . urlencode($vchRef),
            'fix_target_page' => 'daybook',
        ];

        return $findings;
    }

    /**
     * Vector 1: Audit all journal entries up to asOfDate to detect any single-legged or unbalanced vouchers.
     */
    public function auditUnbalancedVouchers(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();

        $unbalancedEntries = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
            ->groupBy('journal_entries.journalEntryId', 'journal_entries.transDate')
            ->select(
                'journal_entries.journalEntryId',
                'journal_entries.transDate',
                DB::raw('SUM(journal_details.amount) as net_amount'),
                DB::raw('SUM(CASE WHEN journal_details.amount > 0 THEN journal_details.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN journal_details.amount < 0 THEN ABS(journal_details.amount) ELSE 0 END) as total_credit')
            )
            ->havingRaw('ABS(SUM(journal_details.amount)) > 0.009')
            ->get();

        $anomalies = [];

        foreach ($unbalancedEntries as $entry) {
            $extra = DB::table('journal_entries')->where('journalEntryId', $entry->journalEntryId)->value('extra');
            $extraData = $extra ? json_decode($extra, true) : [];
            $voucherRef = $extraData['reference'] ?? $extraData['voucher_number'] ?? "VCH-ID-{$entry->journalEntryId}";

            $variance = round(abs((float)$entry->net_amount), 2);
            $dr = round((float)$entry->total_debit, 2);
            $cr = round((float)$entry->total_credit, 2);

            $anomalies[] = [
                'finding_id' => 'VCH-' . $entry->journalEntryId,
                'vector' => 'UNBALANCED_VOUCHER',
                'code' => 'UNBALANCED_VOUCHER',
                'severity' => 'critical',
                'title' => "Unbalanced Voucher: {$voucherRef}",
                'description' => "Voucher '{$voucherRef}' dated {$entry->transDate} has total debits of Rs. " . number_format($dr, 2) . " and credits of Rs. " . number_format($cr, 2) . ", violating double-entry equilibrium by Rs. " . number_format($variance, 2) . ".",
                'impact_amount' => $variance,
                'effect_vector' => [
                    'delta_assets' => $dr,
                    'delta_liabilities' => 0.0,
                    'delta_equity' => -$cr,
                    'delta_pnl' => 0.0,
                    'net_imbalance_effect' => (float)$entry->net_amount,
                ],
                'evidence_footprint' => [
                    'voucher_ids' => [$voucherRef],
                    'journal_entry_ids' => [(int)$entry->journalEntryId],
                    'account_codes' => [],
                    'account_uuids' => [],
                ],
                'remediation_proposal' => [
                    'action_type' => 'reverse_and_repost',
                    'title' => "Reverse Voucher {$voucherRef} & Re-post Balanced Entry",
                    'description' => "Reverse immutable voucher {$voucherRef} and post a compensating balanced entry to restore ledger equilibrium.",
                    'requires_approval' => true,
                    'proposed_entry' => [
                        'voucher_to_reverse' => $voucherRef,
                        'variance' => $variance,
                        'reason' => "Compensating correction for single-legged imbalance in {$voucherRef}",
                    ],
                    'expected_effect' => "Restores Rs. " . number_format($variance, 2) . " double-entry balance",
                ],
                'affected_accounts' => [],
                'affected_vouchers' => [$voucherRef],
                'suggested_fix' => "Reverse voucher '{$voucherRef}' using the Reverse Voucher workflow in Day Book and post a balanced compensating entry.",
                'fix_target_page' => 'daybook',
            ];
        }

        return $anomalies;
    }

    /**
     * Vector 2: Detect accounts that hold active balances but were excluded from the balance sheet
     * because their class/type could not be resolved (unclassified accounts).
     */
    public function auditOrphanAndUnclassifiedAccounts(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $allAccounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->get();

        $allAccountsMap = $allAccounts->keyBy('ledgerUuid');
        $leafAccounts = $allAccounts->where('category', false)->where('code', '!=', '');

        $anomalies = [];

        foreach ($leafAccounts as $account) {
            $balance = $this->reportService->getAccountBalance($account->code, $asOfDate, $currency);
            if (abs($balance) < 0.0001) {
                continue;
            }

            // Check if account has no explicit class and no parent hierarchy in Chart of Accounts
            $extra = $account->extra;
            if (is_string($extra)) {
                $extra = json_decode($extra, true) ?: [];
            } elseif (is_object($extra)) {
                $extra = (array)$extra;
            }

            $hasExplicitClass = !empty($extra['account_class']);
            $hasParentHierarchy = !empty($account->parentUuid) && $allAccountsMap->has($account->parentUuid);

            if (!$hasExplicitClass && !$hasParentHierarchy) {
                $name = $account->names->first() ? $account->names->first()->name : $account->code;
                $absBalance = round(abs($balance), 2);

                // Suggest best parent based on code range or default asset
                $suggestedParent = str_starts_with($account->code, '2') ? '2100' : (str_starts_with($account->code, '5') ? '5000' : '1100');

                $anomalies[] = [
                    'finding_id' => 'ACC-' . $account->code,
                    'vector' => 'UNCLASSIFIED_ACCOUNT',
                    'code' => 'UNCLASSIFIED_ACCOUNT',
                    'severity' => 'critical',
                    'title' => "Unclassified Account: {$account->code} - {$name}",
                    'description' => "Account '{$account->code}' holds an active balance of Rs. " . number_format($absBalance, 2) . " but has no valid parent category or account type mapping. It was excluded from the Balance Sheet calculation.",
                    'impact_amount' => $absBalance,
                    'effect_vector' => [
                        'delta_assets' => $balance > 0 ? (float)$balance : 0.0,
                        'delta_liabilities' => $balance < 0 ? abs((float)$balance) : 0.0,
                        'delta_equity' => 0.0,
                        'delta_pnl' => 0.0,
                        'net_imbalance_effect' => (float)$balance,
                    ],
                    'evidence_footprint' => [
                        'voucher_ids' => [],
                        'journal_entry_ids' => [],
                        'account_codes' => [$account->code],
                        'account_uuids' => [$account->ledgerUuid],
                    ],
                    'remediation_proposal' => [
                        'action_type' => 'reclassify_account',
                        'title' => "Assign Account {$account->code} to Parent Category {$suggestedParent}",
                        'description' => "Link account {$account->code} to its parent category in Chart of Accounts without posting any journal entries.",
                        'requires_approval' => true,
                        'proposed_mutation' => [
                            'account_code' => $account->code,
                            'target_parent' => $suggestedParent,
                        ],
                        'expected_effect' => "Integrates Rs. " . number_format($absBalance, 2) . " into the Balance Sheet tree",
                    ],
                    'affected_accounts' => [$account->code],
                    'affected_vouchers' => [],
                    'suggested_fix' => "Navigate to Chart of Accounts, select account '{$account->code}', and assign it to an appropriate parent group (e.g., {$suggestedParent}).",
                    'fix_target_page' => 'coa',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Vector 3: Audit Opening Balance position and ensure Assets = Liabilities + Equity at inception.
     */
    public function auditOpeningEquityAndPositionMismatch(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();

        $openingEntries = DB::table('journal_entries')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_entries.opening', 1)
            ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
            ->pluck('journal_entries.journalEntryId');

        if ($openingEntries->isEmpty()) {
            return [];
        }

        $obDetails = DB::table('journal_details')
            ->whereIn('journalEntryId', $openingEntries)
            ->select(
                DB::raw('SUM(amount) as net_amount'),
                DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credit')
            )
            ->first();

        $variance = round(abs((float)($obDetails->net_amount ?? 0.0)), 2);
        if ($variance > 0.009) {
            return [
                [
                    'finding_id' => 'OB-MISMATCH',
                    'vector' => 'OPENING_POSITION_MISMATCH',
                    'code' => 'OPENING_POSITION_MISMATCH',
                    'severity' => 'critical',
                    'title' => "Opening Position Inbalance (Variance: Rs. " . number_format($variance, 2) . ")",
                    'description' => "The posted Opening Balance batch (OB) has total debits of Rs. " . number_format((float)$obDetails->total_debit, 2) . " and credits of Rs. " . number_format((float)$obDetails->total_credit, 2) . ", resulting in an unallocated opening position discrepancy of Rs. " . number_format($variance, 2) . ".",
                    'impact_amount' => $variance,
                    'effect_vector' => [
                        'delta_assets' => (float)$obDetails->total_debit,
                        'delta_liabilities' => 0.0,
                        'delta_equity' => -(float)$obDetails->total_credit,
                        'delta_pnl' => 0.0,
                        'net_imbalance_effect' => (float)($obDetails->net_amount ?? 0.0),
                    ],
                    'evidence_footprint' => [
                        'voucher_ids' => ['OB-BATCH'],
                        'journal_entry_ids' => $openingEntries->toArray(),
                        'account_codes' => [],
                        'account_uuids' => [],
                    ],
                    'remediation_proposal' => [
                        'action_type' => 'post_compensating_entry',
                        'title' => 'Re-allocate Opening Balance Difference to Capital/Retained Earnings',
                        'description' => 'Post a balancing entry between the opening positions and Capital (5100) or Retained Earnings (5200).',
                        'requires_approval' => true,
                        'expected_effect' => 'Eliminates opening balance position variance',
                    ],
                    'affected_accounts' => ['5100', '5200'],
                    'affected_vouchers' => ['OB-BATCH'],
                    'suggested_fix' => "Review Opening Balances and ensure the initial balance sheet difference is fully allocated to Capital (5100) or Retained Earnings (5200).",
                    'fix_target_page' => 'daybook',
                ]
            ];
        }

        return [];
    }

    /**
     * Vector 4: Dynamic Retained Earnings equation reconciliation.
     * Reconciles: Opening Retained Earnings + Current Period Net Profit + Direct Equity Postings = Reported Equity
     */
    public function auditDynamicRetainedEarningsMismatch(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $anomalies = [];

        // Check for direct manual journal entries posted into Retained Earnings (5200)
        $reAccount = LedgerAccount::where('code', '5200')
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        if ($reAccount) {
            $directManualEntriesCount = DB::table('journal_details')
                ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->where('journal_details.ledgerUuid', $reAccount->ledgerUuid)
                ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
                ->count();

            if ($directManualEntriesCount > 0) {
                $manualBalance = (float)(DB::table('journal_details')
                    ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                    ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                    ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                    ->where('journal_entries.currency', $currency)
                    ->where('journal_details.ledgerUuid', $reAccount->ledgerUuid)
                    ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
                    ->sum('journal_details.amount') ?? 0.0);

                if (abs($manualBalance) > 0.01) {
                    $absBal = round(abs($manualBalance), 2);
                    $anomalies[] = [
                        'finding_id' => 'RE-5200-MANUAL',
                        'vector' => 'SUSPICIOUS_DIRECT_RETAINED_EARNINGS_POSTING',
                        'code' => 'SUSPICIOUS_DIRECT_RETAINED_EARNINGS_POSTING',
                        'severity' => 'warning',
                        'title' => "Suspicious Direct Posting in Retained Earnings (Account 5200)",
                        'description' => "Account 5200 (Retained Earnings) contains {$directManualEntriesCount} direct journal entries totaling Rs. " . number_format($absBal, 2) . ". In dynamic reporting, current period net profit is derived automatically from Revenue and Expenses; manual direct postings to 5200 may duplicate net income.",
                        'impact_amount' => $absBal,
                        'effect_vector' => [
                            'delta_assets' => 0.0,
                            'delta_liabilities' => 0.0,
                            'delta_equity' => (float)$manualBalance,
                            'delta_pnl' => 0.0,
                            'net_imbalance_effect' => -(float)$manualBalance,
                        ],
                        'evidence_footprint' => [
                            'voucher_ids' => [],
                            'journal_entry_ids' => [],
                            'account_codes' => ['5200'],
                            'account_uuids' => [$reAccount->ledgerUuid],
                        ],
                        'remediation_proposal' => [
                            'action_type' => 'post_compensating_entry',
                            'title' => 'Reclassify Direct 5200 Movements to Operating Nominal Accounts',
                            'description' => 'Transfer operational entries in Retained Earnings (5200) to appropriate revenue/expense nominal accounts.',
                            'requires_approval' => true,
                            'expected_effect' => 'Prevents duplicate recognition of dynamic net income in equity',
                        ],
                        'affected_accounts' => ['5200'],
                        'affected_vouchers' => [],
                        'suggested_fix' => "Review transactions in General Ledger for Retained Earnings (5200). Operating expenses and revenues should be posted to nominal accounts (4000s/3000s) rather than directly to Retained Earnings.",
                        'fix_target_page' => 'ledger',
                    ];
                }
            }
        }

        // Check for future-dated P&L transactions (transactions dated after asOfDate)
        $futurePnLCount = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->join('ledger_accounts', 'journal_details.ledgerUuid', '=', 'ledger_accounts.ledgerUuid')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_entries.transDate', '>', Carbon::parse($asOfDate)->endOfDay())
            ->where(function ($q) {
                $q->where('ledger_accounts.code', 'like', '3%')
                  ->orWhere('ledger_accounts.code', 'like', '4%');
            })
            ->count();

        if ($futurePnLCount > 0) {
            $futureAmt = (float)(DB::table('journal_details')
                ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                ->join('ledger_accounts', 'journal_details.ledgerUuid', '=', 'ledger_accounts.ledgerUuid')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->where('journal_entries.transDate', '>', Carbon::parse($asOfDate)->endOfDay())
                ->where(function ($q) {
                    $q->where('ledger_accounts.code', 'like', '3%')
                      ->orWhere('ledger_accounts.code', 'like', '4%');
                })
                ->sum('journal_details.amount') ?? 0.0);

            $absFuture = round(abs($futureAmt), 2);
            $anomalies[] = [
                'finding_id' => 'PNL-FUTURE-CUTOFF',
                'vector' => 'FUTURE_DATED_PNL_TRANSACTIONS',
                'code' => 'FUTURE_DATED_PNL_TRANSACTIONS',
                'severity' => 'info',
                'title' => "Future-Dated Revenue / Expense Postings ({$futurePnLCount} entries)",
                'description' => "There are {$futurePnLCount} income or expense entries dated after {$asOfDate} totaling Rs. " . number_format($absFuture, 2) . ". If the corresponding asset/liability was posted prior to {$asOfDate}, an as-of-date cutoff mismatch occurs.",
                'impact_amount' => $absFuture,
                'effect_vector' => [
                    'delta_assets' => 0.0,
                    'delta_liabilities' => 0.0,
                    'delta_equity' => 0.0,
                    'delta_pnl' => (float)$futureAmt,
                    'net_imbalance_effect' => -(float)$futureAmt,
                ],
                'evidence_footprint' => [
                    'voucher_ids' => [],
                    'journal_entry_ids' => [],
                    'account_codes' => [],
                    'account_uuids' => [],
                ],
                'remediation_proposal' => [
                    'action_type' => 'review_ledger',
                    'title' => 'Adjust Statement As-Of Date or Correct Post-Dated Vouchers',
                    'description' => 'Inspect post-dated vouchers in Day Book and adjust transaction dates if they belong to the current period.',
                    'requires_approval' => false,
                    'expected_effect' => 'Aligns P&L period cutoff with balance sheet as-of date',
                ],
                'affected_accounts' => [],
                'affected_vouchers' => [],
                'suggested_fix' => "Check if the Balance Sheet date range should be extended to include future dated vouchers, or review voucher transaction dates in Day Book.",
                'fix_target_page' => 'daybook',
            ];
        }

        return $anomalies;
    }

    /**
     * Backward-compatible alias for auditDynamicRetainedEarningsMismatch
     */
    public function auditRetainedEarningsDrift(string $asOfDate, string $currency = 'PKR'): array
    {
        return $this->auditDynamicRetainedEarningsMismatch($asOfDate, $currency);
    }

    /**
     * Backward-compatible audit of normal balance inversions
     */
    public function auditNormalBalanceInversions(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $allAccounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)->with('names')->get();
        $allAccountsMap = $allAccounts->keyBy('ledgerUuid');
        $anomalies = [];

        foreach ($allAccounts->where('category', false) as $account) {
            $balance = $this->reportService->getAccountBalance($account->code, $asOfDate, $currency);
            if (abs($balance) < 0.01) continue;

            $class = $this->reportService->getAccountClassEnum($account, $allAccountsMap);
            $name = $account->names->first() ? $account->names->first()->name : $account->code;

            if ($class === AccountClass::ASSET && $balance < -0.01 && !str_contains(strtolower($name), 'depreciation') && !str_starts_with($account->code, '159')) {
                $absBal = round(abs($balance), 2);
                $anomalies[] = [
                    'finding_id' => 'INV-' . $account->code,
                    'vector' => 'INVERTED_ASSET_BALANCE',
                    'code' => 'INVERTED_ASSET_BALANCE',
                    'severity' => 'info',
                    'title' => "Inverted Asset Balance: {$account->code} - {$name}",
                    'description' => "Asset account '{$account->code}' has a negative (net Credit) balance of Rs. " . number_format($absBal, 2) . ".",
                    'impact_amount' => $absBal,
                    'effect_vector' => [
                        'delta_assets' => -$absBal,
                        'delta_liabilities' => 0.0,
                        'delta_equity' => 0.0,
                        'delta_pnl' => 0.0,
                        'net_imbalance_effect' => -$absBal,
                    ],
                    'evidence_footprint' => [
                        'voucher_ids' => [],
                        'journal_entry_ids' => [],
                        'account_codes' => [$account->code],
                        'account_uuids' => [$account->ledgerUuid],
                    ],
                    'affected_accounts' => [$account->code],
                    'affected_vouchers' => [],
                    'suggested_fix' => "Review ledger movements for {$account->code} in General Ledger.",
                    'fix_target_page' => 'ledger',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Vector 5: Audit Account Classification and Chart of Accounts Aggregation.
     * Verifies that every leaf account rolls into exactly one category without double-counting.
     */
    public function auditAccountClassificationAndAggregation(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $allAccounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->get();

        $allAccountsMap = $allAccounts->keyBy('ledgerUuid');
        $leafAccounts = $allAccounts->where('category', false)->where('code', '!=', '');

        $anomalies = [];
        $seenAccounts = [];

        foreach ($leafAccounts as $account) {
            if (isset($seenAccounts[$account->code])) {
                $anomalies[] = [
                    'finding_id' => 'DUP-ACC-' . $account->code,
                    'vector' => 'DUPLICATE_ACCOUNT_CODE',
                    'code' => 'DUPLICATE_ACCOUNT_CODE',
                    'severity' => 'critical',
                    'title' => "Duplicate Account Code: {$account->code}",
                    'description' => "Account code '{$account->code}' exists multiple times in the Chart of Accounts, causing duplicate ledger aggregation in financial statements.",
                    'impact_amount' => 0.0,
                    'effect_vector' => [
                        'delta_assets' => 0.0,
                        'delta_liabilities' => 0.0,
                        'delta_equity' => 0.0,
                        'delta_pnl' => 0.0,
                        'net_imbalance_effect' => 0.0,
                    ],
                    'evidence_footprint' => [
                        'voucher_ids' => [],
                        'journal_entry_ids' => [],
                        'account_codes' => [$account->code],
                        'account_uuids' => [$account->ledgerUuid],
                    ],
                    'remediation_proposal' => [
                        'action_type' => 'reclassify_account',
                        'title' => "Merge or Rename Duplicate Account Code {$account->code}",
                        'description' => "Rename or merge the duplicate account code in Chart of Accounts.",
                        'requires_approval' => true,
                        'expected_effect' => "Prevents double counting of {$account->code}",
                    ],
                    'affected_accounts' => [$account->code],
                    'affected_vouchers' => [],
                    'suggested_fix' => "Merge or rename duplicate account '{$account->code}' in Chart of Accounts.",
                    'fix_target_page' => 'coa',
                ];
            }
            $seenAccounts[$account->code] = true;
        }

        return $anomalies;
    }

    /**
     * Non-overlapping compound discrepancy reconciliation algorithm.
     * Prevents double-counting overlapping evidence and discovers minimal explanatory sets.
     */
    protected function reconcileDiscrepancyNonOverlapping(array $rawFindings, float $discrepancy, bool $isBalanced, string $direction): array
    {
        if ($isBalanced || $discrepancy < 0.009) {
            $annotated = [];
            foreach ($rawFindings as $f) {
                $annotated[] = array_merge($f, [
                    'explains_discrepancy' => false,
                    'reconciliation_status' => 'UNRELATED_SIGNAL',
                    'confidence' => 0.50,
                    'causal_rank' => 3,
                ]);
            }
            return [
                'findings' => $annotated,
                'diagnosis' => [
                    'status' => empty($annotated) ? 'BALANCED_CLEAN' : 'BALANCED_WITH_WARNINGS',
                    'confidence' => 1.0,
                    'reconciled_amount' => 0.0,
                    'unreconciled_amount' => 0.0,
                    'primary_cause' => null,
                    'minimal_explanatory_set' => [],
                ]
            ];
        }

        // Pass 1: Single exact finding matching the discrepancy
        foreach ($rawFindings as $idx => $f) {
            $impact = round((float)($f['impact_amount'] ?? 0.0), 2);
            if (abs($impact - $discrepancy) < 0.01) {
                $annotated = [];
                foreach ($rawFindings as $k => $item) {
                    $isTop = ($k === $idx);
                    $annotated[] = array_merge($item, [
                        'explains_discrepancy' => $isTop,
                        'reconciliation_status' => $isTop ? 'EXPLAINS_DISCREPANCY' : 'UNRELATED_SIGNAL',
                        'confidence' => $isTop ? 1.0 : 0.40,
                        'causal_rank' => $isTop ? 1 : 3,
                    ]);
                }

                // Sort: Explanatory first
                usort($annotated, fn($a, $b) => ($b['explains_discrepancy'] ? 1 : 0) <=> ($a['explains_discrepancy'] ? 1 : 0));

                return [
                    'findings' => $annotated,
                    'diagnosis' => [
                        'status' => 'EXPLAINED',
                        'confidence' => 1.0,
                        'reconciled_amount' => $discrepancy,
                        'unreconciled_amount' => 0.0,
                        'primary_cause' => $f['title'] ?? 'Single isolated anomaly',
                        'minimal_explanatory_set' => [$f['finding_id'] ?? 'F-1'],
                    ]
                ];
            }
        }

        // Pass 2: Non-overlapping minimal compound subset matching the discrepancy
        $exactSubset = $this->findNonOverlappingSubsetSum($rawFindings, $discrepancy);
        if (!empty($exactSubset)) {
            $subsetIds = array_flip(array_column($exactSubset, 'finding_id'));
            $annotated = [];
            foreach ($rawFindings as $item) {
                $inSubset = isset($subsetIds[$item['finding_id'] ?? '']);
                $impact = round((float)($item['impact_amount'] ?? 0.0), 2);
                $annotated[] = array_merge($item, [
                    'explains_discrepancy' => $inSubset,
                    'reconciliation_status' => $inSubset ? 'EXPLAINS_PART' : 'UNRELATED_SIGNAL',
                    'confidence' => $inSubset ? round(min(0.95, $impact / $discrepancy), 2) : 0.40,
                    'causal_rank' => $inSubset ? 1 : 3,
                ]);
            }

            usort($annotated, fn($a, $b) => ($b['explains_discrepancy'] ? 1 : 0) <=> ($a['explains_discrepancy'] ? 1 : 0));

            return [
                'findings' => $annotated,
                'diagnosis' => [
                    'status' => 'EXPLAINED',
                    'confidence' => 0.95,
                    'reconciled_amount' => $discrepancy,
                    'unreconciled_amount' => 0.0,
                    'primary_cause' => "Compound variance (" . count($exactSubset) . " non-overlapping factors)",
                    'minimal_explanatory_set' => array_keys($subsetIds),
                ]
            ];
        }

        // Pass 3: Greedy non-overlapping partial subset
        $partialSubset = $this->findGreedyNonOverlappingSubset($rawFindings, $discrepancy);
        $reconciledSum = round(array_sum(array_column($partialSubset, 'impact_amount')), 2);
        $unreconciledAmt = max(0.0, round($discrepancy - $reconciledSum, 2));

        $subsetIds = array_flip(array_column($partialSubset, 'finding_id'));
        $annotated = [];
        foreach ($rawFindings as $item) {
            $inSubset = isset($subsetIds[$item['finding_id'] ?? '']);
            $impact = round((float)($item['impact_amount'] ?? 0.0), 2);
            $annotated[] = array_merge($item, [
                'explains_discrepancy' => false,
                'reconciliation_status' => $inSubset ? 'PARTIALLY_EXPLAINS' : 'UNRELATED_SIGNAL',
                'confidence' => $inSubset ? round(min(0.85, $impact / $discrepancy), 2) : 0.30,
                'causal_rank' => $inSubset ? 2 : 3,
            ]);
        }

        usort($annotated, fn($a, $b) => ($a['causal_rank'] <=> $b['causal_rank']));

        return [
            'findings' => $annotated,
            'diagnosis' => [
                'status' => ($reconciledSum > 0.0) ? 'PARTIALLY_EXPLAINED' : 'UNEXPLAINED',
                'confidence' => ($reconciledSum > 0.0) ? round($reconciledSum / $discrepancy, 2) : 0.0,
                'reconciled_amount' => $reconciledSum,
                'unreconciled_amount' => $unreconciledAmt,
                'primary_cause' => ($reconciledSum > 0.0) ? (($annotated[0]['title'] ?? 'Partial variance') . ' (Partial)') : null,
                'minimal_explanatory_set' => array_keys($subsetIds),
            ]
        ];
    }

    /**
     * Check if two findings have overlapping footprints (shared vouchers, journal entry IDs, or accounts).
     */
    protected function hasFootprintOverlap(array $f1, array $f2): bool
    {
        $fp1 = $f1['evidence_footprint'] ?? [];
        $fp2 = $f2['evidence_footprint'] ?? [];

        // Check voucher references
        if (!empty(array_intersect($fp1['voucher_ids'] ?? [], $fp2['voucher_ids'] ?? []))) {
            return true;
        }

        // Check journal entry IDs
        if (!empty(array_intersect($fp1['journal_entry_ids'] ?? [], $fp2['journal_entry_ids'] ?? []))) {
            return true;
        }

        // Check account codes
        if (!empty(array_intersect($fp1['account_codes'] ?? [], $fp2['account_codes'] ?? []))) {
            return true;
        }

        // Check account UUIDs
        if (!empty(array_intersect($fp1['account_uuids'] ?? [], $fp2['account_uuids'] ?? []))) {
            return true;
        }

        return false;
    }

    /**
     * Search for a non-overlapping subset of findings whose impact sums to the target discrepancy.
     */
    protected function findNonOverlappingSubsetSum(array $findings, float $target): array
    {
        $n = count($findings);
        if ($n < 2 || $n > 15) {
            return [];
        }

        // Evaluate 2-element, 3-element and 4-element combinations
        for ($k = 2; $k <= min(4, $n); $k++) {
            $combo = $this->searchCombinationsRecursive($findings, $k, 0, [], $target);
            if (!empty($combo)) {
                return $combo;
            }
        }

        return [];
    }

    protected function searchCombinationsRecursive(array $items, int $k, int $start, array $current, float $target): array
    {
        if (count($current) === $k) {
            $sum = round(array_sum(array_column($current, 'impact_amount')), 2);
            if (abs($sum - $target) < 0.01) {
                return $current;
            }
            return [];
        }

        for ($i = $start; $i < count($items); $i++) {
            $candidate = $items[$i];
            
            // Check non-overlapping with current chosen set
            $hasOverlap = false;
            foreach ($current as $chosen) {
                if ($this->hasFootprintOverlap($candidate, $chosen)) {
                    $hasOverlap = true;
                    break;
                }
            }

            if ($hasOverlap) {
                continue;
            }

            $result = $this->searchCombinationsRecursive($items, $k, $i + 1, array_merge($current, [$candidate]), $target);
            if (!empty($result)) {
                return $result;
            }
        }

        return [];
    }

    protected function findGreedyNonOverlappingSubset(array $findings, float $target): array
    {
        // Sort descending by impact
        usort($findings, fn($a, $b) => ($b['impact_amount'] ?? 0) <=> ($a['impact_amount'] ?? 0));

        $selected = [];
        $runningSum = 0.0;

        foreach ($findings as $f) {
            $impact = round((float)($f['impact_amount'] ?? 0.0), 2);
            if ($impact <= 0 || ($runningSum + $impact) > ($target + 0.01)) {
                continue;
            }

            $hasOverlap = false;
            foreach ($selected as $s) {
                if ($this->hasFootprintOverlap($f, $s)) {
                    $hasOverlap = true;
                    break;
                }
            }

            if (!$hasOverlap) {
                $selected[] = $f;
                $runningSum += $impact;
            }
        }

        return $selected;
    }

    /**
     * Separate Layer: Ledger Integrity Audit for healthy and balanced ledgers.
     * Evaluates transaction and accounting integrity without requiring a Balance Sheet imbalance.
     */
    public function auditLedgerIntegrity(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $findings = [];

        // Check 1: Duplicate or Suspicious Duplicate Vouchers (same date, amount, currency within same day)
        $duplicates = DB::table('journal_entries as j1')
            ->join('journal_entries as j2', function ($join) {
                $join->on('j1.transDate', '=', 'j2.transDate')
                     ->on('j1.currency', '=', 'j2.currency')
                     ->on('j1.domainUuid', '=', 'j2.domainUuid')
                     ->whereColumn('j1.journalEntryId', '<', 'j2.journalEntryId');
            })
            ->join('domain_journal_entries as d1', 'j1.journalEntryId', '=', 'd1.journalEntryId')
            ->where('d1.domainUuid', $currentDomain->domainUuid)
            ->where('j1.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
            ->select('j1.journalEntryId as id1', 'j2.journalEntryId as id2', 'j1.transDate', 'j1.extra as extra1', 'j2.extra as extra2')
            ->get();

        foreach ($duplicates as $dup) {
            $extra1 = json_decode($dup->extra1, true) ?: [];
            $extra2 = json_decode($dup->extra2, true) ?: [];
            $ref1 = $extra1['reference'] ?? "VCH-{$dup->id1}";
            $ref2 = $extra2['reference'] ?? "VCH-{$dup->id2}";

            // Check if amounts match exactly
            $amt1 = DB::table('journal_details')->where('journalEntryId', $dup->id1)->where('amount', '>', 0)->sum('amount');
            $amt2 = DB::table('journal_details')->where('journalEntryId', $dup->id2)->where('amount', '>', 0)->sum('amount');

            if ($amt1 > 0 && abs($amt1 - $amt2) < 0.01) {
                $findings[] = [
                    'audit_type' => 'SUSPICIOUS_DUPLICATE_VOUCHERS',
                    'severity' => 'warning',
                    'title' => "Potential Duplicate Voucher: {$ref1} & {$ref2}",
                    'description' => "Vouchers {$ref1} and {$ref2} dated {$dup->transDate} have identical transaction amounts of Rs. " . number_format($amt1, 2) . ". Verify whether this is a duplicated operational posting.",
                    'impact_amount' => round($amt1, 2),
                    'affected_vouchers' => [$ref1, $ref2],
                    'suggested_fix' => "Inspect Day Book to verify if {$ref2} is an unintentional duplicate of {$ref1}.",
                    'fix_target_page' => 'daybook',
                ];
            }
        }

        // Check 2: Abnormal / Inverted Account Balances (AR Credit, AP Debit, Negative Cash)
        $allAccounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)->with('names')->get();
        $allAccountsMap = $allAccounts->keyBy('ledgerUuid');

        foreach ($allAccounts->where('category', false) as $acc) {
            $bal = $this->reportService->getAccountBalance($acc->code, $asOfDate, $currency);
            if (abs($bal) < 0.01) continue;

            $class = $this->reportService->getAccountClassEnum($acc, $allAccountsMap);
            $name = $acc->names->first() ? $acc->names->first()->name : $acc->code;

            // Inverted Asset (Net Credit)
            if ($class === AccountClass::ASSET && $bal < -0.01 && !str_contains(strtolower($name), 'depreciation') && !str_starts_with($acc->code, '159')) {
                $findings[] = [
                    'audit_type' => 'INVERTED_ASSET_BALANCE',
                    'severity' => 'info',
                    'title' => "Inverted Asset Balance: {$acc->code} - {$name}",
                    'description' => "Asset account {$acc->code} has a net Credit balance of Rs. " . number_format(abs($bal), 2) . ", indicating overdrawn funds or unallocated customer credit.",
                    'impact_amount' => round(abs($bal), 2),
                    'affected_accounts' => [$acc->code],
                    'suggested_fix' => "Review ledger movements for {$acc->code} in General Ledger.",
                    'fix_target_page' => 'ledger',
                ];
            }

            // Inverted Liability (Net Debit on AP)
            if ($class === AccountClass::LIABILITY && $bal > 0.01) {
                $findings[] = [
                    'audit_type' => 'INVERTED_LIABILITY_BALANCE',
                    'severity' => 'info',
                    'title' => "Inverted Liability Balance: {$acc->code} - {$name}",
                    'description' => "Liability account {$acc->code} has a net Debit balance of Rs. " . number_format($bal, 2) . ", indicating advance vendor payments or unallocated debit notes.",
                    'impact_amount' => round($bal, 2),
                    'affected_accounts' => [$acc->code],
                    'suggested_fix' => "Review vendor ledger movements for {$acc->code} in Payables Subledger.",
                    'fix_target_page' => 'subledger-ap',
                ];
            }
        }

        // Check 3: Uncleared Suspense Balances
        $suspense = $this->auditSuspenseAndOpeningPosition($asOfDate, $currency);
        if (!empty($suspense)) {
            $findings = array_merge($findings, $suspense);
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'findings_count' => count($findings),
            'findings' => $findings,
            'status' => empty($findings) ? 'CLEAN' : 'AUDIT_SIGNALS_PRESENT',
        ];
    }

    /**
     * Check if unposted suspense accounts exist.
     */
    public function auditSuspenseAndOpeningPosition(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $anomalies = [];

        $suspenseAccounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where(function ($q) {
                $q->where('code', 'like', '99%')
                  ->orWhere('code', 'like', '9000%');
            })
            ->with('names')
            ->get();

        foreach ($suspenseAccounts as $acc) {
            $balance = $this->reportService->getAccountBalance($acc->code, $asOfDate, $currency);
            if (abs($balance) > 0.01) {
                $name = $acc->names->first() ? $acc->names->first()->name : $acc->code;
                $absBal = round(abs($balance), 2);
                $anomalies[] = [
                    'finding_id' => 'SUSP-' . $acc->code,
                    'vector' => 'UNCLEARED_SUSPENSE_ACCOUNT',
                    'code' => 'UNCLEARED_SUSPENSE_ACCOUNT',
                    'severity' => 'warning',
                    'title' => "Uncleared Suspense Account: {$acc->code} - {$name}",
                    'description' => "Suspense account '{$acc->code}' contains an uncleared balance of Rs. " . number_format($absBal, 2) . ". Suspense balances represent unallocated transactions that should be cleared before final statement sign-off.",
                    'impact_amount' => $absBal,
                    'effect_vector' => [
                        'delta_assets' => $balance > 0 ? $balance : 0.0,
                        'delta_liabilities' => $balance < 0 ? abs($balance) : 0.0,
                        'delta_equity' => 0.0,
                        'delta_pnl' => 0.0,
                        'net_imbalance_effect' => 0.0,
                    ],
                    'evidence_footprint' => [
                        'voucher_ids' => [],
                        'journal_entry_ids' => [],
                        'account_codes' => [$acc->code],
                        'account_uuids' => [$acc->ledgerUuid],
                    ],
                    'remediation_proposal' => [
                        'action_type' => 'post_compensating_entry',
                        'title' => "Reclassify Suspense Account {$acc->code} to Permanent Account",
                        'description' => "Post a reclassification journal entry from suspense account {$acc->code} to the appropriate Asset, Liability, or Expense account.",
                        'requires_approval' => true,
                        'expected_effect' => "Clears suspense balance of Rs. " . number_format($absBal, 2) . " to 0.00",
                    ],
                    'affected_accounts' => [$acc->code],
                    'affected_vouchers' => [],
                    'suggested_fix' => "Clear the balance in account {$acc->code} by posting a reclassification journal entry to the proper Asset, Liability, or Equity account in Journal Voucher Entry.",
                    'fix_target_page' => 'voucher-journal',
                ];
            }
        }

        return $anomalies;
    }

    protected function compileSummaryText(float $assets, float $liabEq, float $diff, string $direction, array $findings, array $diagnosis): string
    {
        if ($direction === 'balanced') {
            if (empty($findings)) {
                return 'The Balance Sheet is in perfect equilibrium. Total Assets exactly match Total Liabilities and Equity (Variance: Rs. 0.00). No anomalies detected.';
            }
            $count = count($findings);
            return "The Balance Sheet is mathematically balanced (Variance: Rs. 0.00), but forensic audit identified {$count} operational signal(s) requiring review.";
        }

        $dirText = $direction === 'assets_exceed'
            ? "Total Assets exceed Total Liabilities & Equity by Rs. " . number_format($diff, 2)
            : "Total Liabilities & Equity exceed Total Assets by Rs. " . number_format($diff, 2);

        $fCount = count($findings);
        if ($diagnosis['status'] === 'EXPLAINED' && !empty($diagnosis['primary_cause'])) {
            return "The Balance Sheet is unbalanced by Rs. " . number_format($diff, 2) . " ({$dirText}). Forensic audit isolated the root cause: {$diagnosis['primary_cause']}.";
        }

        if ($diagnosis['status'] === 'PARTIALLY_EXPLAINED') {
            $recFormatted = number_format($diagnosis['reconciled_amount'], 2);
            $unrecFormatted = number_format($diagnosis['unreconciled_amount'], 2);
            return "The Balance Sheet is unbalanced by Rs. " . number_format($diff, 2) . " ({$dirText}). Identified anomalies explain Rs. {$recFormatted}, leaving Rs. {$unrecFormatted} unreconciled.";
        }

        if ($fCount === 0) {
            return "The Balance Sheet is unbalanced by Rs. " . number_format($diff, 2) . " ({$dirText}). No single-legged vouchers or unclassified accounts match this variance. Review direct equity movements or timing adjustments.";
        }

        return "The Balance Sheet is unbalanced by Rs. " . number_format($diff, 2) . " ({$dirText}). Forensic audit detected {$fCount} potential finding(s), but none directly account for the exact discrepancy amount.";
    }

    protected function compileGuidanceSteps(array $findings, float $diff, string $direction, array $diagnosis): array
    {
        $steps = [];

        foreach ($findings as $index => $finding) {
            $num = $index + 1;
            $reconciliationNote = ($finding['explains_discrepancy'] ?? false)
                ? " [Explains 100% of Discrepancy]"
                : (($finding['reconciliation_status'] ?? '') === 'PARTIALLY_EXPLAINS' || ($finding['reconciliation_status'] ?? '') === 'EXPLAINS_PART'
                    ? " [Partial Impact: Rs. " . number_format($finding['impact_amount'], 2) . "]"
                    : "");

            $fix = $finding['suggested_fix'] ?? ($finding['remediation'] ?? 'Review and correct the transaction.');
            $steps[] = "Step {$num} [{$finding['severity']}]{$reconciliationNote}: {$finding['title']} — {$fix}";
        }

        if (empty($steps)) {
            $steps[] = "Step 1: Check Chart of Accounts to verify all posting accounts belong to a valid parent group.";
            $steps[] = "Step 2: Review General Ledger for Retained Earnings (5200) and Capital (5100).";
            $steps[] = "Step 3: Inspect Day Book for any vouchers dated across accounting periods.";
        }

        return $steps;
    }
}
