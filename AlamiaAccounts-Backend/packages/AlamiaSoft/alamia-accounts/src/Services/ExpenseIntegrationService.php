<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Exceptions\ConcurrentPostingInProgressException;
use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyLeaseFencingException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use Throwable;

class ExpenseIntegrationService
{
    /**
     * Optional post-commit callback invoked after the outer DB::transaction commits
     * and before the response is returned to the caller. Used for post-commit
     * observability and deterministic response/timeout fault-injection testing.
     */
    protected ?Closure $afterCommitObserver = null;

    public function __construct(
        protected VoucherService $voucherService,
        protected AccountMappingResolver $mappingResolver,
        protected CompanyService $companyService,
        protected ?PostingIdempotencyService $idempotencyService = null
    ) {
        $this->idempotencyService = $idempotencyService ?? app(PostingIdempotencyService::class);
    }

    /**
     * Register or clear a post-commit observer callback.
     */
    public function onAfterCommit(?Closure $observer): void
    {
        $this->afterCommitObserver = $observer;
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

            // 3. Concurrency-Safe Durable Idempotency Reservation
            $reservation = $this->idempotencyService->acquireReservation(
                $canonicalIdentity,
                $payloadHash,
                $payload
            );

            if ($reservation['status'] === 'replay') {
                // Valid identical sequential replay: return original committed posting without creating a second entry
                UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
                    ->where('idempotency_key', $idempotencyKey)
                    ->whereIn('status', ['failed', 'reconciliation_required'])
                    ->update([
                        'status' => 'resolved',
                        'resolved_voucher_ref' => (string)($reservation['record']->journal_entry_id ?? $reservation['record']->voucher_reference),
                        'resolved_at' => now(),
                        'error_message' => null,
                    ]);

                $cachedResponse = $reservation['response'] ?? $reservation['record']->response_data ?? [];
                return array_merge([
                    'status' => 'posted',
                    'is_idempotent_replay' => true,
                    'voucher_reference' => $reservation['record']->voucher_reference ?? $reference,
                    'journal_entry_id' => $reservation['record']->journal_entry_id,
                    'debit_account' => $debitAccountCode,
                    'credit_account' => $creditAccountCode,
                    'amount' => $amount,
                    'message' => 'Expense voucher already committed (Idempotent replay).',
                ], $cachedResponse, ['is_idempotent_replay' => true]);
            }

            if ($reservation['status'] === 'conflict') {
                $existingRecord = $reservation['record'];
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

            if ($reservation['status'] === 'in_progress') {
                throw new ConcurrentPostingInProgressException(
                    $idempotencyKey,
                    $domainUuid,
                    $companyCode,
                    $reference,
                    2
                );
            }

            $idempotencyRecord = $reservation['record'];

            try {
                // Steps 5-8 execute inside a single atomic DB::transaction so that the Abivia
                // JournalEntry/JournalDetail/LedgerBalance (via nested savepoint in JournalEntryController::add
                // and VoucherService::createJournalEntry), DomainJournalEntry association, CREATE_VOUCHER audit,
                // fenced idempotency 'committed' transition, INTEGRATION_EXPENSE_POSTED audit, and unposted-queue
                // resolution either all commit together at transaction depth 0 or all roll back together.
                $responseData = DB::transaction(function () use (
                    $desc,
                    $vendorName,
                    $reference,
                    $date,
                    $currency,
                    $debitAccountCode,
                    $creditAccountCode,
                    $amount,
                    $paymentMethod,
                    $companyCode,
                    $idempotencyRecord,
                    $domainUuid,
                    $category,
                    $idempotencyKey,
                    $userId,
                    $userName
                ) {
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

                    // 6. Transition Idempotency Record to 'committed' using optimistic lease fencing
                    $idempotencyRecord->markCommittedFenced($reference, $journalEntryId, $responseData);

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
                    UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
                        ->where('idempotency_key', $idempotencyKey)
                        ->whereIn('status', ['failed', 'reconciliation_required'])
                        ->update([
                            'status' => 'resolved',
                            'resolved_voucher_ref' => (string)($journalEntryId ?? $reference),
                            'resolved_at' => now(),
                            'error_message' => null,
                        ]);

                    return $responseData;
                });

                if ($this->afterCommitObserver !== null) {
                    ($this->afterCommitObserver)($responseData, $idempotencyRecord);
                }

                return $responseData;
            } catch (\AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyLeaseFencingException $e) {
                // Lease was lost/preempted before commit; outer DB::transaction has rolled back the journal entry
                // and domain association. Do not overwrite the new lease holder's idempotency record.
                try {
                    if (class_exists(AccountingAuditTrail::class)) {
                        AccountingAuditTrail::record(
                            $domainUuid,
                            'IDEMPOTENCY_LEASE_FENCED',
                            'expense_voucher',
                            $reference,
                            [
                                'error' => $e->getMessage(),
                                'idempotency_key' => $idempotencyKey,
                            ],
                            $userId,
                            $userName
                        );
                    }
                } catch (Throwable $auditEx) {
                    \Log::warning('Failed to record IDEMPOTENCY_LEASE_FENCED audit trail: ' . $auditEx->getMessage());
                }

                throw $e;
            } catch (Throwable $e) {
                // Guard against post-commit response/delivery timeouts:
                // If the outer DB::transaction already committed, the ledger entry, domain association,
                // idempotency 'committed' status, and INTEGRATION_EXPENSE_POSTED audit are permanently persisted.
                // We must NEVER contradict a committed transaction by recording UnpostedIntegrationEntry::recordFailure
                // or INTEGRATION_POSTING_FAILED.
                $freshIdempotency = $idempotencyRecord ? $idempotencyRecord->fresh() : null;
                if ($freshIdempotency && $freshIdempotency->isCommitted()) {
                    try {
                        if (class_exists(AccountingAuditTrail::class)) {
                            AccountingAuditTrail::record(
                                $domainUuid,
                                'INTEGRATION_POST_COMMIT_DELIVERY_TIMEOUT',
                                'expense_voucher',
                                $reference,
                                [
                                    'error' => $e->getMessage(),
                                    'idempotency_key' => $idempotencyKey,
                                    'journal_entry_id' => $freshIdempotency->journal_entry_id,
                                ],
                                $userId,
                                $userName
                            );
                        }
                    } catch (Throwable $auditEx) {
                        \Log::warning('Failed to record INTEGRATION_POST_COMMIT_DELIVERY_TIMEOUT audit trail: ' . $auditEx->getMessage());
                    }

                    throw $e;
                }

                // Outer DB::transaction has rolled back all partial ledger, domain, idempotency-commit, and success-audit writes.
                // Record failure on the reserved idempotency record (fenced by lease_token and in_flight status).
                try {
                    if ($idempotencyRecord) {
                        $idempotencyRecord->markFailed($e->getMessage());
                    }
                } catch (Throwable $markEx) {
                    \Log::warning('Failed to mark idempotency record as failed after transaction rollback: ' . $markEx->getMessage());
                }

                // Record failure in durable unposted queue (separate post-rollback transaction)
                try {
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
                } catch (Throwable $queueEx) {
                    \Log::warning('Failed to record unposted integration entry after transaction rollback: ' . $queueEx->getMessage());
                }

                try {
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
                } catch (Throwable $auditEx) {
                    \Log::warning('Failed to record INTEGRATION_POSTING_FAILED audit trail: ' . $auditEx->getMessage());
                }

                throw $e;
            }
        });
    }

    /**
     * Deterministically reconcile a failed or unposted integration entry without creating
     * ambiguous duplicate postings. Returns actionable stable status codes for both
     * auto-resolved and non-auto-resolvable states.
     *
     * @param int|UnpostedIntegrationEntry $entry
     * @return array{
     *     status: string,
     *     actionable_code: string,
     *     reconciled: bool,
     *     voucher_reference?: string,
     *     journal_entry_id?: int|string|null,
     *     conflicting_journal_entry_id?: int|string|null,
     *     retry_after_seconds?: int,
     *     retry_count?: int,
     *     error_message?: string|null,
     *     message?: string
     * }
     */
    public function reconcileUnpostedEntry(int|UnpostedIntegrationEntry $entry): array
    {
        $unposted = $entry instanceof UnpostedIntegrationEntry
            ? ($entry->fresh() ?? $entry)
            : UnpostedIntegrationEntry::findOrFail($entry);

        if ($unposted->isResolved()) {
            return [
                'status' => 'already_resolved',
                'actionable_code' => 'ALREADY_RESOLVED',
                'reconciled' => true,
                'voucher_reference' => $unposted->source_reference,
                'resolved_voucher_ref' => $unposted->resolved_voucher_ref,
                'message' => 'Unposted integration entry was already resolved.',
            ];
        }

        $domainUuid = $unposted->domain_uuid;
        $companyCode = $unposted->company_code;
        $reference = trim((string) ($unposted->source_reference ?: ($unposted->payload['reference'] ?? '')));
        $payload = is_array($unposted->payload) ? $unposted->payload : [];

        $existingRecord = PostingIdempotencyRecord::forDomain($domainUuid)
            ->where('idempotency_key', $unposted->idempotency_key)
            ->first();

        // 1. If the idempotency record is already committed, verify payload agreement and resolve without re-posting
        if ($existingRecord && $existingRecord->isCommitted()) {
            $existingBaseHash = !empty($existingRecord->request_payload) && is_array($existingRecord->request_payload)
                ? $this->idempotencyService->computePayloadHash($existingRecord->request_payload, $companyCode)
                : $existingRecord->payload_hash;
            $unpostedBaseHash = $this->idempotencyService->computePayloadHash($payload, $companyCode);

            if ($existingBaseHash !== $unpostedBaseHash && $existingRecord->payload_hash !== $unpostedBaseHash) {
                $reason = "Payload in unposted queue conflicts with already-committed idempotency record '{$unposted->idempotency_key}' (voucher '{$existingRecord->voucher_reference}', journalEntryId={$existingRecord->journal_entry_id}). Manual review required.";
                $unposted->markReconciliationRequired($reason);

                if (class_exists(AccountingAuditTrail::class)) {
                    AccountingAuditTrail::record(
                        $domainUuid,
                        'UNPOSTED_RECONCILIATION_CONFLICT',
                        'expense_voucher',
                        $reference,
                        [
                            'idempotency_key' => $unposted->idempotency_key,
                            'committed_journal_entry_id' => $existingRecord->journal_entry_id,
                            'error' => $reason,
                        ],
                        'system',
                        'Unposted Entry Reconciler'
                    );
                }

                return [
                    'status' => 'reconciliation_required',
                    'actionable_code' => 'RECONCILIATION_PAYLOAD_CONFLICT',
                    'reconciled' => false,
                    'voucher_reference' => $reference,
                    'conflicting_journal_entry_id' => $existingRecord->journal_entry_id,
                    'error_message' => $reason,
                    'message' => $reason,
                ];
            }

            $resolvedRef = (string) ($existingRecord->journal_entry_id ?? $existingRecord->voucher_reference ?? $reference);
            $unposted->markResolved($resolvedRef);

            if (class_exists(AccountingAuditTrail::class)) {
                AccountingAuditTrail::record(
                    $domainUuid,
                    'UNPOSTED_RECONCILED_EXISTING_COMMIT',
                    'expense_voucher',
                    $reference,
                    [
                        'idempotency_key' => $unposted->idempotency_key,
                        'journal_entry_id' => $existingRecord->journal_entry_id,
                        'voucher_reference' => $existingRecord->voucher_reference,
                    ],
                    'system',
                    'Unposted Entry Reconciler'
                );
            }

            return [
                'status' => 'reconciled_from_committed',
                'actionable_code' => 'RECONCILED_EXISTING_COMMIT',
                'reconciled' => true,
                'is_idempotent_replay' => true,
                'voucher_reference' => $existingRecord->voucher_reference ?? $reference,
                'journal_entry_id' => $existingRecord->journal_entry_id,
                'message' => 'Reconciled against already-committed posting without duplicate ledger write.',
            ];
        }

        // 2. If the idempotency record is currently in-flight with an active (unexpired) lease, do not steal it
        if ($existingRecord && $existingRecord->isInFlight() && !$existingRecord->isLeaseExpired()) {
            return [
                'status' => 'in_progress',
                'actionable_code' => 'ACTIVE_LEASE_IN_PROGRESS',
                'reconciled' => false,
                'voucher_reference' => $reference,
                'retry_after_seconds' => 2,
                'message' => 'Posting is currently in-flight under an active lease; retry after lease completion.',
            ];
        }

        // 3. Check if a DomainJournalEntry with this reference already exists in the ledger without a committed idempotency link
        if ($reference !== '') {
            $existingDomainEntry = DomainJournalEntry::where('domainUuid', $domainUuid)
                ->where('reference', $reference)
                ->first();

            if ($existingDomainEntry) {
                $reason = "Voucher reference '{$reference}' already exists in ledger (journalEntryId={$existingDomainEntry->journalEntryId}) without a committed idempotency record for key '{$unposted->idempotency_key}'. Manual accountant review required.";
                $unposted->markReconciliationRequired($reason);

                if ($existingRecord && $existingRecord->isInFlight() && $existingRecord->isLeaseExpired()) {
                    $existingRecord->markFailed($reason);
                }

                if (class_exists(AccountingAuditTrail::class)) {
                    AccountingAuditTrail::record(
                        $domainUuid,
                        'UNPOSTED_RECONCILIATION_AMBIGUOUS_REFERENCE',
                        'expense_voucher',
                        $reference,
                        [
                            'idempotency_key' => $unposted->idempotency_key,
                            'conflicting_journal_entry_id' => $existingDomainEntry->journalEntryId,
                            'error' => $reason,
                        ],
                        'system',
                        'Unposted Entry Reconciler'
                    );
                }

                return [
                    'status' => 'reconciliation_required',
                    'actionable_code' => 'AMBIGUOUS_EXISTING_VOUCHER_REFERENCE',
                    'reconciled' => false,
                    'voucher_reference' => $reference,
                    'conflicting_journal_entry_id' => $existingDomainEntry->journalEntryId,
                    'error_message' => $reason,
                    'message' => $reason,
                ];
            }
        }

        // 4. Attempt clean re-posting through the atomic processExpense pipeline
        try {
            $result = $this->processExpense($payload, $companyCode);

            return array_merge($result, [
                'status' => 'reconciled_posted',
                'actionable_code' => 'RECONCILED_NEW_POSTING',
                'reconciled' => true,
            ]);
        } catch (Throwable $e) {
            $refreshed = $unposted->fresh();

            return [
                'status' => 'failed',
                'actionable_code' => 'RECONCILIATION_POSTING_FAILED',
                'reconciled' => false,
                'voucher_reference' => $reference,
                'retry_count' => $refreshed ? $refreshed->retry_count : $unposted->retry_count,
                'error_message' => $e->getMessage(),
                'message' => $e->getMessage(),
            ];
        }
    }
}
