<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Models\JournalEntry as JournalEntryModel;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Illuminate\Support\Collection;
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

        // Verify all accounts belong to this domain
        if (isset($data['entries'])) {
            $accountCodes = array_column($data['entries'], 'account_code');
            $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domain->domainUuid);
            
            $validAccounts = \Abivia\Ledger\Models\LedgerAccount::whereIn('code', $accountCodes)
                ->whereIn('ledgerUuid', $accountUuids)
                ->count();
            
            if ($validAccounts !== count($accountCodes)) {
                throw new Exception('One or more accounts do not belong to the current domain');
            }
        }

        // Build Entry message
        $message = Entry::fromArray($data);
        
        // Create entry via Abivia controller
        $journalEntry = $this->journalController->add($message);
        
        // Associate with domain via pivot table
        DomainJournalEntry::create([
            'domainUuid' => $domain->domainUuid,
            'journalEntryId' => $journalEntry->journalEntryId,
        ]);
        
        return $journalEntry;
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
        $query = JournalEntryModel::whereIn('journalEntryId', $entryIds);
        
        if (isset($filters['date_from'])) {
            $query->where('transDate', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('transDate', '<=', $filters['date_to']);
        }
        
        if (isset($filters['reference'])) {
            $query->where('reference', 'like', '%' . $filters['reference'] . '%');
        }
        
        return $query->orderBy('transDate', 'desc')->get();
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
        
        return JournalEntryModel::find($journalEntryId);
    }

    // Voucher type-specific methods remain the same but use createJournalEntry
    
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
            'transDate' => $data['date'],
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
            'transDate' => $data['date'],
            'currency' => $data['currency'] ?? 'PKR',
            'entries' => $entries,
        ]);
    }

    public function createPaymentVoucher(array $data): JournalEntryModel
    {
        return $this->createJournalEntry([
            'description' => 'Payment Voucher - ' . ($data['voucher_number'] ?? ''),
            'reference' => $data['voucher_number'] ?? null,
            'transDate' => $data['date'],
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
            'transDate' => $data['date'],
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
