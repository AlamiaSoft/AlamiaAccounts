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
     * Normalize person contact names by stripping honorifics, pronouns, and cleaning whitespace
     */
    public static function normalizePersonName(string $name): string
    {
        $cleaned = preg_replace('/^(we|i|you|they|he|she|us|our|my|mr\.?|mrs\.?|ms\.?|dr\.?|prof\.?|eng\.?|shk\.?|sheikh|janab|sb\.?|sahib)\s+/i', '', trim($name));
        $cleaned = preg_replace('/\b(we|i|you|they|he|she|us|our|my)\b/i', ' ', $cleaned);
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleaned);
        $result = trim(preg_replace('/\s+/', ' ', $cleaned));
        return (strlen($result) <= 2 && in_array(strtolower($result), ['we', 'us', 'me', 'my', 'he', 'to', 'in', 'on', 'at', 'by'])) ? '' : $result;
    }

    /**
     * Normalize organization names by stripping corporate suffixes and legal identifiers
     */
    public static function normalizeOrganizationName(string $name): string
    {
        $cleaned = preg_replace('/\b(ltd\.?|limited|inc\.?|incorporated|corp\.?|corporation|pvt\.?|private|llc|plc|co\.?|company)\b/i', '', trim($name));
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleaned);
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Search transactions/vouchers by party, contact, organization, or human relationships with relevance scoring
     */
    public function searchTransactionsByParty(array $filters, ?string $domainCode = null): array
    {
        if ($domainCode) {
            DomainContext::set($domainCode);
        }

        $allVouchers = $this->voucherService->getJournalEntries();
        $rawParty = trim($filters['party'] ?? '');
        $rawOrg = trim($filters['organization'] ?? '');
        $direction = strtolower(trim($filters['direction'] ?? ''));
        $dateFrom = $filters['date_from'] ?? ($filters['date_filter']['from'] ?? null);
        $dateTo = $filters['date_to'] ?? ($filters['date_filter']['to'] ?? null);

        $partyClean = strtolower(self::normalizePersonName($rawParty));
        $orgClean = strtolower(self::normalizeOrganizationName($rawOrg));

        $partyTokens = array_filter(explode(' ', $partyClean), fn($t) => strlen($t) >= 2);
        $orgTokens = array_filter(explode(' ', $orgClean), fn($t) => strlen($t) >= 2);

        if (empty($partyTokens) && empty($orgTokens) && empty($partyClean) && empty($orgClean)) {
            return [];
        }

        $scoredVouchers = [];

        foreach ($allVouchers as $v) {
            $vDate = $v['date'] ?? '';
            if (!empty($dateFrom) && !empty($vDate) && $vDate < $dateFrom) {
                continue;
            }
            if (!empty($dateTo) && !empty($vDate) && $vDate > $dateTo) {
                continue;
            }

            $score = 0;
            $matchReasons = [];
            $desc = strtolower($v['description'] ?? '');
            $ref = strtolower($v['reference'] ?? '');
            $vType = strtolower($v['type'] ?? $v['voucher_type'] ?? '');

            $partyMatched = false;
            $orgMatched = false;

            // 1. Exact Party Match in header or lines
            if (!empty($partyClean)) {
                if (str_contains($desc, $partyClean)) {
                    $score += 50;
                    $partyMatched = true;
                    $matchReasons[] = "Party '{$rawParty}' in description";
                }
            }

            // 2. Exact Org Match in header
            if (!empty($orgClean)) {
                if (str_contains($desc, $orgClean)) {
                    $score += 50;
                    $orgMatched = true;
                    $matchReasons[] = "Organization '{$rawOrg}' in description";
                }
            }

            // 3. Line Items / Memos / Subledger inspection
            $lines = $v['lineItems'] ?? $v['line_items'] ?? $v['details'] ?? [];
            foreach ($lines as $line) {
                $memo = strtolower($line['memo'] ?? $line['description'] ?? '');
                $accName = strtolower($line['account_name'] ?? $line['raw_name'] ?? '');

                if (!empty($partyClean) && (str_contains($memo, $partyClean) || str_contains($accName, $partyClean))) {
                    $score += 40;
                    $partyMatched = true;
                    $matchReasons[] = "Party '{$rawParty}' in line item memo";
                }

                if (!empty($orgClean) && (str_contains($memo, $orgClean) || str_contains($accName, $orgClean))) {
                    $score += 40;
                    $orgMatched = true;
                    $matchReasons[] = "Organization '{$rawOrg}' in line item memo";
                }
            }

            // 4. Token Matching Fallback
            if (!$partyMatched && !empty($partyTokens)) {
                $tokHits = 0;
                foreach ($partyTokens as $tok) {
                    if (str_contains($desc, $tok)) { $tokHits++; continue; }
                    foreach ($lines as $line) {
                        $memo = strtolower($line['memo'] ?? '');
                        $accName = strtolower($line['account_name'] ?? '');
                        if (str_contains($memo, $tok) || str_contains($accName, $tok)) {
                            $tokHits++;
                            break;
                        }
                    }
                }
                if ($tokHits === count($partyTokens)) {
                    $score += 25;
                    $partyMatched = true;
                    $matchReasons[] = "Party tokens matched";
                }
            }

            if (!$orgMatched && !empty($orgTokens)) {
                $tokHits = 0;
                foreach ($orgTokens as $tok) {
                    if (str_contains($desc, $tok)) { $tokHits++; continue; }
                    foreach ($lines as $line) {
                        $memo = strtolower($line['memo'] ?? '');
                        $accName = strtolower($line['account_name'] ?? '');
                        if (str_contains($memo, $tok) || str_contains($accName, $tok)) {
                            $tokHits++;
                            break;
                        }
                    }
                }
                if ($tokHits === count($orgTokens)) {
                    $score += 25;
                    $orgMatched = true;
                    $matchReasons[] = "Organization tokens matched";
                }
            }

            // 5. Compound Relationship Bonus
            if (!empty($partyClean) && !empty($orgClean) && $partyMatched && $orgMatched) {
                $score += 60; // Strong relationship bonus
                $matchReasons[] = "Matched both party and organization relationship";
            }

            // 6. Direction Alignment
            if ($score > 0 && !empty($direction)) {
                $isOutgoing = str_starts_with($ref, 'pv') || $vType === 'payment' || str_contains($desc, 'paid') || str_contains($desc, 'payment');
                $isIncoming = str_starts_with($ref, 'rv') || str_starts_with($ref, 'sv') || $vType === 'receipt' || $vType === 'sales' || str_contains($desc, 'received') || str_contains($desc, 'receipt') || str_contains($desc, 'sale');

                if ($direction === 'outgoing' && $isOutgoing) {
                    $score += 30;
                    $matchReasons[] = "Matched outgoing transaction direction";
                } elseif ($direction === 'incoming' && $isIncoming) {
                    $score += 30;
                    $matchReasons[] = "Matched incoming transaction direction";
                }
            }

            if ($score > 0) {
                $v['_score'] = $score;
                $v['_match_reasons'] = array_unique($matchReasons);
                $scoredVouchers[] = $v;
            }
        }

        // Sort descending by score
        usort($scoredVouchers, fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));

        return array_slice($scoredVouchers, 0, 50);
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
