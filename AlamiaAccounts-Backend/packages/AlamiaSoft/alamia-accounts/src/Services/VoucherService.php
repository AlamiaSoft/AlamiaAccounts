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
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class VoucherService
{
    protected JournalEntryController $journalController;
    protected PostingValidationService $postingValidationService;

    public function __construct(?PostingValidationService $postingValidationService = null)
    {
        $this->journalController = new JournalEntryController();
        $this->postingValidationService = $postingValidationService ?? app(PostingValidationService::class);
    }

    /**
     * Get the current domain based on DomainContext.
     */
    protected function getCurrentDomain(): LedgerDomain
    {
        $code = DomainContext::get();
        
        if (!$code) {
            throw new Exception('Company context is required for voucher operations. No active company domain set.');
        }
        
        $domain = LedgerDomain::where('code', $code)->first();
        
        if (!$domain) {
            throw new Exception("Domain with code '{$code}' not found.");
        }
        
        return $domain;
    }

    /**
     * Create a journal entry and associate it with the current domain.
     * 
     * @param array $data Entry data
     * @param string|null $domainCode Optional domain code
     * @param array|null $actorContext Optional authenticated actor and correlation context
     * @return JournalEntryModel
     * @throws Exception
     */
    public function createJournalEntry(array $data, ?string $domainCode = null, ?array $actorContext = null): JournalEntryModel
    {
        // Get the domain
        if ($domainCode) {
            $domain = LedgerDomain::where('code', $domainCode)->firstOrFail();
        } else {
            $domain = $this->getCurrentDomain();
        }

        return DomainContext::scope($domain->code, function () use ($data, $domain, $actorContext) {
            // Centralized authoritative write-boundary validation
            $validated = $this->postingValidationService->validateVoucherPosting($domain, $data, $actorContext);

            $refToCheck = $validated['reference'];
            if ($refToCheck !== '') {
                $existingVouchers = $this->getJournalEntries(['reference' => $refToCheck]);
                foreach ($existingVouchers as $ev) {
                    if (strcasecmp($ev['reference'] ?? '', $refToCheck) === 0 || strcasecmp($ev['number'] ?? '', $refToCheck) === 0) {
                        throw new Exception("Duplicate voucher reference '{$refToCheck}' is not allowed for this company");
                    }
                }
            }

            $message = new Entry();
            $message->clearing = true;
            $message->currency = $validated['currency'];
            $message->description = $data['description'] ?? 'Journal Voucher';
            $message->domain = new EntityRef($domain->code);
            $message->transDate = $validated['trans_date'];

            if ($refToCheck !== '' || !empty($validated['voucher_type'])) {
                $extraData = [
                    'reference' => $refToCheck !== '' ? $refToCheck : null,
                    'voucher_number' => isset($data['voucher_number']) ? trim((string) $data['voucher_number']) : null,
                    'voucher_type' => $validated['voucher_type'],
                ];
                if (!empty($data['custom_fields'])) {
                    $extraData['custom_fields'] = $data['custom_fields'];
                }
                if (!empty($data['type'])) {
                    $extraData['type'] = $data['type'];
                }
                $message->extra = json_encode($extraData);
            }

            $details = [];
            foreach ($validated['account_net_amounts'] as $accountCode => $netAmount) {
                if (abs($netAmount) < 0.0001) {
                    continue;
                }
                $detail = new Detail();
                $detail->account = new EntityRef($accountCode);
                $detail->amount = (string)$netAmount;
                $details[] = $detail;
            }

            if (count($details) < 2) {
                throw new Exception('Voucher must contain at least two non-cancelling account entries for double-entry ledger posting');
            }

            $message->details = $details;
            $transDateStr = $validated['trans_date_str'];
            $voucherType = $validated['voucher_type'];

            // Create entry and domain association atomically inside DB::transaction
            return DB::transaction(function () use ($message, $domain, $data, $validated, $voucherType, $transDateStr, $refToCheck) {
                try {
                    $journalEntry = $this->journalController->add($message);
                } catch (\Abivia\Ledger\Exceptions\Breaker $b) {
                    $errors = $b->getErrors();
                    throw new \Exception(!empty($errors) ? implode(', ', $errors) : $b->getMessage());
                }

                // Associate with domain via pivot table with company-scoped reference uniqueness enforcement
                $voucherRef = $refToCheck !== '' ? $refToCheck : ('JV-' . $journalEntry->journalEntryId);
                try {
                    DomainJournalEntry::create([
                        'domainUuid' => $domain->domainUuid,
                        'journalEntryId' => $journalEntry->journalEntryId,
                        'reference' => $voucherRef,
                    ]);
                } catch (QueryException $e) {
                    if (PostingIdempotencyService::isUniqueConstraintViolation($e)) {
                        throw new Exception("Duplicate voucher reference '{$voucherRef}' is not allowed for this company", 0, $e);
                    }
                    throw $e;
                }

                $auditEntityId = $refToCheck !== '' ? $refToCheck : (string)$journalEntry->journalEntryId;

                // Record in accounting audit trail
                AccountingAuditTrail::record(
                    $domain->domainUuid,
                    'CREATE_VOUCHER',
                    'voucher',
                    $auditEntityId,
                    [
                        'voucher_type' => $voucherType ?? 'journal',
                        'date' => $transDateStr,
                        'currency' => $validated['currency'],
                        'entries_count' => count($validated['normalized_entries']),
                        'total_amount' => $validated['total_amount'],
                        'maker_id' => $validated['maker_id'],
                        'approver_id' => $validated['approver_id'],
                        'approval_reason' => $validated['approval_reason'],
                        'source' => $validated['source'],
                        'correlation_id' => $validated['correlation_id'],
                    ],
                    $validated['maker_id'],
                    $validated['maker_name']
                );

                // Record transaction-level accounting integrity checkpoint
                try {
                    $guard = app(AccountingIntegrityGuard::class);
                    $guard->recordCheckpoint(
                        $domain->domainUuid,
                        'POST_VOUCHER',
                        $auditEntityId,
                        $voucherType ?? ($data['custom_voucher_type_name'] ?? 'journal'),
                        $journalEntry->journalEntryId,
                        $transDateStr,
                        $validated['currency'],
                        [
                            'description' => $data['description'] ?? null,
                            'entries_count' => count($validated['normalized_entries']),
                            'total_amount' => $validated['total_amount'],
                        ]
                    );
                } catch (\Throwable $e) {
                    \Log::warning("AccountingIntegrityGuard checkpoint recording error: " . $e->getMessage());
                }

                return $journalEntry;
            });
        });
    }

    /**
     * Get journal entries for the current domain.
     * Uses pivot table for direct domain → entries lookup.
     */
    public function getJournalEntries(array $filters = []): Collection
    {
        $currentDomain = $this->getCurrentDomain();
        
        // Get entry IDs and canonical references for this domain via pivot table
        $pivotReferences = DomainJournalEntry::where('domainUuid', $currentDomain->domainUuid)
            ->pluck('reference', 'journalEntryId');
        $entryIds = $pivotReferences->keys()->toArray();
        
        // Query entries that belong to this domain
        $query = JournalEntryModel::whereIn('journalEntryId', $entryIds)->with('details');
        
        if (isset($filters['date_from'])) {
            $query->where('transDate', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('transDate', '<=', $filters['date_to']);
        }
        
        if (isset($filters['reference'])) {
            $refFilter = trim((string) $filters['reference']);
            if ($refFilter !== '') {
                $matchedPivotIds = DomainJournalEntry::findEntryIdsByReference($currentDomain->domainUuid, $refFilter, false);
                $query->where(function ($q) use ($refFilter, $matchedPivotIds) {
                    $q->where('extra', 'like', '%' . $refFilter . '%');
                    if (!empty($matchedPivotIds)) {
                        $q->orWhereIn('journalEntryId', $matchedPivotIds);
                    }
                });
            }
        }
        
        $entries = $query->orderBy('transDate', 'desc')->get();
        $accounts = \Abivia\Ledger\Models\LedgerAccount::with('names')->get()->keyBy('ledgerUuid');

        return $entries->map(function ($entry) use ($accounts, $pivotReferences) {
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

            $pivotRef = trim((string) ($pivotReferences[$entry->journalEntryId] ?? ''));
            $ref = $pivotRef !== '' ? $pivotRef : ('JV-' . $entry->journalEntryId);
            $voucherType = 'Journal';
            $customFields = null;
            $rawType = null;
            if (!empty($entry->extra)) {
                if (is_string($entry->extra) && str_starts_with(trim($entry->extra), '{')) {
                    $decoded = json_decode($entry->extra, true);
                    $extraRef = trim((string) ($decoded['reference'] ?? $decoded['voucher_number'] ?? ''));
                    if ($pivotRef === '' && $extraRef !== '') {
                        $ref = $extraRef;
                    }
                    if (!empty($decoded['voucher_type'])) {
                        $voucherType = ucfirst($decoded['voucher_type']);
                    }
                    $customFields = $decoded['custom_fields'] ?? null;
                    $rawType = $decoded['type'] ?? null;
                } elseif ($pivotRef === '') {
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
                'custom_fields' => $customFields,
                'custom_voucher_type' => $voucherType,
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
        $trimmed = trim($reference);
        if ($trimmed === '') {
            return null;
        }

        $vouchers = $this->getJournalEntries(['reference' => $trimmed]);
        return $vouchers->first(function (array $v) use ($trimmed) {
            return strcasecmp((string) ($v['reference'] ?? ''), $trimmed) === 0
                || strcasecmp((string) ($v['number'] ?? ''), $trimmed) === 0
                || (string) ($v['id'] ?? '') === $trimmed;
        });
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

        return DB::transaction(function () use ($revRef, $revDesc, $date, $voucher, $entries, $currentDomain, $reference, $reason) {
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
        });
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

    /**
     * Clear / Reset all transactions and vouchers for the current tenant domain.
     * Strictly prohibited in production environments or when allow_destructive_reset is false.
     * Preserves Chart of Accounts, settings, custom voucher types, and periods.
     *
     * @param string|null $domainCode
     * @param string|null $confirmationToken
     * @param string $auditActor
     * @return int Count of deleted journal entries
     */
    public function clearAllTransactions(?string $domainCode = null, ?string $confirmationToken = null, string $auditActor = 'system'): int
    {
        // P0 Security Guard 1: Hard Environment Check (Cannot be invoked in production)
        if (app()->environment('production')) {
            throw new \RuntimeException('Destructive bulk ledger reset is strictly prohibited in production environments.');
        }

        // P0 Security Guard 2: Explicit Feature Flag Check (defaults to false)
        if (!config('alamia-accounts.allow_destructive_reset', false)) {
            throw new \RuntimeException('Destructive bulk ledger reset feature flag (alamia-accounts.allow_destructive_reset) is disabled.');
        }

        $domain = $domainCode
            ? LedgerDomain::where('code', $domainCode)->first()
            : $this->getCurrentDomain();

        if (!$domain) {
            $domain = $this->getCurrentDomain();
        }

        if (!$domain) {
            throw new \RuntimeException('No active domain resolved for transaction reset.');
        }

        // P0 Security Guard 3: Explicit Confirmation Token Check
        $expectedToken = 'CONFIRM_RESET_' . strtoupper(trim($domain->code));
        if ($confirmationToken !== $expectedToken) {
            throw new \InvalidArgumentException("Invalid reset confirmation token. Expected: [{$expectedToken}].");
        }

        $entryIds = DomainJournalEntry::where('domainUuid', $domain->domainUuid)
            ->pluck('journalEntryId')
            ->toArray();

        $count = count($entryIds);

        DB::transaction(function () use ($domain, $entryIds, $count, $auditActor) {
            if ($count > 0) {
                // Delete details from journal_details
                DB::table('journal_details')->whereIn('journalEntryId', $entryIds)->delete();
                // Delete entries from journal_entries
                DB::table('journal_entries')->whereIn('journalEntryId', $entryIds)->delete();
                // Delete domain association
                DomainJournalEntry::where('domainUuid', $domain->domainUuid)->delete();
            }

            // Clear accounting integrity checkpoints for this domain
            \AlamiaSoft\AlamiaAccounts\Models\AccountingIntegrityCheckpoint::where('domain_uuid', $domain->domainUuid)->delete();

            // Clear front-office operational POS sales staged / posted for this company code
            $saleIds = \AlamiaSoft\AlamiaAccounts\Models\OperationalSale::where('company_code', $domain->code)->pluck('id')->toArray();
            if (!empty($saleIds)) {
                \AlamiaSoft\AlamiaAccounts\Models\OperationalSaleItem::whereIn('operational_sale_id', $saleIds)->delete();
                \AlamiaSoft\AlamiaAccounts\Models\OperationalSale::whereIn('id', $saleIds)->delete();
            }

            // Clear opening balance batches if any
            \AlamiaSoft\AlamiaAccounts\Models\OpeningBalanceBatch::where('domain_uuid', $domain->domainUuid)->delete();

            // Record administrative audit trail
            AccountingAuditTrail::record(
                $domain->domainUuid,
                'RESET_ALL_TRANSACTIONS',
                $auditActor,
                'ALL',
                [
                    'cleared_vouchers_count' => $count,
                    'cleared_sales_count' => count($saleIds ?? []),
                    'reason' => "Administrator [{$auditActor}] reset tenant transactions"
                ]
            );
        });

        return $count;
    }
}
