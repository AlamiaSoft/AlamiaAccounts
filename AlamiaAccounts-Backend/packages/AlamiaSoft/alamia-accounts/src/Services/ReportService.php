<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\JournalEntry as JournalEntryModel;
use Abivia\Ledger\Http\Controllers\ReportController;
use Abivia\Ledger\Messages\Report;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Carbon\Carbon;
use Exception;

class ReportService
{
    protected ReportController $reportController;

    public function __construct()
    {
        $this->reportController = new ReportController();
    }

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
     * Get Trial Balance.
     * Uses Abivia Ledger's ReportController if possible, or falls back to custom calculation.
     */
    public function getTrialBalance(string $asOfDate, string $currency = 'USD'): array
    {
        // Try using Ledger's native report first
        try {
            $request = new Report();
            $request->name = 'trialBalance';
            $request->currency = $currency;
            $request->toDate = new Carbon($asOfDate);
            
            $response = $this->reportController->generate($request);
            // If response is useful, parse it. 
            // For now, let's stick to the custom implementation from AlamiaAccountingService 
            // as it returns a specific array format the user might expect.
        } catch (\Exception $e) {
            // Fallback
        }

        $currentDomain = $this->getCurrentDomain();
        $accounts = LedgerAccount::where('domainUuid', $currentDomain->domainUuid)->get();
        $trialBalance = [];
        
        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->code, $asOfDate, $currency);
            
            if ($balance != 0) {
                $trialBalance[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->names->first()->name ?? $account->code,
                    'debit' => $balance > 0 ? $balance : 0,
                    'credit' => $balance < 0 ? abs($balance) : 0,
                ];
            }
        }
        
        return $trialBalance;
    }

    public function getProfitAndLoss(string $fromDate, string $toDate, string $currency = 'USD'): array
    {
        // Using custom implementation from AlamiaAccountingService
        // We need to ensure 'category' is populated in accounts.
        
        // Get accounts for current domain via pivot table
        $currentDomain = $this->getCurrentDomain();
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);
        
        $incomeAccounts = LedgerAccount::where('category', 'revenue')
            ->whereIn('ledgerUuid', $accountUuids)
            ->get();
        $expenseAccounts = LedgerAccount::where('category', 'expense')
            ->whereIn('ledgerUuid', $accountUuids)
            ->get();
        
        $income = [];
        $totalIncome = 0;
        
        foreach ($incomeAccounts as $account) {
            $balance = $this->getAccountBalanceForPeriod($account->code, $fromDate, $toDate, $currency);
            if ($balance != 0) {
                $income[] = [
                    'account_name' => $account->names->first()->name ?? $account->code,
                    'amount' => abs($balance),
                ];
                $totalIncome += abs($balance);
            }
        }
        
        $expenses = [];
        $totalExpenses = 0;
        
        foreach ($expenseAccounts as $account) {
            $balance = $this->getAccountBalanceForPeriod($account->code, $fromDate, $toDate, $currency);
            if ($balance != 0) {
                $expenses[] = [
                    'account_name' => $account->names->first()->name ?? $account->code,
                    'amount' => abs($balance),
                ];
                $totalExpenses += abs($balance);
            }
        }
        
        return [
            'income' => $income,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $totalIncome - $totalExpenses,
        ];
    }

    public function getAccountLedger(string $accountCode, string $fromDate, string $toDate, string $currency = 'USD'): array
    {
        $currentDomain = $this->getCurrentDomain();
        $account = LedgerAccount::where('code', $accountCode)
            ->where('domainUuid', $currentDomain->domainUuid)
            ->first();

        if (!$account) {
            throw new Exception("Account with code {$accountCode} not found");
        }

        // 1. Calculate Opening Balance
        $asOfDateForOpening = Carbon::parse($fromDate)->subDay()->toDateString();
        $openingBalance = $this->getAccountBalance($accountCode, $asOfDateForOpening, $currency);

        // 2. Fetch Transactions
        $transactions = \DB::table('ledger_journal_details')
            ->join('ledger_entries', 'ledger_journal_details.journalEntryId', '=', 'ledger_entries.journalEntryId')
            ->where('ledger_entries.currency', $currency)
            ->where('ledger_journal_details.ledgerUuid', $account->ledgerUuid)
            ->whereBetween('ledger_entries.transDate', [$fromDate, $toDate])
            ->select(
                'ledger_entries.transDate as date',
                'ledger_entries.reference',
                'ledger_entries.description',
                'ledger_journal_details.amount'
            )
            ->orderBy('ledger_entries.transDate')
            ->orderBy('ledger_entries.journalEntryId')
            ->get();

        // 3. Process Transactions with Running Balance
        $ledgerEntries = [];
        $runningBalance = $openingBalance;

        foreach ($transactions as $tx) {
            $amount = (float)$tx->amount;
            $runningBalance += $amount;
            
            $ledgerEntries[] = [
                'date' => $tx->date,
                'reference' => $tx->reference,
                'description' => $tx->description,
                'debit' => $amount > 0 ? $amount : 0,
                'credit' => $amount < 0 ? abs($amount) : 0,
                'balance' => $runningBalance
            ];
        }

        return [
            'account' => [
                'code' => $account->code,
                'name' => $account->names->first()->name ?? $account->code,
            ],
            'opening_balance' => $openingBalance,
            'entries' => $ledgerEntries,
            'closing_balance' => $runningBalance,
            'total_debit' => array_sum(array_column($ledgerEntries, 'debit')),
            'total_credit' => array_sum(array_column($ledgerEntries, 'credit')),
        ];
    }

    public function getBalanceSheet(string $asOfDate, string $currency = 'USD'): array
    {
        $assets = $this->getAccountsByCategory('asset', $asOfDate, $currency);
        $liabilities = $this->getAccountsByCategory('liability', $asOfDate, $currency);
        $equity = $this->getAccountsByCategory('equity', $asOfDate, $currency);
        
        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => array_sum(array_column($assets, 'amount')),
            'total_liabilities_equity' => array_sum(array_column($liabilities, 'amount')) + array_sum(array_column($equity, 'amount')),
        ];
    }

    private function getAccountBalance(string $accountCode, string $asOfDate = null, string $currency = 'USD'): float
    {
        $currentDomain = $this->getCurrentDomain();
        
        $account = LedgerAccount::where('code', $accountCode)
            ->where('domainUuid', $currentDomain->domainUuid)
            ->first();
        if (!$account) {
            return 0;
        }
        
        // Simplified balance calculation
        // In reality, we should query JournalDetails joined with JournalEntries
        // This is a placeholder logic based on the user's existing service
        // We need to actually implement the query here or it will return 0.
        
        $query = JournalEntryModel::where('currency', $currency);
        if ($asOfDate) {
            $query->where('transDate', '<=', $asOfDate); // Note: field is transDate in Ledger
        }
        
        // This requires joining with details.
        // Let's try to do a real query.
        
        $balance = \DB::table('ledger_journal_details')
            ->join('ledger_entries', 'ledger_journal_details.journalEntryId', '=', 'ledger_entries.journalEntryId')
            ->where('ledger_entries.currency', $currency)
            ->where('ledger_journal_details.ledgerUuid', $account->ledgerUuid)
            ->when($asOfDate, function ($q) use ($asOfDate) {
                return $q->where('ledger_entries.transDate', '<=', $asOfDate);
            })
            ->sum('ledger_journal_details.amount'); // Assuming amount is signed
            
        return $balance ?? 0;
    }

    private function getAccountBalanceForPeriod(string $accountCode, string $fromDate, string $toDate, string $currency = 'USD'): float
    {
        $currentDomain = $this->getCurrentDomain();
        
        $account = LedgerAccount::where('code', $accountCode)
            ->where('domainUuid', $currentDomain->domainUuid)
            ->first();
        if (!$account) {
            return 0;
        }

        $balance = \DB::table('ledger_journal_details')
            ->join('ledger_entries', 'ledger_journal_details.journalEntryId', '=', 'ledger_entries.journalEntryId')
            ->where('ledger_entries.currency', $currency)
            ->where('ledger_journal_details.ledgerUuid', $account->ledgerUuid)
            ->whereBetween('ledger_entries.transDate', [$fromDate, $toDate])
            ->sum('ledger_journal_details.amount');
            
        return $balance ?? 0;
    }

    private function getAccountsByCategory(string $category, string $asOfDate, string $currency = 'USD'): array
    {
        $currentDomain = $this->getCurrentDomain();
        
        $accounts = LedgerAccount::where('category', $category)
            ->where('domainUuid', $currentDomain->domainUuid)
            ->get();
        $result = [];
        
        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->code, $asOfDate, $currency);
            if ($balance != 0) {
                $result[] = [
                    'account_name' => $account->names->first()->name ?? $account->code,
                    'amount' => abs($balance),
                ];
            }
        }
        
        return $result;
    }
}
