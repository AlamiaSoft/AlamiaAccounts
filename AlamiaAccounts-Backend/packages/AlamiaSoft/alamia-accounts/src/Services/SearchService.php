<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use Illuminate\Support\Facades\DB;

class SearchService
{
    protected VoucherService $voucherService;
    protected AccountService $accountService;

    public function __construct(?VoucherService $voucherService = null, ?AccountService $accountService = null)
    {
        $this->voucherService = $voucherService ?? app(VoucherService::class);
        $this->accountService = $accountService ?? app(AccountService::class);
    }

    /**
     * Search vouchers for a domain
     */
    public function searchVouchers(string $query, ?string $domainCode = null): array
    {
        if ($domainCode) {
            DomainContext::set($domainCode);
        }

        $allVouchers = $this->voucherService->getJournalEntries();
        $q = strtolower(trim($query));

        if (empty($q)) {
            return [];
        }

        $filtered = $allVouchers->filter(function ($v) use ($q) {
            if (str_contains(strtolower($v['reference'] ?? ''), $q)) return true;
            if (str_contains(strtolower($v['number'] ?? ''), $q)) return true;
            if (str_contains(strtolower($v['description'] ?? ''), $q)) return true;
            if (str_contains(strtolower($v['type'] ?? ''), $q)) return true;
            if (str_contains(strtolower($v['voucher_type'] ?? ''), $q)) return true;
            if (str_contains(strtolower($v['date'] ?? ''), $q)) return true;

            $lines = $v['lineItems'] ?? $v['line_items'] ?? $v['details'] ?? [];
            foreach ($lines as $line) {
                if (str_contains(strtolower($line['account_code'] ?? $line['account'] ?? ''), $q)) return true;
                if (str_contains(strtolower($line['account_name'] ?? $line['raw_name'] ?? ''), $q)) return true;
                if (str_contains(strtolower($line['memo'] ?? $line['description'] ?? ''), $q)) return true;
            }

            return false;
        });

        return $filtered->values()->all();
    }

    /**
     * Search transactions/vouchers by party, contact, organization, or human relationships
     */
    public function searchTransactionsByParty(array $filters, ?string $domainCode = null): array
    {
        if ($domainCode) {
            DomainContext::set($domainCode);
        }

        $allVouchers = $this->voucherService->getJournalEntries();
        $party = strtolower(trim($filters['party'] ?? ''));
        $partyClean = trim(preg_replace('/^(mr\.?|mrs\.?|ms\.?|dr\.?)\s+/i', '', $party));
        $org = strtolower(trim($filters['organization'] ?? ''));
        $orgClean = trim(preg_replace('/\b(ltd\.?|limited|inc\.?|corp\.?|pvt\.?|private)\b/i', '', $org));

        $partyTokens = array_filter(explode(' ', $partyClean), fn($t) => strlen($t) >= 2);
        $orgTokens = array_filter(explode(' ', $orgClean), fn($t) => strlen($t) >= 2);

        if (empty($partyTokens) && empty($orgTokens) && empty($partyClean) && empty($orgClean)) {
            return [];
        }

        $filtered = $allVouchers->filter(function ($v) use ($partyClean, $orgClean, $partyTokens, $orgTokens) {
            $desc = strtolower($v['description'] ?? '');
            $ref = strtolower($v['reference'] ?? '');

            // Exact phrase match in description or reference
            if (!empty($partyClean) && (str_contains($desc, $partyClean) || str_contains($ref, $partyClean))) return true;
            if (!empty($orgClean) && (str_contains($desc, $orgClean) || str_contains($ref, $orgClean))) return true;

            $lines = $v['lineItems'] ?? $v['line_items'] ?? $v['details'] ?? [];
            foreach ($lines as $line) {
                $memo = strtolower($line['memo'] ?? $line['description'] ?? '');
                $accName = strtolower($line['account_name'] ?? $line['raw_name'] ?? '');

                if (!empty($partyClean) && (str_contains($memo, $partyClean) || str_contains($accName, $partyClean))) return true;
                if (!empty($orgClean) && (str_contains($memo, $orgClean) || str_contains($accName, $orgClean))) return true;
            }

            // Party token matching
            if (!empty($partyTokens)) {
                $matched = 0;
                foreach ($partyTokens as $tok) {
                    if (str_contains($desc, $tok)) { $matched++; continue; }
                    foreach ($lines as $line) {
                        $memo = strtolower($line['memo'] ?? '');
                        $accName = strtolower($line['account_name'] ?? '');
                        if (str_contains($memo, $tok) || str_contains($accName, $tok)) {
                            $matched++;
                            break;
                        }
                    }
                }
                if ($matched === count($partyTokens)) {
                    return true;
                }
            }

            return false;
        });

        return $filtered->values()->take(50)->toArray();
    }

    /**
     * Search accounts for a domain
     */
    public function searchAccounts(string $query, ?string $domainCode = null): array
    {
        if ($domainCode) {
            DomainContext::set($domainCode);
        }

        $accounts = collect($this->accountService->getChartOfAccountsFormatted());
        $q = strtolower(trim($query));

        if (empty($q)) {
            return [];
        }

        $filtered = $accounts->filter(function ($acc) use ($q) {
            $code = strtolower($acc['code'] ?? '');
            $name = strtolower($acc['name'] ?? '');
            return str_contains($code, $q) || str_contains($name, $q);
        });

        return $filtered->values()->take(50)->toArray();
    }

    /**
     * Search ledger entries for a domain
     */
    public function searchLedgerEntries(string $query, ?string $domainCode = null): array
    {
        $vouchers = $this->searchVouchers($query, $domainCode);
        $entries = [];

        foreach ($vouchers as $v) {
            $lines = $v['lineItems'] ?? $v['line_items'] ?? $v['details'] ?? [];
            foreach ($lines as $line) {
                $code = $line['account_code'] ?? $line['account'] ?? '';
                $name = $line['account_name'] ?? $line['raw_name'] ?? '';
                $memo = $line['memo'] ?? $line['description'] ?? '';

                $entries[] = [
                    'id' => $line['id'] ?? $v['id'],
                    'entry_id' => $v['id'],
                    'voucher_reference' => $v['reference'],
                    'date' => $v['date'],
                    'account_code' => $code,
                    'account_name' => $name,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'amount' => $line['amount'] ?? max($line['debit'] ?? 0, $line['credit'] ?? 0),
                    'description' => !empty($memo) ? $memo : ($v['description'] ?? ''),
                ];
            }
        }

        return array_slice($entries, 0, 50);
    }

    /**
     * Global search across all entities
     */
    public function globalSearch(string $query, ?string $domainCode = null): array
    {
        return [
            'vouchers' => $this->searchVouchers($query, $domainCode),
            'accounts' => $this->searchAccounts($query, $domainCode),
            'ledger_entries' => $this->searchLedgerEntries($query, $domainCode),
        ];
    }
}
