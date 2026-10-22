<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry;
use AlamiaSoft\AlamiaAccounts\Services\AccountMappingResolver;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ExpenseIntegrationService;
use AlamiaSoft\AlamiaAccounts\Services\PostingIdempotencyService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FailureRecoveryAndTimeoutRetryTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseIntegrationService $expenseService;
    protected PostingIdempotencyService $idempotencyService;
    protected VoucherService $voucherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();

        $this->expenseService = app(ExpenseIntegrationService::class);
        $this->idempotencyService = app(PostingIdempotencyService::class);
        $this->voucherService = app(VoucherService::class);
    }

    protected function tearDown(): void
    {
        $this->expenseService->onAfterCommit(null);
        Event::forget('eloquent.creating: ' . DomainJournalEntry::class);
        Event::forget('eloquent.creating: ' . AccountingAuditTrail::class);
        Event::forget(QueryExecuted::class);
        parent::tearDown();
    }

    protected function getDomainUuid(string $code): string
    {
        $domain = app(CompanyService::class)->getDomain($code);
        if (!$domain) {
            throw new RuntimeException("Domain {$code} not found");
        }
        return $domain->domainUuid;
    }

    /**
     * Test 1: Failure at the journal write boundary rolls back cleanly, leaves a durable
     * reconcilable failure state, rejects repeated failing retries without duplicates,
     * and recovers cleanly once the fault is cleared.
     */
    public function test_journal_write_failure_rolls_back_and_records_durable_reconcilable_failure_state(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeBefore = DB::table('journal_entries')->count();
        $djeBefore = DB::table('domain_journal_entries')->count();

        $payload = [
            'reference' => 'EXP-2026-REC-JRNL-001',
            'amount' => 1400.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Metro Supplies',
            'description' => 'Journal write failure injection test',
            'currency' => 'PKR',
        ];

        $shouldFailJournalWrite = true;
        DB::listen(function (QueryExecuted $query) use (&$shouldFailJournalWrite) {
            $sql = strtolower($query->sql);
            if ($shouldFailJournalWrite && str_contains($sql, 'insert into') && str_contains($sql, 'journal_entries') && !str_contains($sql, 'domain_journal_entries')) {
                throw new RuntimeException('Simulated database failure during journal_entries write');
            }
        });

        // Attempt 1: Fails at journal write boundary
        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected RuntimeException during journal_entries write');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated database failure during journal_entries write', $e->getMessage());
        }

        // Verify complete rollback and synchronized failure state across idempotency, unposted queue, and audit
        $this->assertSame($jeBefore, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore, DB::table('domain_journal_entries')->count());

        $idemp = PostingIdempotencyRecord::where('domain_uuid', $domainUuid)
            ->where('source_transaction_id', 'EXP-2026-REC-JRNL-001')
            ->first();
        $this->assertNotNull($idemp);
        $this->assertSame('failed', $idemp->status);
        $this->assertNull($idemp->journal_entry_id);
        $this->assertSame(1, $idemp->lease_generation);

        $unposted = UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
            ->where('source_reference', 'EXP-2026-REC-JRNL-001')
            ->first();
        $this->assertNotNull($unposted);
        $this->assertSame('failed', $unposted->status);
        $this->assertSame(1, $unposted->retry_count);

        $this->assertSame(
            0,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-REC-JRNL-001')
                ->whereIn('action', ['CREATE_VOUCHER', 'INTEGRATION_EXPENSE_POSTED'])
                ->count()
        );
        $this->assertSame(
            1,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-REC-JRNL-001')
                ->where('action', 'INTEGRATION_POSTING_FAILED')
                ->count()
        );

        // Attempt 2: Retry while fault is STILL active -> must not create duplicates or claim false success
        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected second attempt to fail while fault remains active');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated database failure during journal_entries write', $e->getMessage());
        }

        $this->assertSame($jeBefore, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore, DB::table('domain_journal_entries')->count());
        $this->assertSame(1, PostingIdempotencyRecord::where('domain_uuid', $domainUuid)->where('source_transaction_id', 'EXP-2026-REC-JRNL-001')->count());
        $this->assertSame(1, UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)->where('source_reference', 'EXP-2026-REC-JRNL-001')->count());

        $idemp->refresh();
        $unposted->refresh();
        $this->assertSame('failed', $idemp->status);
        $this->assertSame(2, $idemp->lease_generation);
        $this->assertSame('failed', $unposted->status);
        $this->assertSame(2, $unposted->retry_count);

        // Attempt 3: Clear fault and retry -> must post once and synchronize all states
        $shouldFailJournalWrite = false;
        $result = $this->expenseService->processExpense($payload, 'MAIN');

        $this->assertSame('posted', $result['status']);
        $this->assertFalse($result['is_idempotent_replay']);
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore + 1, DB::table('domain_journal_entries')->count());

        $idemp->refresh();
        $unposted->refresh();
        $this->assertSame('committed', $idemp->status);
        $this->assertSame(3, $idemp->lease_generation);
        $this->assertSame((int) $result['journal_entry_id'], $idemp->journal_entry_id);
        $this->assertSame('resolved', $unposted->status);
        $this->assertSame((string) $result['journal_entry_id'], $unposted->resolved_voucher_ref);
        $this->assertNull($unposted->error_message);
    }

    /**
     * Test 2: Failure at the domain association boundary rolls back Abivia savepoint
     * and reconciles cleanly via reconcileUnpostedEntry().
     */
    public function test_domain_association_failure_rolls_back_journal_and_reconciles_cleanly(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeBefore = DB::table('journal_entries')->count();
        $jdBefore = DB::table('journal_details')->count();
        $djeBefore = DB::table('domain_journal_entries')->count();

        $payload = [
            'reference' => 'EXP-2026-REC-DOM-002',
            'amount' => 875.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Domain Boundary Vendor',
            'currency' => 'PKR',
        ];

        $failDomainLink = true;
        DomainJournalEntry::creating(function (DomainJournalEntry $entry) use (&$failDomainLink) {
            if ($failDomainLink && $entry->reference === 'EXP-2026-REC-DOM-002') {
                throw new RuntimeException('Simulated failure during domain_journal_entries association');
            }
        });

        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected RuntimeException during domain association');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated failure during domain_journal_entries association', $e->getMessage());
        }

        // Abivia journal_entries and journal_details must be rolled back across the nested savepoint
        $this->assertSame($jeBefore, DB::table('journal_entries')->count());
        $this->assertSame($jdBefore, DB::table('journal_details')->count());
        $this->assertSame($djeBefore, DB::table('domain_journal_entries')->count());

        $unposted = UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
            ->where('source_reference', 'EXP-2026-REC-DOM-002')
            ->first();
        $this->assertNotNull($unposted);
        $this->assertSame('failed', $unposted->status);

        // Reconcile while fault is still active -> returns actionable RECONCILIATION_POSTING_FAILED
        $failedReconcile = $this->expenseService->reconcileUnpostedEntry($unposted);
        $this->assertFalse($failedReconcile['reconciled']);
        $this->assertSame('RECONCILIATION_POSTING_FAILED', $failedReconcile['actionable_code']);
        $this->assertSame(2, $failedReconcile['retry_count']);
        $this->assertSame($jeBefore, DB::table('journal_entries')->count());

        // Clear fault and reconcile via reconcileUnpostedEntry()
        $failDomainLink = false;
        $reconciled = $this->expenseService->reconcileUnpostedEntry($unposted);

        $this->assertTrue($reconciled['reconciled']);
        $this->assertSame('RECONCILED_NEW_POSTING', $reconciled['actionable_code']);
        $this->assertSame('reconciled_posted', $reconciled['status']);
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore + 1, DB::table('domain_journal_entries')->count());

        $unposted->refresh();
        $this->assertSame('resolved', $unposted->status);
        $this->assertNull($unposted->error_message);

        // Reconciling an already-resolved entry is a safe idempotent no-op
        $secondReconcile = $this->expenseService->reconcileUnpostedEntry($unposted);
        $this->assertTrue($secondReconcile['reconciled']);
        $this->assertSame('ALREADY_RESOLVED', $secondReconcile['actionable_code']);
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
    }

    /**
     * Test 3: Database error during the idempotency status transition to 'committed'
     * rolls back the journal entry and domain link, transitions to 'failed', and recovers on retry.
     */
    public function test_idempotency_status_update_db_error_rolls_back_journal_and_recovers_on_retry(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeBefore = DB::table('journal_entries')->count();
        $djeBefore = DB::table('domain_journal_entries')->count();

        $payload = [
            'reference' => 'EXP-2026-REC-IDEMP-003',
            'amount' => 640.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Idempotency Boundary Vendor',
            'currency' => 'PKR',
        ];

        $failCommittedUpdate = true;
        DB::listen(function (QueryExecuted $query) use (&$failCommittedUpdate) {
            $sql = strtolower($query->sql);
            if ($failCommittedUpdate && str_contains($sql, 'update') && str_contains($sql, 'posting_idempotency_records')) {
                foreach ($query->bindings as $binding) {
                    if ($binding === 'committed') {
                        throw new RuntimeException('Simulated DB failure updating idempotency status to committed');
                    }
                }
            }
        });

        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected RuntimeException during idempotency status update');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated DB failure updating idempotency status to committed', $e->getMessage());
        }

        // Journal and domain entry must have been rolled back
        $this->assertSame($jeBefore, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore, DB::table('domain_journal_entries')->count());

        $idemp = PostingIdempotencyRecord::where('domain_uuid', $domainUuid)
            ->where('source_transaction_id', 'EXP-2026-REC-IDEMP-003')
            ->first();
        $this->assertNotNull($idemp);
        $this->assertSame('failed', $idemp->status);
        $this->assertNull($idemp->journal_entry_id);

        $unposted = UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
            ->where('source_reference', 'EXP-2026-REC-IDEMP-003')
            ->first();
        $this->assertNotNull($unposted);
        $this->assertSame('failed', $unposted->status);

        // Clear fault and retry
        $failCommittedUpdate = false;
        $result = $this->expenseService->processExpense($payload, 'MAIN');

        $this->assertSame('posted', $result['status']);
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore + 1, DB::table('domain_journal_entries')->count());

        $idemp->refresh();
        $unposted->refresh();
        $this->assertSame('committed', $idemp->status);
        $this->assertSame('resolved', $unposted->status);
    }

    /**
     * Test 4: Failure at the audit recording boundary rolls back journal, domain association,
     * and idempotency commit, and reconciles cleanly via reconcileUnpostedEntry().
     */
    public function test_audit_recording_failure_rolls_back_journal_and_idempotency_commit_and_reconciles(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeBefore = DB::table('journal_entries')->count();
        $djeBefore = DB::table('domain_journal_entries')->count();

        $payload = [
            'reference' => 'EXP-2026-REC-AUD-004',
            'amount' => 1120.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Audit Boundary Vendor',
            'currency' => 'PKR',
        ];

        $failAudit = true;
        AccountingAuditTrail::creating(function (AccountingAuditTrail $audit) use (&$failAudit) {
            if ($failAudit && $audit->action === 'INTEGRATION_EXPENSE_POSTED' && $audit->entity_reference === 'EXP-2026-REC-AUD-004') {
                throw new RuntimeException('Simulated failure writing INTEGRATION_EXPENSE_POSTED audit record');
            }
        });

        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected RuntimeException on audit write');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated failure writing INTEGRATION_EXPENSE_POSTED audit record', $e->getMessage());
        }

        $this->assertSame($jeBefore, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore, DB::table('domain_journal_entries')->count());

        $idemp = PostingIdempotencyRecord::where('domain_uuid', $domainUuid)
            ->where('source_transaction_id', 'EXP-2026-REC-AUD-004')
            ->first();
        $this->assertNotNull($idemp);
        $this->assertSame('failed', $idemp->status);

        $unposted = UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
            ->where('source_reference', 'EXP-2026-REC-AUD-004')
            ->first();
        $this->assertNotNull($unposted);
        $this->assertSame('failed', $unposted->status);

        // Clear fault and reconcile
        $failAudit = false;
        $reconciled = $this->expenseService->reconcileUnpostedEntry($unposted);

        $this->assertTrue($reconciled['reconciled']);
        $this->assertSame('RECONCILED_NEW_POSTING', $reconciled['actionable_code']);
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore + 1, DB::table('domain_journal_entries')->count());

        $idemp->refresh();
        $unposted->refresh();
        $this->assertSame('committed', $idemp->status);
        $this->assertSame('resolved', $unposted->status);
    }

    /**
     * Test 5: Simulated post-commit response timeout preserves the committed ledger and idempotency
     * state (without recording a false failure in unposted_integration_entries), and caller retry
     * returns the original posting without creating a duplicate.
     */
    public function test_retry_after_simulated_post_commit_timeout_returns_original_posting_without_duplicate(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeBefore = DB::table('journal_entries')->count();
        $djeBefore = DB::table('domain_journal_entries')->count();

        $payload = [
            'reference' => 'EXP-2026-POST-TIMEOUT-005',
            'amount' => 2350.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Timeout Recovery Suppliers',
            'description' => 'Post-commit response timeout test',
            'currency' => 'PKR',
        ];

        $committedJournalIdDuringAttempt1 = null;
        $this->expenseService->onAfterCommit(function (array $responseData) use (&$committedJournalIdDuringAttempt1) {
            $committedJournalIdDuringAttempt1 = $responseData['journal_entry_id'];
            throw new RuntimeException('Simulated HTTP gateway timeout after database transaction commit');
        });

        // Attempt 1: Database commits at depth 0, then caller experiences a response timeout
        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected simulated post-commit timeout exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated HTTP gateway timeout after database transaction commit', $e->getMessage());
        }

        $this->assertNotNull($committedJournalIdDuringAttempt1);

        // 1. Ledger entry and domain association ARE committed in the database
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore + 1, DB::table('domain_journal_entries')->count());

        // 2. Idempotency record MUST remain 'committed' (never overwritten to 'failed')
        $idemp = PostingIdempotencyRecord::where('domain_uuid', $domainUuid)
            ->where('source_transaction_id', 'EXP-2026-POST-TIMEOUT-005')
            ->first();
        $this->assertNotNull($idemp);
        $this->assertSame('committed', $idemp->status);
        $this->assertSame((int) $committedJournalIdDuringAttempt1, $idemp->journal_entry_id);

        // 3. Unposted queue MUST NOT contain a false 'failed' entry for a committed transaction
        $this->assertSame(
            0,
            UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
                ->where('source_reference', 'EXP-2026-POST-TIMEOUT-005')
                ->where('status', 'failed')
                ->count()
        );

        // 4. Audit trail MUST have CREATE_VOUCHER, INTEGRATION_EXPENSE_POSTED, and INTEGRATION_POST_COMMIT_DELIVERY_TIMEOUT,
        //    and ZERO INTEGRATION_POSTING_FAILED records
        $this->assertSame(
            1,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-POST-TIMEOUT-005')
                ->where('action', 'INTEGRATION_EXPENSE_POSTED')
                ->count()
        );
        $this->assertSame(
            1,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-POST-TIMEOUT-005')
                ->where('action', 'INTEGRATION_POST_COMMIT_DELIVERY_TIMEOUT')
                ->count()
        );
        $this->assertSame(
            0,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-POST-TIMEOUT-005')
                ->where('action', 'INTEGRATION_POSTING_FAILED')
                ->count()
        );

        // Attempt 2: Caller retries after timeout -> returns original posting without duplicating
        $this->expenseService->onAfterCommit(null);
        $retryResult = $this->expenseService->processExpense($payload, 'MAIN');

        $this->assertSame('posted', $retryResult['status']);
        $this->assertTrue($retryResult['is_idempotent_replay']);
        $this->assertSame('EXP-2026-POST-TIMEOUT-005', $retryResult['voucher_reference']);
        $this->assertSame($committedJournalIdDuringAttempt1, $retryResult['journal_entry_id']);

        // Invariant: Still only 1 journal entry and 1 domain association in the ledger
        $this->assertSame($jeBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeBefore + 1, DB::table('domain_journal_entries')->count());
    }

    /**
     * Test 6: Stale lease sweeper recovers stranded expired in-flight records into a durable
     * reconcilable failure state, and NEVER steals a live lease or contradicts a committed record.
     */
    public function test_sweeper_recovers_expired_in_flight_record_and_never_steals_live_lease_or_contradicts_commit(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $mappingResolver = app(AccountMappingResolver::class);
        $debit = $mappingResolver->resolveExpenseAccount('office_supplies', $domainUuid);
        $credit = $mappingResolver->resolvePaymentSourceAccount('cash', $domainUuid);

        // 1. Stranded expired in-flight record (simulating worker crash before catch block)
        $expiredPayload = [
            'reference' => 'EXP-2026-SWEEP-EXPIRED',
            'amount' => 450.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Stranded Worker Vendor',
            'currency' => 'PKR',
        ];
        $expiredHash = $this->idempotencyService->computePayloadHash($expiredPayload, 'MAIN', [
            'resolved_debit_account' => $debit,
            'resolved_credit_account' => $credit,
        ]);
        $expiredRecord = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => 'travelos:MAIN:EXP-2026-SWEEP-EXPIRED:post:v1',
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-SWEEP-EXPIRED',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => $expiredHash,
            'request_payload' => $expiredPayload,
            'status' => 'in_flight',
            'lease_token' => (string) Str::uuid(),
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->subMinutes(10),
        ]);

        // 2. Live in-flight record (unexpired lease)
        $liveToken = (string) Str::uuid();
        $liveRecord = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => 'travelos:MAIN:EXP-2026-SWEEP-LIVE:post:v1',
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-SWEEP-LIVE',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'live-payload'),
            'request_payload' => ['reference' => 'EXP-2026-SWEEP-LIVE', 'amount' => 300.00],
            'status' => 'in_flight',
            'lease_token' => $liveToken,
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // 3. Already committed record
        $committedResult = $this->expenseService->processExpense([
            'reference' => 'EXP-2026-SWEEP-COMMITTED',
            'amount' => 900.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Committed Vendor',
            'currency' => 'PKR',
        ], 'MAIN');

        // Run the sweeper
        $sweepSummary = $this->idempotencyService->sweepStaleInFlightRecords($domainUuid);

        $this->assertSame(1, $sweepSummary['swept_count']);
        $this->assertSame(0, $sweepSummary['reconciliation_required_count']);

        // Verify expired record transitioned to 'failed' with synchronized unposted entry and audit
        $expiredRecord->refresh();
        $this->assertSame('failed', $expiredRecord->status);
        $this->assertStringContainsString('recovery sweeper', (string) $expiredRecord->error_message);

        $sweptUnposted = UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
            ->where('source_reference', 'EXP-2026-SWEEP-EXPIRED')
            ->first();
        $this->assertNotNull($sweptUnposted);
        $this->assertSame('failed', $sweptUnposted->status);

        $this->assertSame(
            1,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-SWEEP-EXPIRED')
                ->where('action', 'IDEMPOTENCY_STALE_LEASE_SWEPT')
                ->count()
        );

        // Verify live lease was NOT stolen or modified
        $liveRecord->refresh();
        $this->assertSame('in_flight', $liveRecord->status);
        $this->assertSame($liveToken, $liveRecord->lease_token);
        $this->assertSame(1, $liveRecord->lease_generation);
        $this->assertNull(
            UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
                ->where('source_reference', 'EXP-2026-SWEEP-LIVE')
                ->first()
        );

        // Verify committed record was NOT contradicted
        $committedRecord = PostingIdempotencyRecord::where('domain_uuid', $domainUuid)
            ->where('source_transaction_id', 'EXP-2026-SWEEP-COMMITTED')
            ->first();
        $this->assertSame('committed', $committedRecord->status);
        $this->assertSame((int) $committedResult['journal_entry_id'], $committedRecord->journal_entry_id);

        // Now reconcile the swept unposted entry -> posts cleanly once
        $reconciled = $this->expenseService->reconcileUnpostedEntry($sweptUnposted);
        $this->assertTrue($reconciled['reconciled']);
        $this->assertSame('RECONCILED_NEW_POSTING', $reconciled['actionable_code']);
        $this->assertSame('resolved', $sweptUnposted->fresh()->status);
        $this->assertSame('committed', $expiredRecord->fresh()->status);
    }

    /**
     * Test 7: Reconciling unposted entries handles non-auto-resolvable ambiguous voucher references,
     * active in-flight leases, and already-committed postings with stable actionable status codes.
     */
    public function test_reconciliation_handles_ambiguous_existing_reference_active_lease_and_already_committed_states(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $mappingResolver = app(AccountMappingResolver::class);
        $debit = $mappingResolver->resolveExpenseAccount('office_supplies', $domainUuid);
        $credit = $mappingResolver->resolvePaymentSourceAccount('cash', $domainUuid);

        // --- Sub-case 7A: Ambiguous existing voucher reference in ledger ---
        $ambiguousPayload = [
            'reference' => 'EXP-2026-AMB-001',
            'amount' => 700.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Ambiguous Ref Vendor',
            'currency' => 'PKR',
        ];
        $ambiguousKey = 'travelos:MAIN:EXP-2026-AMB-001:post:v1';

        $ambiguousUnposted = UnpostedIntegrationEntry::recordFailure(
            $domainUuid,
            'MAIN',
            'travelos',
            'EXP-2026-AMB-001',
            $ambiguousKey,
            'expense',
            $ambiguousPayload,
            'Initial transient failure'
        );

        // An accountant manually posts a voucher using reference 'EXP-2026-AMB-001' directly via VoucherService
        $manualJournal = DomainContext::scope('MAIN', fn () => $this->voucherService->createJournalEntry([
            'reference' => 'EXP-2026-AMB-001',
            'date' => now()->format('Y-m-d'),
            'currency' => 'PKR',
            'description' => 'Manual voucher using same reference',
            'entries' => [
                ['account_code' => $debit, 'debit' => 700.00, 'credit' => 0.00, 'description' => 'Manual Dr'],
                ['account_code' => $credit, 'debit' => 0.00, 'credit' => 700.00, 'description' => 'Manual Cr'],
            ],
        ]));
        $manualJournalId = $manualJournal->journalEntryId ?? $manualJournal['id'];

        $jeBeforeAmbiguousReconcile = DB::table('journal_entries')->count();

        // Attempting to auto-reconcile must detect the unlinked existing DomainJournalEntry and refuse to double-post
        $ambiguousResult = $this->expenseService->reconcileUnpostedEntry($ambiguousUnposted);

        $this->assertFalse($ambiguousResult['reconciled']);
        $this->assertSame('reconciliation_required', $ambiguousResult['status']);
        $this->assertSame('AMBIGUOUS_EXISTING_VOUCHER_REFERENCE', $ambiguousResult['actionable_code']);
        $this->assertSame($manualJournalId, $ambiguousResult['conflicting_journal_entry_id']);
        $this->assertSame($jeBeforeAmbiguousReconcile, DB::table('journal_entries')->count());

        $ambiguousUnposted->refresh();
        $this->assertSame('reconciliation_required', $ambiguousUnposted->status);
        $this->assertSame(
            1,
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('entity_reference', 'EXP-2026-AMB-001')
                ->where('action', 'UNPOSTED_RECONCILIATION_AMBIGUOUS_REFERENCE')
                ->count()
        );

        // --- Sub-case 7B: Unposted entry whose idempotency record is already committed ---
        $committedPayload = [
            'reference' => 'EXP-2026-ALREADY-COMMITTED-002',
            'amount' => 520.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Already Committed Vendor',
            'currency' => 'PKR',
        ];
        $posted = $this->expenseService->processExpense($committedPayload, 'MAIN');
        $committedKey = 'travelos:MAIN:EXP-2026-ALREADY-COMMITTED-002:post:v1';

        // Simulate a stale failed unposted entry for the same key
        $staleUnposted = UnpostedIntegrationEntry::recordFailure(
            $domainUuid,
            'MAIN',
            'travelos',
            'EXP-2026-ALREADY-COMMITTED-002',
            $committedKey,
            'expense',
            $committedPayload,
            'Stale unposted row prior to commit'
        );

        $jeBeforeCommittedReconcile = DB::table('journal_entries')->count();
        $reconciledFromCommitted = $this->expenseService->reconcileUnpostedEntry($staleUnposted);

        $this->assertTrue($reconciledFromCommitted['reconciled']);
        $this->assertSame('RECONCILED_EXISTING_COMMIT', $reconciledFromCommitted['actionable_code']);
        $this->assertSame($posted['journal_entry_id'], $reconciledFromCommitted['journal_entry_id']);
        $this->assertSame($jeBeforeCommittedReconcile, DB::table('journal_entries')->count());
        $this->assertSame('resolved', $staleUnposted->fresh()->status);

        // --- Sub-case 7C: Unposted entry while an active lease is in progress ---
        $inFlightKey = 'travelos:MAIN:EXP-2026-INFLIGHT-003:post:v1';
        $inFlightPayload = [
            'reference' => 'EXP-2026-INFLIGHT-003',
            'amount' => 310.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
        ];
        PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => $inFlightKey,
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-INFLIGHT-003',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => $this->idempotencyService->computePayloadHash($inFlightPayload, 'MAIN'),
            'request_payload' => $inFlightPayload,
            'status' => 'in_flight',
            'lease_token' => (string) Str::uuid(),
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->addMinutes(2),
        ]);
        $inFlightUnposted = UnpostedIntegrationEntry::recordFailure(
            $domainUuid,
            'MAIN',
            'travelos',
            'EXP-2026-INFLIGHT-003',
            $inFlightKey,
            'expense',
            $inFlightPayload,
            'Prior failure'
        );

        $inProgressResult = $this->expenseService->reconcileUnpostedEntry($inFlightUnposted);
        $this->assertFalse($inProgressResult['reconciled']);
        $this->assertSame('ACTIVE_LEASE_IN_PROGRESS', $inProgressResult['actionable_code']);
        $this->assertSame(2, $inProgressResult['retry_after_seconds']);
    }

    /**
     * Test 8: Mutated payload on a failed idempotency record is rejected with IdempotencyPayloadMismatchException,
     * and unposted_integration_entries enforces tenant isolation across companies sharing an idempotency key.
     */
    public function test_mutated_payload_on_failed_record_is_rejected_and_cross_tenant_unposted_entries_remain_isolated(): void
    {
        $mainDomain = $this->getDomainUuid('MAIN');
        $kamalDomain = $this->getDomainUuid('KAMAL_EXPRESS');

        $payloadMain = [
            'reference' => 'EXP-2026-SHARED-FAIL-001',
            'idempotency_key' => 'SHARED-CLIENT-KEY-FAIL-001',
            'amount' => 500.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Main Vendor',
            'currency' => 'PKR',
        ];

        $failOnce = true;
        DomainJournalEntry::creating(function () use (&$failOnce) {
            if ($failOnce) {
                throw new RuntimeException('Simulated pre-commit failure');
            }
        });

        // 1. MAIN fails pre-commit
        try {
            $this->expenseService->processExpense($payloadMain, 'MAIN');
            $this->fail('Expected failure in MAIN');
        } catch (RuntimeException) {
        }

        // 2. KAMAL_EXPRESS also fails pre-commit with the SAME client idempotency_key
        try {
            $this->expenseService->processExpense($payloadMain, 'KAMAL_EXPRESS');
            $this->fail('Expected failure in KAMAL_EXPRESS');
        } catch (RuntimeException) {
        }

        // Both tenants must have their own isolated row in unposted_integration_entries
        $mainUnposted = UnpostedIntegrationEntry::where('domain_uuid', $mainDomain)
            ->where('idempotency_key', 'SHARED-CLIENT-KEY-FAIL-001')
            ->first();
        $kamalUnposted = UnpostedIntegrationEntry::where('domain_uuid', $kamalDomain)
            ->where('idempotency_key', 'SHARED-CLIENT-KEY-FAIL-001')
            ->first();

        $this->assertNotNull($mainUnposted);
        $this->assertNotNull($kamalUnposted);
        $this->assertNotSame($mainUnposted->id, $kamalUnposted->id);
        $this->assertSame('failed', $mainUnposted->status);
        $this->assertSame('failed', $kamalUnposted->status);

        $failOnce = false;

        // 3. Mutating the amount on MAIN's failed record under the same identity must be rejected as a payload conflict
        $mutatedMain = $payloadMain;
        $mutatedMain['amount'] = 9999.00;

        try {
            $this->expenseService->processExpense($mutatedMain, 'MAIN');
            $this->fail('Expected IdempotencyPayloadMismatchException when mutating amount on a failed key');
        } catch (IdempotencyPayloadMismatchException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // MAIN's unposted entry must still be 'failed' (not falsely resolved!)
        $this->assertSame('failed', $mainUnposted->fresh()->status);

        // 4. Retrying MAIN with the original payload succeeds and resolves ONLY MAIN's unposted entry
        $mainResult = $this->expenseService->processExpense($payloadMain, 'MAIN');
        $this->assertSame('posted', $mainResult['status']);
        $this->assertSame('resolved', $mainUnposted->fresh()->status);
        $this->assertSame('failed', $kamalUnposted->fresh()->status, 'KAMAL_EXPRESS unposted entry must remain failed until KAMAL_EXPRESS retries');
    }
}
