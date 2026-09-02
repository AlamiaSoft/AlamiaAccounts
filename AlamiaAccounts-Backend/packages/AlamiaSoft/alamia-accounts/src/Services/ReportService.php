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

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity + $retainedEarnings;

        return [
            'as_of_date' => $asOfDate,
            'currency' => $currency,
            'assets' => $assets,
            'total_assets' => round($totalAssets, 2),
            'liabilities' => $liabilities,
            'total_liabilities' => round($totalLiabilities, 2),
            'equity' => $equity,
            'total_equity' => round($totalEquity, 2),
            'retained_earnings' => round($retainedEarnings, 2),
            'total_liabilities_and_equity' => round($totalLiabilitiesAndEquity, 2),
            'is_balanced' => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
        ];
    }

    /**
     * Get granular statement for an individual account with running balances.
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

        // 1. Calculate Opening Balance before $fromDate
        $asOfDateForOpening = Carbon::parse($fromDate)->subDay()->toDateString();
        $openingBalance = $this->getAccountBalance($accountCode, $asOfDateForOpening, $currency);

        // 2. Fetch Transactions within the period
        $transactions = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_details.ledgerUuid', $account->ledgerUuid)
            ->where('journal_entries.transDate', '>=', Carbon::parse($fromDate)->startOfDay())
            ->where('journal_entries.transDate', '<=', Carbon::parse($toDate)->endOfDay())
            ->select(
                'journal_entries.transDate as date',
                'journal_entries.description',
                'journal_entries.extra',
                'journal_details.amount'
            )
            ->orderBy('journal_entries.transDate')
            ->orderBy('journal_entries.journalEntryId')
            ->get();

        // 3. Compute running balance
        $ledgerEntries = [];
        $runningBalance = $openingBalance;

        foreach ($transactions as $tx) {
            $amount = (float)$tx->amount;
            $runningBalance += $amount;

            $extra = json_decode($tx->extra ?? '', true) ?? [];
            $reference = $extra['reference'] ?? $extra['voucher_number'] ?? '-';

            $ledgerEntries[] = [
                'date' => Carbon::parse($tx->date)->toDateString(),
                'reference' => $reference,
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
     * Compute balance as of a date for an account.
     */
    public function getAccountBalance(string $accountCode, ?string $asOfDate = null, string $currency = 'PKR'): float
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $account = LedgerAccount::where('code', $accountCode)
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        if (!$account) {
            return 0.0;
        }

        $query = DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_details.ledgerUuid', $account->ledgerUuid);

        if ($asOfDate) {
            // Include through the end of the specified date
            $query->where('journal_entries.transDate', '<=', Carbon::parse($asOfDate)->endOfDay());
        }

        return (float)($query->sum('journal_details.amount') ?? 0.0);
    }

    /**
     * Compute net balance movement in an account over a specific date range.
     */
    public function getAccountBalanceForPeriod(string $accountCode, string $fromDate, string $toDate, string $currency = 'PKR'): float
    {
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);

        $account = LedgerAccount::where('code', $accountCode)
            ->whereIn('ledgerUuid', $accountUuids)
            ->first();

        if (!$account) {
            return 0.0;
        }

        return (float)(DB::table('journal_details')
            ->join('journal_entries', 'journal_details.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $currentDomain->domainUuid)
            ->where('journal_entries.currency', $currency)
            ->where('journal_details.ledgerUuid', $account->ledgerUuid)
            ->where('journal_entries.transDate', '>=', Carbon::parse($fromDate)->startOfDay())
            ->where('journal_entries.transDate', '<=', Carbon::parse($toDate)->endOfDay())
            ->sum('journal_details.amount') ?? 0.0);
    }
}
