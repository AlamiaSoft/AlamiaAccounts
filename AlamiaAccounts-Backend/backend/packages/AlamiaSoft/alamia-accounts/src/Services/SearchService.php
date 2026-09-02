<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Illuminate\Support\Facades\DB;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\JournalEntry;

class SearchService
{
    /**
     * Search vouchers
     */
    public function searchVouchers(string $query, string $domainCode): array
    {
        return DB::table('journal_entries')
            ->where('domain', $domainCode)
            ->where(function($q) use ($query) {
                $q->where('reference', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }
    
    /**
     * Search accounts
     */
    public function searchAccounts(string $query, string $domainCode): array
    {
        return DB::table('ledger_accounts')
            ->where('domain', $domainCode)
            ->where(function($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            })
            ->orderBy('code')
            ->limit(50)
            ->get()
            ->toArray();
    }
    
    /**
     * Search ledger entries
     */
    public function searchLedgerEntries(string $query, string $domainCode): array
    {
        return DB::table('journal_details')
            ->join('journal_entries', 'journal_details.entry_id', '=', 'journal_entries.entry_id')
            ->join('ledger_accounts', 'journal_details.account_uuid', '=', 'ledger_accounts.account_uuid')
            ->where('journal_entries.domain', $domainCode)
            ->where(function($q) use ($query) {
                $q->where('journal_entries.reference', 'like', "%{$query}%")
                  ->orWhere('ledger_accounts.name', 'like', "%{$query}%")
                  ->orWhere('journal_details.description', 'like', "%{$query}%");
            })
            ->select('journal_details.*', 'journal_entries.reference', 'ledger_accounts.name as account_name')
            ->orderBy('journal_entries.date', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }
    
    /**
     * Global search across all entities
     */
    public function globalSearch(string $query, string $domainCode): array
    {
        return [
            'vouchers' => $this->searchVouchers($query, $domainCode),
            'accounts' => $this->searchAccounts($query, $domainCode),
            'ledger_entries' => $this->searchLedgerEntries($query, $domainCode),
        ];
    }
}
