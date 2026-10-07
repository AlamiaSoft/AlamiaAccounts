<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\JournalEntry as JournalEntryModel;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use AlamiaSoft\AlamiaAccounts\Enums\AccountClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class ReportService
{
    /**
     * Get the current domain based on DomainContext.
     */
    protected function getCurrentDomain(): LedgerDomain
    {
        $code = DomainContext::get();
        
        if (!$code) {
            // Fallback to first domain if no context set
            $domain = LedgerDomain::first();
            if ($domain) {
                DomainContext::set($domain->code);
                return $domain;
            }
            throw new Exception('No domain found. Please create a company first.');
        }
        
        $domain = LedgerDomain::where('code', $code)->first();
        
        if (!$domain) {
            throw new Exception("Domain with code {$code} not found");
        }
        
        return $domain;
    }

    /**
     * Get Trial Balance as of a specific date.
     * Enforces domain isolation and mathematical balance.
     */
    public function getTrialBalance(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        // Only leaf posting accounts (category == false) have transaction balances
        $accounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('category', false)
            ->where('code', '!=', '')
            ->with('names')
            ->orderBy('code')
            ->get();

        $trialBalance = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->code, $asOfDate, $currency);

            if (abs($balance) > 0.0001) {
                $debit = $balance > 0 ? (float)$balance : 0.0;
                $credit = $balance < 0 ? (float)abs($balance) : 0.0;

                $totalDebit += $debit;
                $totalCredit += $credit;

                $trialBalance[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->names->first()->name ?? $account->code,
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                ];
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'accounts' => $trialBalance,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    /**
     * Resolve the strongly-typed formal accounting class enum
     * by delegating to AccountService domain logic.
     */
    public function getAccountClassEnum(LedgerAccount $account, $allAccountsMap = null): AccountClass
    {
        return app(AccountService::class)->getAccountClassEnum($account, $allAccountsMap);
    }

    /**
     * Resolve the formal accounting class key (asset, liability, equity, revenue, expense).
     */
    public function getAccountClass(LedgerAccount $account, $allAccountsMap = null): string
    {
        return $this->getAccountClassEnum($account, $allAccountsMap)->value;
    }

    /**
     * Get Profit and Loss Statement for a period.
     */
    public function getProfitAndLoss(string $fromDate, string $toDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        // Fetch leaf accounts and full account map for hierarchy traversal
        $allAccountsMap = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->get()
            ->keyBy('ledgerUuid');

        $accounts = $allAccountsMap->where('category', false)->where('code', '!=', '');

        $income = [];
        $totalIncome = 0.0;

        $expenses = [];
        $totalExpenses = 0.0;

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalanceForPeriod($account->code, $fromDate, $toDate, $currency);

            if (abs($balance) < 0.0001) {
                continue;
            }

            $name = $account->names->first()->name ?? $account->code;
            $class = $this->getAccountClassEnum($account, $allAccountsMap);

            if ($class === AccountClass::REVENUE) {
                $amount = abs($balance);
                $totalIncome += $amount;
                $income[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amount, 2),
                ];
            } elseif ($class === AccountClass::EXPENSE) {
                $amount = abs($balance);
                $totalExpenses += $amount;
                $expenses[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amount, 2),
                ];
            }
        }

        $netProfit = $totalIncome - $totalExpenses;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'currency' => $currency,
            'income' => $income,
            'total_income' => round($totalIncome, 2),
            'total_revenue' => round($totalIncome, 2),
            'expenses' => $expenses,
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
        ];
    }

    /**
     * Get Balance Sheet as of a date.
     */
    public function getBalanceSheet(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $allAccountsMap = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->get()
            ->keyBy('ledgerUuid');

        $accounts = $allAccountsMap->where('category', false)->where('code', '!=', '');

        $assets = [];
        $totalAssets = 0.0;

        $liabilities = [];
        $totalLiabilities = 0.0;

        $equity = [];
        $totalEquity = 0.0;

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->code, $asOfDate, $currency);

            if (abs($balance) < 0.0001) {
                continue;
            }

            $name = $account->names->first()->name ?? $account->code;
            $class = $this->getAccountClassEnum($account, $allAccountsMap);

            if ($class === AccountClass::ASSET) {
                $amt = $balance;
                $totalAssets += $amt;
                $assets[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amt, 2),
                ];
            } elseif ($class === AccountClass::LIABILITY) {
                $amt = abs($balance);
                $totalLiabilities += $amt;
                $liabilities[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amt, 2),
                ];
            } elseif ($class === AccountClass::EQUITY) {
                $amt = -$balance;
                $totalEquity += $amt;
                $equity[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amt, 2),
                ];
            }
        }

        // Net income to date also belongs to Equity as retained earnings
        $earliest = '2000-01-01';
        $pnl = $this->getProfitAndLoss($earliest, $asOfDate, $currency);
        $retainedEarnings = $pnl['net_profit'];

        $totalEquityWithRetained = $totalEquity + $retainedEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithRetained;

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'assets' => $assets,
            'total_assets' => round($totalAssets, 2),
            'liabilities' => $liabilities,
            'total_liabilities' => round($totalLiabilities, 2),
            'equity' => $equity,
            'capital_equity' => round($totalEquity, 2),
            'total_equity' => round($totalEquityWithRetained, 2),
            'retained_earnings' => round($retainedEarnings, 2),
            'total_liabilities_and_equity' => round($totalLiabilitiesAndEquity, 2),
            'is_balanced' => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
        ];
    }

    /**
     * Get granular statement for an individual account with running balances.
     */
    /**
     * Get granular statement for an individual account or parent group with running balances.
     */
    public function getAccountLedger(string $accountCode, string $fromDate, string $toDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $account = LedgerAccount::where('code', $accountCode)
            ->whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->first();

        if (!$account) {
            throw new Exception("Account with code {$accountCode} not found in current domain");
        }

        $hasChildren = LedgerAccount::where('parentUuid', $account->ledgerUuid)
            ->whereIn('ledgerUuid', $accountUuids)
            ->exists();
        $isGroup = (bool)$account->category || $hasChildren;
        $targetUuids = [$account->ledgerUuid];
        if ($isGroup) {
            $descendants = $this->getDescendantAccountUuids($account->ledgerUuid, $accountUuids);
            $targetUuids = array_merge($targetUuids, $descendants);
        }

        // 1. Calculate Opening Balance before $fromDate
        $asOfDateForOpening = Carbon::parse($fromDate)->subDay()->toDateString();
        $openingBalance = $this->getAccountBalance($accountCode, $asOfDateForOpening, $currency, $isGroup);

        // 2. Fetch Transactions within the period
        $transactions = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->join('ledger_accounts', 'journal_details.ledgerUuid', '=', 'ledger_accounts.ledgerUuid')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->whereIn('journal_details.ledgerUuid', $targetUuids)
            ->where('journal_entries.transDate', '>=', Carbon::parse($fromDate)->startOfDay())
            ->where('journal_entries.transDate', '<=', Carbon::parse($toDate)->endOfDay())
            ->select(
                'journal_entries.journalEntryId',
                'journal_entries.transDate as date',
                'journal_entries.description',
                'journal_entries.extra',
                'journal_details.amount',
                'ledger_accounts.code as account_code'
            )
            ->orderBy('journal_entries.transDate')
            ->orderBy('journal_entries.journalEntryId')
            ->get();

        // 3. Compute running balance
        $ledgerEntries = [];
        $runningBalance = $openingBalance;

        // Pre-fetch related accounts for entries to detect contra/internal transfers
        $entryIds = $transactions->pluck('journalEntryId')->unique()->toArray();
        $entryAccountCodes = [];
        if (!empty($entryIds)) {
            $rawDetails = DB::table('journal_details')
                ->join('ledger_accounts', 'journal_details.ledgerUuid', '=', 'ledger_accounts.ledgerUuid')
                ->whereIn('journal_details.journalEntryId', $entryIds)
                ->select('journal_details.journalEntryId', 'ledger_accounts.code')
                ->get();
            foreach ($rawDetails as $rd) {
                $entryAccountCodes[$rd->journalEntryId][] = $rd->code;
            }
        }

        // Pre-fetch account names map
        $accNamesMap = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->get()
            ->keyBy('code')
            ->map(function ($a) {
                return $a->names->first() ? $a->names->first()->name : $a->code;
            });

        foreach ($transactions as $tx) {
            $amount = (float)$tx->amount;
            $runningBalance += $amount;

            $extra = json_decode($tx->extra ?? '', true) ?? [];
            $reference = $extra['reference'] ?? $extra['voucher_number'] ?? '-';
            $voucherType = $extra['voucher_type'] ?? null;

            if (!$voucherType) {
                $refUpper = strtoupper($reference);
                if (str_starts_with($refUpper, 'CV') || str_starts_with($refUpper, 'CONTRA')) {
                    $voucherType = 'contra';
                } elseif (str_starts_with($refUpper, 'OB')) {
                    $voucherType = 'opening';
                } elseif (str_starts_with($refUpper, 'PV') || str_starts_with($refUpper, 'PAY')) {
                    $voucherType = 'payment';
                } elseif (str_starts_with($refUpper, 'RV') || str_starts_with($refUpper, 'REC')) {
                    $voucherType = 'receipt';
                } else {
                    // Check if all involved accounts are cash or bank accounts (codes starting with 11)
                    $related = $entryAccountCodes[$tx->journalEntryId] ?? [];
                    $isAllCashOrBank = count($related) >= 2;
                    foreach ($related as $code) {
                        if (!str_starts_with($code, '11')) {
                            $isAllCashOrBank = false;
                            break;
                        }
                    }
                    if ($isAllCashOrBank) {
                        $voucherType = 'contra';
                    } else {
                        $voucherType = $amount > 0 ? 'receipt' : 'payment';
                    }
                }
            }

            $txAccCode = $tx->account_code ?? $accountCode;
            $txAccName = $accNamesMap->get($txAccCode) ?? $txAccCode;

            $ledgerEntries[] = [
                'journal_entry_id' => $tx->journalEntryId,
                'date' => Carbon::parse($tx->date)->toDateString(),
                'reference' => $reference,
                'voucher_type' => $voucherType,
                'account_code' => $txAccCode,
                'account_name' => $txAccName,
                'description' => $tx->description,
                'debit' => $amount > 0 ? round($amount, 2) : 0.0,
                'credit' => $amount < 0 ? round(abs($amount), 2) : 0.0,
                'balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => [
                'code' => $account->code,
                'name' => $account->names->first()->name ?? $account->code,
                'is_group' => $isGroup,
            ],
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'currency' => $currency,
            'opening_balance' => round($openingBalance, 2),
            'entries' => $ledgerEntries,
            'closing_balance' => round($runningBalance, 2),
            'total_debit' => round(array_sum(array_column($ledgerEntries, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($ledgerEntries, 'credit')), 2),
        ];
    }

    /**
     * Compute balance as of a date for an account or category.
     */
    public function getAccountBalance(string $accountCode, ?string $asOfDate = null, string $currency = 'PKR', bool $includeDescendants = false): float
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $account = LedgerAccount::where('code', $accountCode)
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        if (!$account) {
            return 0.0;
        }

        $targetUuids = [$account->ledgerUuid];
        if ($includeDescendants) {
            $hasChildren = LedgerAccount::where('parentUuid', $account->ledgerUuid)
                ->whereIn('ledgerUuid', $accountUuids)
                ->exists();

            if ($account->category || $hasChildren) {
                $descendants = $this->getDescendantAccountUuids($account->ledgerUuid, $accountUuids);
                $targetUuids = array_merge($targetUuids, $descendants);
            }
        }

        $query = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->whereIn('journal_details.ledgerUuid', $targetUuids);

        if ($asOfDate) {
            $query->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay());
        }

        return (float)($query->sum('journal_details.amount') ?? 0.0);
    }

    /**
     * Compute net balance movement in an account over a specific date range.
     */
    public function getAccountBalanceForPeriod(string $accountCode, string $fromDate, string $toDate, string $currency = 'PKR', bool $includeDescendants = false): float
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $account = LedgerAccount::where('code', $accountCode)
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        if (!$account) {
            return 0.0;
        }

        $targetUuids = [$account->ledgerUuid];
        if ($includeDescendants) {
            $hasChildren = LedgerAccount::where('parentUuid', $account->ledgerUuid)
                ->whereIn('ledgerUuid', $accountUuids)
                ->exists();

            if ($account->category || $hasChildren) {
                $descendants = $this->getDescendantAccountUuids($account->ledgerUuid, $accountUuids);
                $targetUuids = array_merge($targetUuids, $descendants);
            }
        }

        return (float)(DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->whereIn('journal_details.ledgerUuid', $targetUuids)
            ->where('journal_entries.transDate', '>=', Carbon::parse($fromDate)->startOfDay())
            ->where('journal_entries.transDate', '<=', Carbon::parse($toDate)->endOfDay())
            ->sum('journal_details.amount') ?? 0.0);
    }

    /**
     * Recursively collect all descendant leaf account UUIDs under a parent UUID.
     */
    public function getDescendantAccountUuids(string $parentUuid, array $domainAccountUuids): array
    {
        $childAccounts = LedgerAccount::where('parentUuid', $parentUuid)
            ->whereIn('ledgerUuid', $domainAccountUuids)
            ->get();

        $uuids = [];
        foreach ($childAccounts as $child) {
            $uuids[] = $child->ledgerUuid;
            $subUuids = $this->getDescendantAccountUuids($child->ledgerUuid, $domainAccountUuids);
            $uuids = array_merge($uuids, $subUuids);
        }
        return array_unique($uuids);
    }

    /**
     * Receivables Subledger Report: customer balances, movements, and reconciliation with Balance Sheet.
     */
    public function getReceivablesReport(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $parentAR = LedgerAccount::where('code', '1200')
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        $customers = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('category', false)
            ->where(function ($q) use ($parentAR) {
                $q->where('code', 'like', '12%');
                if ($parentAR) {
                    $q->orWhere('parentUuid', $parentAR->ledgerUuid);
                }
            })
            ->with('names')
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totalBalance = 0.0;
        $totalAging = ['days_0_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];

        foreach ($customers as $cust) {
            $name = $cust->names->first() ? $cust->names->first()->name : $cust->code;

            // Total sales/invoices (Dr)
            $debitMovements = (float)(DB::table('journal_details')
                ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->where('journal_details.ledgerUuid', $cust->ledgerUuid)
                ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
                ->where('journal_details.amount', '>', 0)
                ->sum('journal_details.amount') ?? 0.0);

            // Total receipts/payments received (Cr)
            $creditMovements = (float)(DB::table('journal_details')
                ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->where('journal_details.ledgerUuid', $cust->ledgerUuid)
                ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
                ->where('journal_details.amount', '<', 0)
                ->sum(DB::raw('ABS(journal_details.amount)')) ?? 0.0);

            $balance = round($debitMovements - $creditMovements, 2);
            $aging = $this->computeAgingBuckets($cust->ledgerUuid, $asOfDate, true, $currency);

            $rows[] = [
                'code' => $cust->code,
                'name' => $name,
                'total_debit' => round($debitMovements, 2),
                'total_credit' => round($creditMovements, 2),
                'balance' => $balance,
                'aging' => $aging,
            ];

            $totalDebit += $debitMovements;
            $totalCredit += $creditMovements;
            $totalBalance += $balance;
            $totalAging['days_0_30'] += $aging['days_0_30'];
            $totalAging['days_31_60'] += $aging['days_31_60'];
            $totalAging['days_61_90'] += $aging['days_61_90'];
            $totalAging['days_over_90'] += $aging['days_over_90'];
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'customers' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'total_balance' => round($totalBalance, 2),
            'total_receivables' => round($totalBalance, 2),
            'aging_summary' => [
                'days_0_30' => round($totalAging['days_0_30'], 2),
                'days_31_60' => round($totalAging['days_31_60'], 2),
                'days_61_90' => round($totalAging['days_61_90'], 2),
                'days_over_90' => round($totalAging['days_over_90'], 2),
                'current_0_30' => round($totalAging['days_0_30'], 2),
                'aging_31_60' => round($totalAging['days_31_60'], 2),
                'aging_61_90' => round($totalAging['days_61_90'], 2),
                'aging_90_plus' => round($totalAging['days_over_90'], 2),
            ],
        ];
    }

    /**
     * Payables Subledger Report: supplier balances, movements, and reconciliation with Balance Sheet.
     */
    public function getPayablesReport(string $asOfDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $parentAP = LedgerAccount::where('code', '2100')
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        $suppliers = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('category', false)
            ->where(function ($q) use ($parentAP) {
                $q->where('code', 'like', '21%');
                if ($parentAP) {
                    $q->orWhere('parentUuid', $parentAP->ledgerUuid);
                }
            })
            ->with('names')
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totalBalance = 0.0;
        $totalAging = ['days_0_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];

        foreach ($suppliers as $sup) {
            $name = $sup->names->first() ? $sup->names->first()->name : $sup->code;

            // Total payments made (Dr)
            $debitMovements = (float)(DB::table('journal_details')
                ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->where('journal_details.ledgerUuid', $sup->ledgerUuid)
                ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
                ->where('journal_details.amount', '>', 0)
                ->sum('journal_details.amount') ?? 0.0);

            // Total purchases/bills owed (Cr)
            $creditMovements = (float)(DB::table('journal_details')
                ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
                ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
                ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
                ->where('journal_entries.currency', $currency)
                ->where('journal_details.ledgerUuid', $sup->ledgerUuid)
                ->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay())
                ->where('journal_details.amount', '<', 0)
                ->sum(DB::raw('ABS(journal_details.amount)')) ?? 0.0);

            // Payable balance: Credit (Owed) - Debit (Paid)
            $balance = round($creditMovements - $debitMovements, 2);
            $aging = $this->computeAgingBuckets($sup->ledgerUuid, $asOfDate, false, $currency);

            $rows[] = [
                'code' => $sup->code,
                'name' => $name,
                'total_debit' => round($debitMovements, 2),
                'total_credit' => round($creditMovements, 2),
                'balance' => $balance,
                'aging' => $aging,
            ];

            $totalDebit += $debitMovements;
            $totalCredit += $creditMovements;
            $totalBalance += $balance;
            $totalAging['days_0_30'] += $aging['days_0_30'];
            $totalAging['days_31_60'] += $aging['days_31_60'];
            $totalAging['days_61_90'] += $aging['days_61_90'];
            $totalAging['days_over_90'] += $aging['days_over_90'];
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'suppliers' => $rows,
            'vendors' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'total_balance' => round($totalBalance, 2),
            'total_payables' => round($totalBalance, 2),
            'aging_summary' => [
                'days_0_30' => round($totalAging['days_0_30'], 2),
                'days_31_60' => round($totalAging['days_31_60'], 2),
                'days_61_90' => round($totalAging['days_61_90'], 2),
                'days_over_90' => round($totalAging['days_over_90'], 2),
                'current_0_30' => round($totalAging['days_0_30'], 2),
                'aging_31_60' => round($totalAging['days_31_60'], 2),
                'aging_61_90' => round($totalAging['days_61_90'], 2),
                'aging_90_plus' => round($totalAging['days_over_90'], 2),
            ],
        ];
    }

    /**
     * Compute 4-bracket aging breakdown for a subledger account.
     */
    protected function computeAgingBuckets(string $ledgerUuid, string $asOfDate, bool $isReceivable = true, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $dateObj = Carbon::parse($asOfDate);
        $d30 = $dateObj->copy()->subDays(30)->startOfDay();
        $d60 = $dateObj->copy()->subDays(60)->startOfDay();
        $d90 = $dateObj->copy()->subDays(90)->startOfDay();

        $rows = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_details.ledgerUuid', $ledgerUuid)
            ->where('journal_entries.transDate', '<=', $dateObj->endOfDay())
            ->select('journal_details.amount', 'journal_entries.transDate')
            ->get();

        $b0_30 = 0.0;
        $b31_60 = 0.0;
        $b61_90 = 0.0;
        $bOver90 = 0.0;

        foreach ($rows as $r) {
            $amt = (float)$r->amount;
            $tDate = Carbon::parse($r->transDate);
            $signedAmt = $isReceivable ? $amt : -$amt;

            if ($tDate >= $d30) {
                $b0_30 += $signedAmt;
            } elseif ($tDate >= $d60) {
                $b31_60 += $signedAmt;
            } elseif ($tDate >= $d90) {
                $b61_90 += $signedAmt;
            } else {
                $bOver90 += $signedAmt;
            }
        }

        return [
            'days_0_30' => round($b0_30, 2),
            'days_31_60' => round($b31_60, 2),
            'days_61_90' => round($b61_90, 2),
            'days_over_90' => round($bOver90, 2),
            'current_0_30' => round($b0_30, 2),
            'aging_31_60' => round($b31_60, 2),
            'aging_61_90' => round($b61_90, 2),
            'aging_90_plus' => round($bOver90, 2),
        ];
    }

    /**
     * Bank Book Report: Chronological statement of banking transactions, inflows, outflows, and running balances.
     */
    public function getBankBook(string $accountCode = 'ALL', string $fromDate = '2024-01-01', string $toDate = '2099-12-31', string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        // 1. Discover all active bank accounts in domain
        $bankParent = LedgerAccount::where('code', '1120')
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        $allBankAccounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('category', false)
            ->where(function ($q) use ($bankParent) {
                $q->where('code', '1120')
                  ->orWhere('code', 'like', '112%')
                  ->orWhere('code', 'like', '113%');
                if ($bankParent) {
                    $q->orWhere('parentUuid', $bankParent->ledgerUuid);
                }
            })
            ->with('names')
            ->orderBy('code')
            ->get();

        $bankAccountsList = [];
        foreach ($allBankAccounts as $bAcc) {
            $curBal = $this->getAccountBalance($bAcc->code, $toDate, $currency);
            $bankAccountsList[] = [
                'code' => $bAcc->code,
                'name' => $bAcc->names->first() ? $bAcc->names->first()->name : $bAcc->code,
                'current_balance' => round($curBal, 2),
            ];
        }

        // 2. Determine target bank account UUIDs
        $targetBankUuids = [];
        $selectedAccountInfo = null;

        if ($accountCode !== 'ALL' && !empty($accountCode)) {
            $matched = $allBankAccounts->firstWhere('code', $accountCode) ?? LedgerAccount::where('code', $accountCode)->whereIn('ledgerUuid', $accountUuids)->with('names')->first();
            if ($matched) {
                $targetBankUuids = [$matched->ledgerUuid];
                $selectedAccountInfo = [
                    'code' => $matched->code,
                    'name' => $matched->names->first() ? $matched->names->first()->name : $matched->code,
                ];
            }
        }

        if (empty($targetBankUuids)) {
            $targetBankUuids = $allBankAccounts->pluck('ledgerUuid')->toArray();
            $selectedAccountInfo = [
                'code' => 'ALL',
                'name' => 'All Bank Accounts',
            ];
        }

        // 3. Compute opening balance before $fromDate
        $asOfDateForOpening = Carbon::parse($fromDate)->subDay()->toDateString();
        $openingBalance = 0.0;
        if ($selectedAccountInfo['code'] === 'ALL') {
            foreach ($allBankAccounts as $bAcc) {
                $openingBalance += $this->getAccountBalance($bAcc->code, $asOfDateForOpening, $currency);
            }
        } else {
            $openingBalance = $this->getAccountBalance($selectedAccountInfo['code'], $asOfDateForOpening, $currency);
        }

        // 4. Fetch bank transactions within period
        $transactions = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->join('ledger_accounts', 'journal_details.ledgerUuid', '=', 'ledger_accounts.ledgerUuid')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->whereIn('journal_details.ledgerUuid', $targetBankUuids)
            ->where('journal_entries.transDate', '>=', Carbon::parse($fromDate)->startOfDay())
            ->where('journal_entries.transDate', '<=', Carbon::parse($toDate)->endOfDay())
            ->select(
                'journal_entries.journalEntryId',
                'journal_entries.transDate as date',
                'journal_entries.description',
                'journal_entries.extra',
                'journal_details.amount',
                'ledger_accounts.code as bank_account_code'
            )
            ->orderBy('journal_entries.transDate')
            ->orderBy('journal_entries.journalEntryId')
            ->get();

        // Pre-fetch all other legs of these entries to identify opposing accounts
        $entryIds = $transactions->pluck('journalEntryId')->unique()->toArray();
        $entryLegs = [];
        if (!empty($entryIds)) {
            $rawLegs = DB::table('journal_details')
                ->join('ledger_accounts', 'journal_details.ledgerUuid', '=', 'ledger_accounts.ledgerUuid')
                ->leftJoin('ledger_names', 'ledger_accounts.ledgerUuid', '=', 'ledger_names.ownerUuid')
                ->whereIn('journal_details.journalEntryId', $entryIds)
                ->select(
                    'journal_details.journalEntryId',
                    'journal_details.ledgerUuid',
                    'journal_details.amount',
                    'ledger_accounts.code as account_code',
                    'ledger_names.name as account_name'
                )
                ->get();
            foreach ($rawLegs as $leg) {
                $entryLegs[$leg->journalEntryId][] = $leg;
            }
        }

        $entries = [];
        $runningBalance = $openingBalance;
        $totalInflow = 0.0;
        $totalOutflow = 0.0;

        foreach ($transactions as $tx) {
            $amount = (float)$tx->amount;
            $runningBalance += $amount;

            $extra = json_decode($tx->extra ?? '', true) ?? [];
            $reference = $extra['reference'] ?? $extra['voucher_number'] ?? "JE-{$tx->journalEntryId}";
            $voucherType = $extra['voucher_type'] ?? null;

            // Find opposing account from other legs
            $legs = $entryLegs[$tx->journalEntryId] ?? [];
            $opposingAccounts = [];
            foreach ($legs as $l) {
                if (!in_array($l->ledgerUuid, $targetBankUuids)) {
                    $opposingAccounts[] = ($l->account_name ? $l->account_name : $l->account_code) . " ({$l->account_code})";
                }
            }
            $opposingText = !empty($opposingAccounts) ? implode(', ', array_unique($opposingAccounts)) : 'General Ledger';

            if (!$voucherType) {
                $refUpper = strtoupper($reference);
                if (str_starts_with($refUpper, 'CV') || str_starts_with($refUpper, 'CONTRA')) {
                    $voucherType = 'contra';
                } elseif (str_starts_with($refUpper, 'OB')) {
                    $voucherType = 'opening';
                } elseif (str_starts_with($refUpper, 'PV') || str_starts_with($refUpper, 'PAY')) {
                    $voucherType = 'payment';
                } elseif (str_starts_with($refUpper, 'RV') || str_starts_with($refUpper, 'REC')) {
                    $voucherType = 'receipt';
                } else {
                    $voucherType = $amount > 0 ? 'receipt' : 'payment';
                }
            }

            $inflow = $amount > 0 ? $amount : 0.0;
            $outflow = $amount < 0 ? abs($amount) : 0.0;

            $totalInflow += $inflow;
            $totalOutflow += $outflow;

            $entries[] = [
                'journal_entry_id' => $tx->journalEntryId,
                'date' => Carbon::parse($tx->date)->toDateString(),
                'reference' => $reference,
                'voucher_type' => $voucherType,
                'bank_account_code' => $tx->bank_account_code,
                'particulars' => $opposingText,
                'description' => $tx->description,
                'deposit' => round($inflow, 2),
                'withdrawal' => round($outflow, 2),
                'balance' => round($runningBalance, 2),
            ];
        }

        return [
            'selected_account' => $selectedAccountInfo,
            'bank_accounts' => $bankAccountsList,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'currency' => $currency,
            'opening_balance' => round($openingBalance, 2),
            'entries' => $entries,
            'total_inflow' => round($totalInflow, 2),
            'total_outflow' => round($totalOutflow, 2),
            'closing_balance' => round($runningBalance, 2),
        ];
    }
}
