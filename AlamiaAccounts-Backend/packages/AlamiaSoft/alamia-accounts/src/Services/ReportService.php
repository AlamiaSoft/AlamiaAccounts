<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\JournalEntry as JournalEntryModel;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
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
     * Get Profit and Loss Statement for a period.
     */
    public function getProfitAndLoss(string $fromDate, string $toDate, string $currency = 'PKR'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        // Fetch leaf accounts
        $accounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('category', false)
            ->where('code', '!=', '')
            ->with('names')
            ->orderBy('code')
            ->get();

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

            // Revenue: Credit accounts (excluding Liabilities 2xxx and Equity 51xx/52xx/53xx)
            $isEquity = str_starts_with($account->code, '51') || str_starts_with($account->code, '52') || str_starts_with($account->code, '53') || $account->code === '3000';
            $isLiability = str_starts_with($account->code, '2');

            if ($account->credit && !$isEquity && !$isLiability) {
                $amount = abs($balance);
                $totalIncome += $amount;
                $income[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amount, 2),
                ];
            }
            // Expenses: Debit accounts starting with 4
            elseif ($account->debit && str_starts_with($account->code, '4')) {
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

        $accounts = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('category', false)
            ->where('code', '!=', '')
            ->with('names')
            ->orderBy('code')
            ->get();

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

            // Assets (1xxx)
            if (str_starts_with($account->code, '1')) {
                $amt = $balance;
                $totalAssets += $amt;
                $assets[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amt, 2),
                ];
            }
            // Liabilities (2xxx)
            elseif (str_starts_with($account->code, '2')) {
                $amt = abs($balance);
                $totalLiabilities += $amt;
                $liabilities[] = [
                    'account_code' => $account->code,
                    'account_name' => $name,
                    'amount' => round($amt, 2),
                ];
            }
            // Equity (51xx, 52xx, or 3xxx if equity)
            elseif (str_starts_with($account->code, '51') || str_starts_with($account->code, '52') || str_starts_with($account->code, '53')) {
                $amt = abs($balance);
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

            $rows[] = [
                'code' => $cust->code,
                'name' => $name,
                'total_debit' => round($debitMovements, 2),
                'total_credit' => round($creditMovements, 2),
                'balance' => $balance,
            ];

            $totalDebit += $debitMovements;
            $totalCredit += $creditMovements;
            $totalBalance += $balance;
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'customers' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'total_balance' => round($totalBalance, 2),
            'total_receivables' => round($totalBalance, 2),
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

            $rows[] = [
                'code' => $sup->code,
                'name' => $name,
                'total_debit' => round($debitMovements, 2),
                'total_credit' => round($creditMovements, 2),
                'balance' => $balance,
            ];

            $totalDebit += $debitMovements;
            $totalCredit += $creditMovements;
            $totalBalance += $balance;
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'suppliers' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($creditMovements, 2),
            'total_balance' => round($totalBalance, 2),
            'total_payables' => round($totalBalance, 2),
        ];
    }
}
