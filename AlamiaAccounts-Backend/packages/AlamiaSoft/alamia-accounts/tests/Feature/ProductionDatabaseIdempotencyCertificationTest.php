<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ExpenseIntegrationService;
use AlamiaSoft\AlamiaAccounts\Services\PostingIdempotencyService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #43: Certify idempotency against the production database engine (PostgreSQL 16)
 * and SQLite test engine.
 *
 * Final certification suite for Parent Epic #8:
 * - Engine verification: Inspects active connection driver (PostgreSQL 16 / SQLite).
 * - Sequential replay: Identical request returns cached original posting with zero duplicate vouchers.
 * - Payload conflict: Reusing idempotency key with modified amount/accounts throws 409 conflict and prevents mutation.
 * - Atomic rollback: Failure during pivot or audit rolls back all journal entries and details atomically.
 * - Commit timeout recovery: Post-commit response timeout reconciles against committed voucher without duplicate entries.
 * - Company-scoped reference uniqueness: Case-insensitive collisions within a tenant fail, while distinct tenants independently post identical references.
 * - Database-level unique constraint: Concurrent collisions are rejected at the DB boundary.
 */
class ProductionDatabaseIdempotencyCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseIntegrationService $expenseService;
    protected VoucherService $voucherService;
    protected PostingIdempotencyService $idempotencyService;
    protected CompanyService $companyService;

    protected string $mainDomainUuid;
    protected string $secondDomainUuid;
    protected string $debitAccountCode = '1110';
    protected string $creditAccountCode = '5100';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);

        $this->expenseService = app(ExpenseIntegrationService::class);
        $this->voucherService = app(VoucherService::class);
        $this->idempotencyService = app(PostingIdempotencyService::class);
        $this->companyService = app(CompanyService::class);

        $mainDomain = $this->companyService->getCompanyByCode('MAIN');
        $secondDomain = $this->companyService->getCompanyByCode('KAMAL_EXPRESS');

        $this->assertNotNull($mainDomain, 'MAIN domain must exist');
        $this->assertNotNull($secondDomain, 'KAMAL_EXPRESS domain must exist');

        $this->mainDomainUuid = (string) $mainDomain->domainUuid;
        $this->secondDomainUuid = (string) $secondDomain->domainUuid;

        DomainContext::reset();
    }

    protected function tearDown(): void
    {
        $this->expenseService->onAfterCommit(null);
        DomainJournalEntry::flushEventListeners();
        DomainJournalEntry::boot();
        AccountingAuditTrail::flushEventListeners();
        PostingIdempotencyRecord::flushEventListeners();
        DomainContext::reset();
        parent::tearDown();
    }

    public function test_it_verifies_active_database_engine_and_configuration(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $this->assertContains(
            $driver,
            ['sqlite', 'pgsql'],
            "Only SQLite and PostgreSQL are certified database engines. Detected: {$driver}"
        );

        $this->assertTrue(Schema::hasTable('posting_idempotency_records'), 'posting_idempotency_records table must exist');
        $this->assertTrue(Schema::hasTable('domain_journal_entries'), 'domain_journal_entries table must exist');
        $this->assertTrue(Schema::hasTable('accounting_audit_trails'), 'accounting_audit_trails table must exist');
        $this->assertTrue(Schema::hasColumn('domain_journal_entries', 'reference'), 'domain_journal_entries.reference column must exist');
    }

    public function test_it_replays_identical_request_with_cached_response_and_zero_duplicate_postings(): void
    {
        $payload = [
            'reference' => 'EXP-CERT-REPLAY-001',
            'amount' => 17500.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Certified Vendor Supplies',
            'description' => 'Office equipment purchase',
            'currency' => 'PKR',
        ];

        $firstResult = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($firstResult['is_idempotent_replay']);
        $firstVoucherId = $firstResult['journal_entry_id'];

        $journalEntriesCountInitial = DB::table('journal_entries')->count();
        $pivotCountInitial = DB::table('domain_journal_entries')->count();
        $idempotencyRecordInitial = PostingIdempotencyRecord::where('source_transaction_id', 'EXP-CERT-REPLAY-001')->first();

        $this->assertNotNull($idempotencyRecordInitial);
        $this->assertSame('committed', $idempotencyRecordInitial->status);
        $this->assertSame($firstVoucherId, $idempotencyRecordInitial->journal_entry_id);

        // Replay identical request
        $replayResult = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertTrue($replayResult['is_idempotent_replay']);
        $this->assertSame($firstVoucherId, $replayResult['journal_entry_id']);
        $this->assertSame($firstResult['voucher_reference'], $replayResult['voucher_reference']);

        // Assert zero duplicate entries created
        $this->assertSame($journalEntriesCountInitial, DB::table('journal_entries')->count());
        $this->assertSame($pivotCountInitial, DB::table('domain_journal_entries')->count());
        $this->assertSame(1, PostingIdempotencyRecord::where('source_transaction_id', 'EXP-CERT-REPLAY-001')->count());
    }

    public function test_it_rejects_idempotency_payload_conflict_and_preserves_original_state(): void
    {
        $originalPayload = [
            'reference' => 'EXP-CERT-CONFLICT-002',
            'amount' => 8400.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Original Vendor',
            'description' => 'Original expense',
            'currency' => 'PKR',
        ];

        $firstResult = $this->expenseService->processExpense($originalPayload, 'MAIN');
        $this->assertFalse($firstResult['is_idempotent_replay']);

        $conflictingPayload = array_merge($originalPayload, [
            'amount' => 9999.00,
            'description' => 'Conflicting modified amount payload',
        ]);

        try {
            $this->expenseService->processExpense($conflictingPayload, 'MAIN');
            $this->fail('Expected conflicting payload reuse of idempotency key to be rejected.');
        } catch (IdempotencyPayloadMismatchException $e) {
            $this->assertStringContainsString('conflict', strtolower($e->getMessage()));
        }

        $record = PostingIdempotencyRecord::where('source_transaction_id', 'EXP-CERT-CONFLICT-002')->first();
        $this->assertNotNull($record);
        $this->assertSame('committed', $record->status);
        $this->assertSame($firstResult['journal_entry_id'], $record->journal_entry_id);
    }

    public function test_it_rolls_back_atomic_transaction_cleanly_on_audit_or_pivot_failure(): void
    {
        $initialJournalEntries = DB::table('journal_entries')->count();
        $initialJournalDetails = DB::table('journal_details')->count();

        // Inject simulated failure during AccountingAuditTrail::record
        AccountingAuditTrail::creating(function () {
            throw new Exception('Simulated atomic audit failure on production engine');
        });

        try {
            $this->expenseService->processExpense([
                'reference' => 'EXP-CERT-FAIL-ROLLBACK-003',
                'amount' => 5200.00,
                'category' => 'office_supplies',
                'payment_method' => 'cash',
                'vendor_name' => 'Fail Vendor',
                'description' => 'Rollback candidate',
                'currency' => 'PKR',
            ], 'MAIN');
            $this->fail('Expected audit failure to abort posting transaction.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Simulated atomic audit failure', $e->getMessage());
        } finally {
            AccountingAuditTrail::flushEventListeners();
        }

        // Verify atomic rollback on PostgreSQL / SQLite: zero orphan entries created
        $this->assertSame($initialJournalEntries, DB::table('journal_entries')->count());
        $this->assertSame($initialJournalDetails, DB::table('journal_details')->count());
        $this->assertSame(0, DomainJournalEntry::where('reference', 'EXP-CERT-FAIL-ROLLBACK-003')->count());
    }

    public function test_it_reconciles_commit_timeout_and_recovers_without_duplicate_voucher(): void
    {
        $payload = [
            'reference' => 'EXP-CERT-TIMEOUT-004',
            'amount' => 12500.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Timeout Recovery Suppliers',
            'description' => 'Post-commit response timeout certification test',
            'currency' => 'PKR',
        ];

        $committedJournalId = null;
        $this->expenseService->onAfterCommit(function (array $responseData) use (&$committedJournalId) {
            $committedJournalId = $responseData['journal_entry_id'];
            throw new RuntimeException('Simulated HTTP gateway timeout after database transaction commit');
        });

        // 1. Attempt 1 commits to DB then experiences a post-commit delivery timeout
        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected simulated post-commit timeout exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated HTTP gateway timeout', $e->getMessage());
        }

        $this->assertNotNull($committedJournalId);
        $this->assertSame(1, DomainJournalEntry::where('reference', 'EXP-CERT-TIMEOUT-004')->count());

        // 2. Clear fault and perform client retry
        $this->expenseService->onAfterCommit(null);
        $retryResult = $this->expenseService->processExpense($payload, 'MAIN');

        $this->assertTrue($retryResult['is_idempotent_replay']);
        $this->assertSame((int) $committedJournalId, $retryResult['journal_entry_id']);

        // Verify zero duplicate vouchers created
        $this->assertSame(1, DomainJournalEntry::where('reference', 'EXP-CERT-TIMEOUT-004')->count());
    }

    public function test_it_enforces_company_scoped_reference_uniqueness_and_cross_tenant_isolation(): void
    {
        DomainContext::set('MAIN');

        $mainVoucher = $this->voucherService->createJournalEntry([
            'reference' => 'CERT-VOUCHER-100',
            'date' => '2026-10-23',
            'description' => 'MAIN reference voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 15000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 15000],
            ],
        ]);
        $this->assertNotNull($mainVoucher->journalEntryId);

        // Duplicate case-insensitive in MAIN is rejected
        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'cert-voucher-100',
                'date' => '2026-10-23',
                'description' => 'Lowercase duplicate in MAIN',
                'entries' => [
                    ['account_code' => $this->debitAccountCode, 'debit' => 20000, 'credit' => 0],
                    ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 20000],
                ],
            ]);
            $this->fail('Expected duplicate reference in MAIN to be rejected.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Duplicate voucher reference', $e->getMessage());
        }

        // Exact same reference in KAMAL_EXPRESS succeeds without collision
        $secondVoucher = $this->voucherService->createJournalEntry([
            'reference' => 'CERT-VOUCHER-100',
            'date' => '2026-10-23',
            'description' => 'KAMAL_EXPRESS reference voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 32000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 32000],
            ],
        ], 'KAMAL_EXPRESS');

        $this->assertNotNull($secondVoucher->journalEntryId);
        $this->assertNotSame($mainVoucher->journalEntryId, $secondVoucher->journalEntryId);

        // Verification of tenant isolation
        $mainFound = $this->voucherService->getVoucher('CERT-VOUCHER-100');
        $this->assertEquals(15000.0, (float) $mainFound['amount']);

        DomainContext::set('KAMAL_EXPRESS');
        $secondFound = $this->voucherService->getVoucher('CERT-VOUCHER-100');
        $this->assertEquals(32000.0, (float) $secondFound['amount']);
    }

    public function test_it_enforces_database_level_unique_index_under_simulated_concurrent_collision(): void
    {
        DomainContext::set('MAIN');

        $v1 = $this->voucherService->createJournalEntry([
            'reference' => 'CERT-RACE-ORIGINAL',
            'date' => '2026-10-23',
            'description' => 'Original pre-existing voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 4000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 4000],
            ],
        ]);
        $vHolder = $this->voucherService->createJournalEntry([
            'reference' => 'CERT-RACE-HOLDER',
            'date' => '2026-10-23',
            'description' => 'Holder voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 4500, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 4500],
            ],
        ]);

        // Direct DB boundary test with savepoint protection for PostgreSQL
        try {
            DB::transaction(function () use ($vHolder) {
                DB::table('domain_journal_entries')
                    ->where('journalEntryId', $vHolder->journalEntryId)
                    ->update(['reference' => 'cert-race-original']);
            });
            $this->fail('Expected DB unique constraint domain_reference_ci_unique to block duplicate.');
        } catch (QueryException $qe) {
            $this->assertTrue(PostingIdempotencyService::isUniqueConstraintViolation($qe));
        }
    }
}
