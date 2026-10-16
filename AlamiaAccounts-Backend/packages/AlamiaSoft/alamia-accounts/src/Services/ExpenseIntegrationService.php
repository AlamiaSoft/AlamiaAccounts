<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry;
use Illuminate\Support\Str;
use Exception;
use Throwable;

class ExpenseIntegrationService
{
    public function __construct(
        protected VoucherService $voucherService,
        protected AccountMappingResolver $mappingResolver,
        protected CompanyService $companyService,
        protected ?PostingIdempotencyService $idempotencyService = null
    ) {
        $this->idempotencyService = $idempotencyService ?? app(PostingIdempotencyService::class);
    }

    /**
     * Process an incoming operational expense and commit a balanced double-entry voucher.
     * Enforces durable, database-backed idempotency, sequential replay, and conflict detection.
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
        $targetCompany = $companyCode ?: DomainContext::get();
        if (empty($targetCompany)) {
            throw new Exception("Explicit company context is required for expense integration. Missing or unassigned company.");
        }
        $companyCode = strtoupper(trim((string) $targetCompany));
        $domain = $this->companyService->getDomain($companyCode);

        if (!$domain) {
            throw new Exception("Ledger domain for company '{$companyCode}' could not be resolved.");
        }

        return DomainContext::scope($companyCode, function () use ($payload, $companyCode, $domain) {
            $domainUuid = $domain->domainUuid;

            $reference = trim((string)($payload['reference'] ?? ''));
            if (empty($reference)) {
                throw new Exception("Expense reference is required.");
            }

            $category = (string)($payload['category'] ?? 'miscellaneous');
            $paymentMethod = (string)($payload['payment_method'] ?? 'cash');
            $vendorName = !empty($payload['vendor_name']) ? trim((string)$payload['vendor_name']) : 'General Payee';
            $desc = !empty($payload['description']) ? trim((string)$payload['description']) : "Operational Expense ({$category})";
            $date = !empty($payload['date']) ? $payload['date'] : now()->format('Y-m-d');
            $currency = !empty($payload['currency']) ? strtoupper(trim((string)$payload['currency'])) : 'PKR';
            $userId = !empty($payload['user_id']) ? (string)$payload['user_id'] : 'system';
            $userName = !empty($payload['user_name']) ? (string)$payload['user_name'] : 'System / Integration';

            $amount = round((float)($payload['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new Exception("Expense amount must be strictly greater than zero.");
            }

            // 1. Resolve target ledger accounts using AccountMappingResolver
            $debitAccountCode = $this->mappingResolver->resolveExpenseAccount($category, $domainUuid);
            $creditAccountCode = $this->mappingResolver->resolvePaymentSourceAccount($paymentMethod, $domainUuid);

            // 2. Canonical Identity & Payload Fingerprinting
            $canonicalIdentity = $this->idempotencyService->resolveCanonicalIdentity($payload, $companyCode, $domainUuid);
            $idempotencyKey = $canonicalIdentity['idempotency_key'];
            $sourceModule = $canonicalIdentity['source_module'];

            $payloadHash = $this->idempotencyService->computePayloadHash(
                $payload,
                $companyCode,
                [
                    'resolved_debit_account' => $debitAccountCode,
                    'resolved_credit_account' => $creditAccountCode,
                ]
            );

            // 3. Durable Idempotency Check (Zero SQL LIKE Scans)
            $existingRecord = $this->idempotencyService->findExistingRecord(
                $domainUuid,
                $idempotencyKey,
                $canonicalIdentity
            );

            if ($existingRecord) {
                $eval = $this->idempotencyService->evaluateReplay($existingRecord, $payloadHash);

                if ($eval['action'] === 'replay') {
                    // Valid identical sequential replay: return original committed posting without creating a second entry
                    UnpostedIntegrationEntry::where('idempotency_key', $idempotencyKey)
                        ->where('status', 'failed')
                        ->update([
                            'status' => 'resolved',
                            'resolved_voucher_ref' => (string)($existingRecord->journal_entry_id ?? $existingRecord->voucher_reference),
                            'resolved_at' => now(),
                        ]);

                    $cachedResponse = $existingRecord->response_data ?? [];
                    return array_merge([
                        'status' => 'posted',
                        'is_idempotent_replay' => true,
                        'voucher_reference' => $existingRecord->voucher_reference ?? $reference,
                        'journal_entry_id' => $existingRecord->journal_entry_id,
                        'debit_account' => $debitAccountCode,
                        'credit_account' => $creditAccountCode,
                        'amount' => $amount,
                        'message' => 'Expense voucher already committed (Idempotent replay).',
                    ], $cachedResponse, ['is_idempotent_replay' => true]);
                }

                if ($eval['action'] === 'conflict') {
                    // Conflicting payload fingerprint under same identity: reject and audit as a security/conflict violation
                    if (class_exists(AccountingAuditTrail::class)) {
                        AccountingAuditTrail::record(
                            $domainUuid,
                            'IDEMPOTENCY_PAYLOAD_MISMATCH',
                            'expense_voucher',
                            $reference,
                            [
                                'error' => "Idempotency key '{$idempotencyKey}' reused with conflicting payload",
                                'idempotency_key' => $idempotencyKey,
                                'original_payload_hash' => $existingRecord->payload_hash,
                                'attempted_payload_hash' => $payloadHash,
                                'original_voucher_ref' => $existingRecord->voucher_reference,
                                'original_journal_entry_id' => $existingRecord->journal_entry_id,
                                'attempted_payload' => $payload,
                            ],
                            $userId,
                            $userName
                        );
                    }

                    throw new IdempotencyPayloadMismatchException(
                        $idempotencyKey,
                        $existingRecord->payload_hash,
                        $payloadHash,
                        $domainUuid,
                        $companyCode,
                        $reference
                    );
                }
            }

            // 4. Pre-reserve or Re-reserve Idempotency Record (status: 'in_flight')
            $idempotencyRecord = null;
            if ($existingRecord && $existingRecord->status === 'failed') {
                $existingRecord->update([
                    'status' => 'in_flight',
                    'payload_hash' => $payloadHash,
                    'request_payload' => $payload,
                    'lease_token' => (string) Str::uuid(),
                    'lease_generation' => $existingRecord->lease_generation + 1,
                    'lease_expires_at' => now()->addSeconds(60),
                    'error_message' => null,
                ]);
                $idempotencyRecord = $existingRecord;
            } else {
                $idempotencyRecord = $this->idempotencyService->reserveInFlight(
                    $canonicalIdentity,
                    $payloadHash,
                    $payload
                );
            }

            try {
                // 5. Post Balanced Double-Entry Voucher
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
                $journalEntryId = $journalEntry->journalEntryId ?? ($journalEntry['id'] ?? null);

                $responseData = [
                    'status' => 'posted',
                    'is_idempotent_replay' => false,
                    'voucher_reference' => $reference,
                    'journal_entry_id' => $journalEntryId,
                    'debit_account' => $debitAccountCode,
                    'credit_account' => $creditAccountCode,
                    'amount' => $amount,
                ];

                // 6. Transition Idempotency Record to 'committed'
                $idempotencyRecord->markCommitted($reference, $journalEntryId, $responseData);

                // 7. Log to Audit Trail
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

                // 8. Clear any prior failure in unposted queue
                UnpostedIntegrationEntry::where('idempotency_key', $idempotencyKey)
                    ->where('status', 'failed')
                    ->update([
                        'status' => 'resolved',
                        'resolved_voucher_ref' => (string)($journalEntryId ?? $reference),
                        'resolved_at' => now(),
                    ]);

                return $responseData;
            } catch (Throwable $e) {
                // Record failure on the reserved idempotency record
                if ($idempotencyRecord) {
                    $idempotencyRecord->markFailed($e->getMessage());
                }

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
        });
    }
}
