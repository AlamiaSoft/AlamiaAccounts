<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\Detail;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Models\JournalEntry as JournalEntryModel;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class VoucherService
{
    protected JournalEntryController $journalController;

    public function __construct()
    {
        $this->journalController = new JournalEntryController();
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
     * Create a journal entry and associate it with the current domain.
     * 
     * @param array $data Entry data
     * @param string|null $domainCode Optional domain code
     * @return JournalEntryModel
     * @throws Exception
     */
    public function createJournalEntry(array $data, ?string $domainCode = null): JournalEntryModel
    {
        // Get the domain
        if ($domainCode) {
            $domain = LedgerDomain::where('code', $domainCode)->firstOrFail();
        } else {
            $domain = $this->getCurrentDomain();
        }

        $entries = $data['entries'] ?? $data['details'] ?? [];
        if (empty($entries) || count($entries) < 2) {
            throw new Exception('Voucher must contain at least two entries');
        }

        // Duplicate reference prevention (NEG-009, LIFE-011)
        $refToCheck = trim($data['reference'] ?? $data['voucher_number'] ?? '');
        if ($refToCheck !== '') {
            $existingVouchers = $this->getJournalEntries(['reference' => $refToCheck]);
            foreach ($existingVouchers as $ev) {
                if (strcasecmp($ev['reference'] ?? '', $refToCheck) === 0 || strcasecmp($ev['number'] ?? '', $refToCheck) === 0) {
                    throw new Exception("Duplicate voucher reference '{$refToCheck}' is not allowed for this company");
                }
            }
        }

        // Verify non-zero amounts (NEG-004) and collect accounts
        $accountCodes = [];
        foreach ($entries as $item) {
            $amt = (float)($item['amount'] ?? $item['debit'] ?? $item['credit'] ?? 0);
            if ($amt <= 0) {
                throw new Exception("Voucher line item amount must be greater than zero");
            }
            $code = $item['account_code'] ?? $item['account'] ?? null;
            if ($code) {
                $accountCodes[] = $code;
            }
        }

        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domain->domainUuid);
        foreach ($accountCodes as $code) {
            $globalAcc = \Abivia\Ledger\Models\LedgerAccount::where('code', $code)->first();
            if ($globalAcc && !in_array($globalAcc->ledgerUuid, $accountUuids)) {
                DomainLedgerAccount::firstOrCreate([
                    'domainUuid' => $domain->domainUuid,
                    'ledgerUuid' => $globalAcc->ledgerUuid,
                ]);
            }
        }
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domain->domainUuid);
        $validAccounts = \Abivia\Ledger\Models\LedgerAccount::whereIn('code', $accountCodes)
            ->whereIn('ledgerUuid', $accountUuids)
            ->count();

        if ($validAccounts !== count($accountCodes)) {
            throw new Exception('One or more accounts do not belong to the current domain');
        }

        $message = new Entry();
        $message->clearing = true;
        $message->currency = $data['currency'] ?? $domain->currencyDefault ?? 'PKR';
        $message->description = $data['description'] ?? 'Journal Voucher';
        $message->domain = new EntityRef($domain->code);

        // Transaction date handling
        if (isset($data['transDate'])) {
            $message->transDate = Carbon::parse($data['transDate']);
        } elseif (isset($data['date'])) {
            $message->transDate = Carbon::parse($data['date']);
        } else {
            $message->transDate = Carbon::now();
        }

        if (!empty($data['reference']) || !empty($data['voucher_number']) || !empty($data['voucher_type'])) {
            $voucherType = $data['voucher_type'] ?? null;
            if (!$voucherType) {
                $ref = strtoupper($data['reference'] ?? $data['voucher_number'] ?? '');
                if (str_starts_with($ref, 'CV') || str_starts_with($ref, 'CONTRA')) {
                    $voucherType = 'contra';
                } elseif (str_starts_with($ref, 'OB')) {
                    $voucherType = 'opening';
                } elseif (str_starts_with($ref, 'PV')) {
                    $voucherType = 'payment';
                } elseif (str_starts_with($ref, 'RV')) {
                    $voucherType = 'receipt';
                } else {
                    // Check if all accounts in entries are cash/bank (code starting with 11)
                    $isAllCashOrBank = count($entries) >= 2;
                    foreach ($entries as $e) {
                        $c = $e['account_code'] ?? $e['account'] ?? '';
                        if (!str_starts_with($c, '11')) {
                            $isAllCashOrBank = false;
                            break;
                        }
                    }
                    $voucherType = $isAllCashOrBank ? 'contra' : 'journal';
                }
            }

            $message->extra = json_encode([
                'reference' => $data['reference'] ?? null,
                'voucher_number' => $data['voucher_number'] ?? null,
                'voucher_type' => $voucherType,
            ]);
        }

        $details = [];
        foreach ($entries as $item) {
            $accountCode = $item['account_code'] ?? $item['account'] ?? null;
            $detail = new Detail();
            $detail->account = new EntityRef($accountCode);

            $rawAmount = (float)($item['amount'] ?? 0);
            $isCredit = false;
            if (isset($item['type'])) {
                $isCredit = strtolower($item['type']) === 'credit';
            } elseif (isset($item['credit']) && $item['credit']) {
                $isCredit = true;
            } elseif (isset($item['debit']) && !$item['debit']) {
                $isCredit = true;
            }

            // In Abivia: debit is positive, credit is negative
            $detail->amount = $isCredit ? (string) -abs($rawAmount) : (string) abs($rawAmount);
            $details[] = $detail;
        }

        $message->details = $details;

        // Period validation
        $transDateStr = $message->transDate instanceof Carbon ? $message->transDate->toDateString() : (string)$message->transDate;
        $periodService = app(PeriodService::class);
        $periodService->validatePostingDate($domain->domainUuid, $transDateStr);

        // Create entry and domain association atomically inside DB::transaction
        return DB::transaction(function () use ($message, $domain, $data, $entries, $voucherType, $transDateStr) {
            try {
                $journalEntry = $this->journalController->add($message);
            } catch (\Abivia\Ledger\Exceptions\Breaker $b) {
                $errors = $b->getErrors();
                throw new \Exception(!empty($errors) ? implode(', ', $errors) : $b->getMessage());
            }

            // Associate with domain via pivot table
            DomainJournalEntry::create([
                'domainUuid' => $domain->domainUuid,
                'journalEntryId' => $journalEntry->journalEntryId,
            ]);

            // Record in accounting audit trail
            AccountingAuditTrail::record(
                $domain->domainUuid,
                'CREATE_VOUCHER',
                'voucher',
                $data['reference'] ?? (string)$journalEntry->journalEntryId,
                [
                    'voucher_type' => $voucherType ?? 'journal',
                    'date' => $transDateStr,
                    'currency' => $data['currency'] ?? 'PKR',
                    'entries_count' => count($entries),
                ]
            );

            return $journalEntry;
        });
    }

    /**
     * Get journal entries for the current domain.
     * Uses pivot table for direct domain → entries lookup.
     */
    public function getJournalEntries(array $filters = []): Collection
    {
        $currentDomain = $this->getCurrentDomain();
        
        // Get entry IDs for this domain via pivot table
        $entryIds = DomainJournalEntry::getEntryIdsForDomain($currentDomain->domainUuid);
        
        // Query entries that belong to this domain
        $query = JournalEntryModel::whereIn('journalEntryId', $entryIds)->with('details');
        
        if (isset($filters['date_from'])) {
            $query->where('transDate', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('transDate', '<=', $filters['date_to']);
        }
        
        if (isset($filters['reference'])) {
            $query->where('extra', 'like', '%' . $filters['reference'] . '%');
        }
        
        $entries = $query->orderBy('transDate', 'desc')->get();
        $accounts = \Abivia\Ledger\Models\LedgerAccount::with('names')->get()->keyBy('ledgerUuid');

        return $entries->map(function ($entry) use ($accounts) {
            $details = ($entry->details ?? collect())->map(function ($detail) use ($accounts) {
                $acc = $accounts->get($detail->ledgerUuid);
                $amt = (float) $detail->amount;
                $firstName = $acc && $acc->names ? $acc->names->first() : null;
                $rawName = $firstName ? $firstName->name : ($acc ? $acc->code : 'Unknown');
                $displayName = $acc ? "{$rawName} ({$acc->code})" : $rawName;

                return [
                    'id' => $detail->journalDetailId,
                    'account' => $acc ? $acc->code : '',
                    'account_code' => $acc ? $acc->code : '',
                    'account_name' => $displayName,
                    'raw_name' => $rawName,
                    'debit' => $amt > 0 ? $amt : 0,
                    'credit' => $amt < 0 ? abs($amt) : 0,
                    'amount' => abs($amt),
                    'memo' => $detail->extra ?? '',
                ];
            });

            $ref = 'JV-' . $entry->journalEntryId;
            $voucherType = 'Journal';
            if (!empty($entry->extra)) {
                if (is_string($entry->extra) && str_starts_with(trim($entry->extra), '{')) {
                    $decoded = json_decode($entry->extra, true);
                    $ref = $decoded['reference'] ?? $decoded['voucher_number'] ?? $ref;
                    if (!empty($decoded['voucher_type'])) {
                        $voucherType = ucfirst($decoded['voucher_type']);
                    }
                } else {
                    $ref = (string) $entry->extra;
                }
            }

            if ($voucherType === 'Journal') {
                $refUpper = strtoupper($ref);
                if (str_starts_with($refUpper, 'CV') || str_starts_with($refUpper, 'CONTRA')) {
                    $voucherType = 'Contra';
                } elseif (str_starts_with($refUpper, 'OB')) {
                    $voucherType = 'Opening Balance';
                } elseif (str_starts_with($refUpper, 'PV') || str_starts_with($refUpper, 'PAY')) {
                    $voucherType = 'Payment';
                } elseif (str_starts_with($refUpper, 'RV') || str_starts_with($refUpper, 'REC')) {
                    $voucherType = 'Receipt';
                } else {
                    // Check if all lines are cash or bank accounts (codes start with 11)
                    $isAllCashOrBank = $details->count() >= 2 && $details->every(function ($d) {
                        return str_starts_with($d['account_code'] ?? '', '11');
                    });
                    if ($isAllCashOrBank) {
                        $voucherType = 'Contra';
                    }
                }
            }

            return [
                'id' => $entry->journalEntryId,
                'reference' => $ref,
                'number' => $ref,
                'type' => $voucherType,
                'voucher_type' => strtolower($voucherType),
                'date' => substr($entry->transDate, 0, 10),
                'description' => $entry->description,
                'currency' => $entry->currency,
                'amount' => $details->where('debit', '>', 0)->sum('debit'),
                'line_items' => $details,
                'details' => $details,
            ];
        });
    }

    /**
     * Helper for controller voucher listing
     */
    public function getVouchers(?string $fromDate = null, ?string $toDate = null): Collection
    {
        $filters = [];
        if ($fromDate) {
            $filters['date_from'] = $fromDate;
        }
        if ($toDate) {
            $filters['date_to'] = $toDate;
        }
        return $this->getJournalEntries($filters);
    }

    /**
     * Get a single journal entry by ID (domain-scoped).
     */
    public function getJournalEntryById(int $journalEntryId): ?JournalEntryModel
    {
        $currentDomain = $this->getCurrentDomain();
        
        // Verify entry belongs to this domain
        if (!DomainJournalEntry::entryBelongsToDomain($journalEntryId, $currentDomain->domainUuid)) {
            return null;
        }
        
        return JournalEntryModel::with('entries')->find($journalEntryId);
    }

    /**
     * Get voucher by reference or ID
     */
    public function getVoucher(string $reference): ?array
    {
        $vouchers = $this->getJournalEntries(['reference' => $reference]);
        return $vouchers->first();
    }

    /**
     * Delete a voucher is prohibited for posted accounting vouchers to ensure GAAP audit trail immutability.
     */
    public function deleteVoucher(string $reference): bool
    {
        throw new Exception("Posted accounting vouchers cannot be physically deleted. Use voucher reversal to maintain double-entry audit history.");
    }

    /**
     * Reverse a posted voucher (LIFE-008, LIFE-009, AUD-01)
     */
    public function reverseVoucher(string $reference, ?string $date = null, ?string $reason = null): JournalEntryModel
    {
        $currentDomain = $this->getCurrentDomain();
        $voucher = $this->getVoucher($reference);
        if (!$voucher) {
            throw new Exception("Voucher with reference '{$reference}' not found for current company");
        }

        if (str_starts_with(strtoupper($reference), 'REV-')) {
            throw new Exception("Cannot reverse a voucher that is already a reversal");
        }

        $revRef = 'REV-' . $reference;
        $existing = $this->getVoucher($revRef);
        if ($existing) {
            throw new Exception("Voucher '{$reference}' has already been reversed by '{$revRef}'");
        }

        $lineItems = $voucher['line_items'] ?? $voucher['details'] ?? [];
        if (empty($lineItems)) {
            throw new Exception("Voucher has no line items to reverse");
        }

        // Invert debits and credits
        $entries = [];
        $revDesc = "Reversal of {$reference}" . (!empty($reason) ? " - Reason: {$reason}" : "");
        foreach ($lineItems as $item) {
            $code = $item['account_code'] ?? $item['account'];
            $isDebit = (float)($item['debit'] ?? 0) > 0;
            $amt = $isDebit ? (float)$item['debit'] : (float)$item['credit'];

            $entries[] = [
                'account_code' => $code,
                'amount' => $amt,
                'type' => $isDebit ? 'credit' : 'debit',
                'description' => $revDesc,
            ];
        }

        $reversalEntry = $this->createJournalEntry([
            'reference' => $revRef,
            'voucher_type' => 'Journal',
            'description' => $revDesc,
            'date' => $date ?? date('Y-m-d'),
            'currency' => $voucher['currency'] ?? 'PKR',
            'entries' => $entries,
        ]);

        // Audit Trail
        AccountingAuditTrail::record(
            $currentDomain->domainUuid,
            'REVERSE_VOUCHER',
            'voucher',
            $reference,
            [
                'reversal_reference' => $revRef,
                'reason' => $reason ?? 'Voucher correction',
                'reversal_date' => $date ?? date('Y-m-d'),
            ]
        );

        return $reversalEntry;
    }

    public function createSalesVoucher(array $data): JournalEntryModel
    {
        $entries = [
            [
                'account' => $data['customer_account_code'],
                'amount' => $data['total_amount'],
                'debit' => true,
                'description' => 'Sales to ' . ($data['customer_name'] ?? 'Customer'),
            ],
            [
                'account' => $data['sales_account_code'],
                'amount' => $data['net_amount'],
                'credit' => true,
                'description' => 'Sales revenue',
            ]
        ];
        
        if (!empty($data['tax_amount'])) {
            $entries[] = [
                'account' => $data['tax_account_code'],
                'amount' => $data['tax_amount'],
                'credit' => true,
                'description' => 'Sales tax collected',
            ];
        }
        
        return $this->createJournalEntry([
            'description' => 'Sales Voucher - ' . ($data['voucher_number'] ?? ''),
            'reference' => $data['voucher_number'] ?? null,
            'date' => $data['date'] ?? null,
            'currency' => $data['currency'] ?? 'PKR',
            'entries' => $entries,
        ]);
    }

    public function createPurchaseVoucher(array $data): JournalEntryModel
    {
        $entries = [
            [
                'account' => $data['purchase_account_code'],
                'amount' => $data['net_amount'],
                'debit' => true,
                'description' => 'Purchase from ' . ($data['supplier_name'] ?? 'Supplier'),
            ],
            [
                'account' => $data['supplier_account_code'],
                'amount' => $data['total_amount'],
                'credit' => true,
                'description' => 'Amount payable to supplier',
            ]
        ];
        
        if (!empty($data['tax_amount'])) {
            $entries[] = [
                'account' => $data['input_tax_account_code'],
                'amount' => $data['tax_amount'],
                'debit' => true,
                'description' => 'Input tax on purchase',
            ];
        }
        
        return $this->createJournalEntry([
            'description' => 'Purchase Voucher - ' . ($data['voucher_number'] ?? ''),
            'reference' => $data['voucher_number'] ?? null,
            'date' => $data['date'] ?? null,
            'currency' => $data['currency'] ?? 'PKR',
            'entries' => $entries,
        ]);
    }

    public function createPaymentVoucher(array $data): JournalEntryModel
    {
        return $this->createJournalEntry([
            'description' => 'Payment Voucher - ' . ($data['voucher_number'] ?? ''),
            'reference' => $data['voucher_number'] ?? null,
            'date' => $data['date'] ?? null,
            'currency' => $data['currency'] ?? 'PKR',
            'entries' => [
                [
                    'account' => $data['payee_account_code'],
                    'amount' => $data['amount'],
                    'debit' => true,
                    'description' => 'Payment to ' . ($data['payee_name'] ?? 'Payee'),
                ],
                [
                    'account' => $data['bank_account_code'],
                    'amount' => $data['amount'],
                    'credit' => true,
                    'description' => 'Payment from bank',
                ]
            ],
        ]);
    }

    public function createReceiptVoucher(array $data): JournalEntryModel
    {
        return $this->createJournalEntry([
            'description' => 'Receipt Voucher - ' . ($data['voucher_number'] ?? ''),
            'reference' => $data['voucher_number'] ?? null,
            'date' => $data['date'] ?? null,
            'currency' => $data['currency'] ?? 'PKR',
            'entries' => [
                [
                    'account' => $data['bank_account_code'],
                    'amount' => $data['amount'],
                    'debit' => true,
                    'description' => 'Receipt in bank',
                ],
                [
                    'account' => $data['payer_account_code'],
                    'amount' => $data['amount'],
                    'credit' => true,
                    'description' => 'Receipt from ' . ($data['payer_name'] ?? 'Payer'),
                ]
            ],
        ]);
    }
}
