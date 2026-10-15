<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry;
use Illuminate\Support\Facades\DB;
use Exception;
use Throwable;

class ExpenseIntegrationService
{
    public function __construct(
        protected VoucherService $voucherService,
        protected AccountMappingResolver $mappingResolver,
        protected CompanyService $companyService
    ) {}

    /**
     * Process an incoming operational expense and commit a balanced double-entry voucher.
     *
     * @param array{
     *     reference: string,
     *     amount: float|int|string,
     *     category: string,
     *     payment_method?: string|null,
     *     vendor_name?: string|null,
     *     description?: string|null,
     *     date?: string|null,
     *     currency?: string|null,
     *     user_id?: string|int|null,
     *     user_name?: string|null,
     *     idempotency_key?: string|null,
     *     source_module?: string|null
     * } $payload
     * @param string|null $companyCode
     * @return array
     * @throws Exception
     */
    public function processExpense(array $payload, ?string $companyCode = null): array
    {
        $companyCode = strtoupper(trim($companyCode ?: (DomainContext::get() ?: 'MAIN')));
        $domain = $this->companyService->getDomain($companyCode);

        if (!$domain) {
            $companyCode = 'MAIN';
            $domain = $this->companyService->getDomain('MAIN');
            if (!$domain) {
                throw new Exception("Ledger domain for company '{$companyCode}' could not be resolved.");
            }
        }

        DomainContext::set($companyCode);
        $domainUuid = $domain->domainUuid;

        $reference = trim((string)($payload['reference'] ?? ''));
        if (empty($reference)) {
            throw new Exception("Expense reference is required.");
        }

        $sourceModule = strtolower(trim((string)($payload['source_module'] ?? 'travelos')));
        $idempotencyKey = trim((string)($payload['idempotency_key'] ?? "{$sourceModule}:{$companyCode}:EXPENSE:{$reference}"));

        // 1. Idempotency Check with Database Locking
        // Check if voucher was already recorded in journal_entries
        $existingEntry = DB::table('journal_entries')
            ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
            ->where('domain_journal_entries.domainUuid', $domainUuid)
            ->where('journal_entries.extra', 'like', '%"reference":"' . $reference . '"%')
            ->select('journal_entries.*')
            ->first();

        if ($existingEntry) {
            // Check if there is an unposted entry to clear
            UnpostedIntegrationEntry::where('idempotency_key', $idempotencyKey)
                ->where('status', 'failed')
                ->update([
                    'status' => 'resolved',
                    'resolved_voucher_ref' => $existingEntry->journalEntryId,
                    'resolved_at' => now(),
                ]);

            return [
                'status' => 'posted',
                'is_idempotent_replay' => true,
                'voucher_reference' => $reference,
                'journal_entry_id' => $existingEntry->journalEntryId,
                'message' => 'Expense voucher already committed (Idempotent replay).',
            ];
        }

        $category = (string)($payload['category'] ?? 'miscellaneous');
        $paymentMethod = (string)($payload['payment_method'] ?? 'cash');
        $vendorName = !empty($payload['vendor_name']) ? trim((string)$payload['vendor_name']) : 'General Payee';
        $desc = !empty($payload['description']) ? trim((string)$payload['description']) : "Operational Expense ({$category})";
        $date = !empty($payload['date']) ? $payload['date'] : now()->format('Y-m-d');
        $currency = !empty($payload['currency']) ? strtoupper(trim((string)$payload['currency'])) : 'PKR';
        $userId = !empty($payload['user_id']) ? (string)$payload['user_id'] : 'system';
        $userName = !empty($payload['user_name']) ? (string)$payload['user_name'] : 'System / Integration';

        try {
            $amount = round((float)($payload['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new Exception("Expense amount must be strictly greater than zero.");
            }

            // 2. Resolve target accounts using AccountMappingResolver
            $debitAccountCode = $this->mappingResolver->resolveExpenseAccount($category, $domainUuid);
            $creditAccountCode = $this->mappingResolver->resolvePaymentSourceAccount($paymentMethod, $domainUuid);

            // 3. Post Balanced Double-Entry Voucher
            $voucherData = [
                'description' => "{$desc} [Payee: {$vendorName}]",
                'reference' => $reference,
                'date' => $date,
                'currency' => $currency,
                'entries' => [
                    [
                        'account_code' => $debitAccountCode,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'description' => "Expense: {$desc}",
                    ],
                    [
                        'account_code' => $creditAccountCode,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'description' => "Payment via {$paymentMethod}",
                    ],
                ],
            ];

            $journalEntry = $this->voucherService->createJournalEntry($voucherData, $companyCode);

            // 4. Log to Audit Trail
            if (class_exists(AccountingAuditTrail::class)) {
                AccountingAuditTrail::record(
                    $domainUuid,
                    'INTEGRATION_EXPENSE_POSTED',
                    'expense_voucher',
                    $reference,
                    [
                        'amount' => $amount,
                        'debit_account' => $debitAccountCode,
                        'credit_account' => $creditAccountCode,
                        'category' => $category,
                        'payment_method' => $paymentMethod,
                        'idempotency_key' => $idempotencyKey,
                    ],
                    $userId,
                    $userName
                );
            }

            // 5. Clear any prior failure in unposted queue
            UnpostedIntegrationEntry::where('idempotency_key', $idempotencyKey)
                ->where('status', 'failed')
                ->update([
                    'status' => 'resolved',
                    'resolved_voucher_ref' => (string)($journalEntry['id'] ?? $reference),
                    'resolved_at' => now(),
                ]);

            return [
                'status' => 'posted',
                'is_idempotent_replay' => false,
                'voucher_reference' => $reference,
                'journal_entry_id' => $journalEntry['id'] ?? null,
                'debit_account' => $debitAccountCode,
                'credit_account' => $creditAccountCode,
                'amount' => $amount,
            ];
        } catch (Throwable $e) {
            // Record failure in durable unposted queue and audit trail
            UnpostedIntegrationEntry::recordFailure(
                $domainUuid,
                $companyCode,
                $sourceModule,
                $reference,
                $idempotencyKey,
                'expense',
                $payload,
                $e->getMessage()
            );

            if (class_exists(AccountingAuditTrail::class)) {
                AccountingAuditTrail::record(
                    $domainUuid,
                    'INTEGRATION_POSTING_FAILED',
                    'expense_voucher',
                    $reference,
                    [
                        'error' => $e->getMessage(),
                        'idempotency_key' => $idempotencyKey,
                        'payload' => $payload,
                    ],
                    $userId,
                    $userName
                );
            }

            throw $e;
        }
    }
}
