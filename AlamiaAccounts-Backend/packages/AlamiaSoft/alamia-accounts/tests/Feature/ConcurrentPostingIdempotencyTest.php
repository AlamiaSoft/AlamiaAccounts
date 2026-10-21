<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Exceptions\ConcurrentPostingInProgressException;
use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyLeaseFencingException;
use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ExpenseIntegrationService;
use AlamiaSoft\AlamiaAccounts\Services\PostingIdempotencyService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrentPostingIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseIntegrationService $expenseService;
    protected PostingIdempotencyService $idempotencyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();

        $this->expenseService = app(ExpenseIntegrationService::class);
        $this->idempotencyService = app(PostingIdempotencyService::class);
    }

    protected function getDomainUuid(string $code): string
    {
        $domain = app(CompanyService::class)->getDomain($code);
        if (!$domain) {
            throw new \RuntimeException("Domain {$code} not found");
        }
        return $domain->domainUuid;
    }

    /**
     * Test 1: Concurrent insert collision recovers and returns the single committed posting.
     */
    public function test_concurrent_insert_collision_recovers_and_returns_single_posting(): void
    {
        $payload = [
            'reference' => 'EXP-2026-CONC-001',
            'amount' => 1250.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Apex Office World',
            'description' => 'Concurrent posting test payload',
            'currency' => 'PKR',
        ];

        // Worker 1 processes and commits the voucher
        $result1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);
        $this->assertEquals('EXP-2026-CONC-001', $result1['voucher_reference']);

        $jeCountBefore = DB::table('journal_entries')->count();
        $djeCountBefore = DB::table('domain_journal_entries')->count();

        // Worker 2 attempts concurrent execution with the exact same payload
        $result2 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertTrue($result2['is_idempotent_replay']);
        $this->assertEquals($result1['journal_entry_id'], $result2['journal_entry_id']);
        $this->assertEquals($result1['voucher_reference'], $result2['voucher_reference']);

        // Invariant: Exactly one posting exists in the ledger
        $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count(), 'No duplicate journal entry created');
        $this->assertEquals($djeCountBefore, DB::table('domain_journal_entries')->count(), 'No duplicate domain entry created');
    }

    /**
     * Test 2: Concurrent requests with same identity but conflicting payloads cannot both succeed.
     */
    public function test_concurrent_same_key_different_payload_is_rejected(): void
    {
        $payloadA = [
            'reference' => 'EXP-2026-CONC-002',
            'amount' => 500.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Office Vendor A',
        ];

        $payloadB = [
            'reference' => 'EXP-2026-CONC-002',
            'amount' => 1500.00, // Altered amount under same reference
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Office Vendor B',
        ];

        // Worker 1 commits Payload A
        $result1 = $this->expenseService->processExpense($payloadA, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);

        $jeCountBefore = DB::table('journal_entries')->count();

        // Worker 2 attempts to commit conflicting Payload B under the same identity
        $this->expectException(IdempotencyPayloadMismatchException::class);
        try {
            $this->expenseService->processExpense($payloadB, 'MAIN');
        } finally {
            // Verify no second or partial posting was committed for Payload B
            $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count(), 'No second posting left behind');
        }
    }

    /**
     * Test 3: Active in-flight worker causes concurrent requests to receive stable retryable 409 response.
     */
    public function test_in_progress_concurrent_request_returns_retryable_conflict(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $idempotencyKey = 'travelos:MAIN:EXP-2026-CONC-003:post:v1';
        $payloadHash = hash('sha256', 'dummy-normalized-payload-3');

        // Pre-create an in-flight reservation with an active (unexpired) lease
        $activeRecord = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => $idempotencyKey,
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-CONC-003',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => $payloadHash,
            'request_payload' => ['reference' => 'EXP-2026-CONC-003'],
            'status' => 'in_flight',
            'lease_token' => (string) Str::uuid(),
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->addSeconds(60),
        ]);

        $canonicalIdentity = [
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-CONC-003',
            'operation' => 'post',
            'version' => 'v1',
            'idempotency_key' => $idempotencyKey,
        ];

        // A second worker arriving concurrently (with maxWaitMs = 0 to avoid polling delay)
        $reservation = $this->idempotencyService->acquireReservation(
            $canonicalIdentity,
            $payloadHash,
            ['reference' => 'EXP-2026-CONC-003'],
            0
        );

        $this->assertEquals('in_progress', $reservation['status']);
        $this->assertEquals($activeRecord->id, $reservation['record']->id);
    }

    /**
     * Test 4: Stale expired lease takeover allows recovery after worker failure/timeout.
     */
    public function test_stale_lease_takeover_allows_recovery_after_timeout(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $idempotencyKey = 'travelos:MAIN:EXP-2026-CONC-004:post:v1';

        $payload = [
            'reference' => 'EXP-2026-CONC-004',
            'amount' => 750.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Stationery Hub',
        ];

        $mappingResolver = app(\AlamiaSoft\AlamiaAccounts\Services\AccountMappingResolver::class);
        $debit = $mappingResolver->resolveExpenseAccount($payload['category'], $domainUuid);
        $credit = $mappingResolver->resolvePaymentSourceAccount($payload['payment_method'], $domainUuid);

        $payloadHash = $this->idempotencyService->computePayloadHash($payload, 'MAIN', [
            'resolved_debit_account' => $debit,
            'resolved_credit_account' => $credit,
        ]);

        $oldLeaseToken = (string) Str::uuid();

        // Simulate Worker 1 crashing: record is 'in_flight' but lease_expires_at is in the past
        $staleRecord = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => $idempotencyKey,
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-CONC-004',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => $payloadHash,
            'request_payload' => $payload,
            'status' => 'in_flight',
            'lease_token' => $oldLeaseToken,
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->subMinutes(5), // Expired!
        ]);

        $this->assertTrue($staleRecord->isLeaseExpired());

        // Worker 2 arrives and processes the expense
        $result = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result['is_idempotent_replay']);
        $this->assertEquals('EXP-2026-CONC-004', $result['voucher_reference']);

        // Verify the record was taken over and committed
        $staleRecord->refresh();
        $this->assertEquals('committed', $staleRecord->status);
        $this->assertEquals(2, $staleRecord->lease_generation, 'Lease generation must increment upon takeover');
        $this->assertNotEquals($oldLeaseToken, $staleRecord->lease_token, 'Lease token must be renewed upon takeover');
    }

    /**
     * Test 5: Preempted slow worker aborts transaction via optimistic lease fencing.
     */
    public function test_preempted_worker_aborts_transaction_via_lease_fencing(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $idempotencyKey = 'travelos:MAIN:EXP-2026-CONC-005:post:v1';
        $token1 = (string) Str::uuid();
        $token2 = (string) Str::uuid();

        // Worker 1 acquired lease with Token 1
        $record = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => $idempotencyKey,
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-CONC-005',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => 'hash-5',
            'request_payload' => [],
            'status' => 'in_flight',
            'lease_token' => $token1,
            'lease_generation' => 1,
            'lease_expires_at' => Carbon::now()->addSeconds(60),
        ]);

        // Worker 2 takes over the lease with Token 2 (simulating preemption)
        DB::table('posting_idempotency_records')
            ->where('id', $record->id)
            ->update([
                'lease_token' => $token2,
                'lease_generation' => 2,
            ]);

        // Now Worker 1 (holding Token 1) attempts markCommittedFenced()
        $this->expectException(IdempotencyLeaseFencingException::class);
        $record->markCommittedFenced('EXP-2026-CONC-005', 999, ['status' => 'posted']);
    }

    /**
     * Test 6: Unique constraint violation helper accurately classifies errors and does not swallow unrelated DB errors.
     */
    public function test_unique_constraint_violation_helper_does_not_swallow_unrelated_database_errors(): void
    {
        // 1. Simulate PostgreSQL unique constraint violation (SQLSTATE 23505)
        $pgUniqueException = new QueryException(
            'pgsql',
            'insert into posting_idempotency_records...',
            [],
            new \Exception('ERROR: duplicate key value violates unique constraint "domain_idempotency_unique"', 23505)
        );
        $this->assertTrue(PostingIdempotencyService::isUniqueConstraintViolation($pgUniqueException));

        // 2. Simulate SQLite unique constraint violation (code 19 / 2067)
        $sqliteUniqueException = new QueryException(
            'sqlite',
            'insert into posting_idempotency_records...',
            [],
            new \Exception('UNIQUE constraint failed: posting_idempotency_records.domain_uuid, posting_idempotency_records.idempotency_key', 19)
        );
        $this->assertTrue(PostingIdempotencyService::isUniqueConstraintViolation($sqliteUniqueException));

        // 3. Simulate unrelated database errors (must return FALSE to prevent swallowing)
        $syntaxException = new QueryException(
            'sqlite',
            'select * from non_existent_table',
            [],
            new \Exception('no such table: non_existent_table', 1)
        );
        $this->assertFalse(PostingIdempotencyService::isUniqueConstraintViolation($syntaxException));

        $connException = new QueryException(
            'pgsql',
            'select 1',
            [],
            new \Exception('connection refused', 7)
        );
        $this->assertFalse(PostingIdempotencyService::isUniqueConstraintViolation($connException));
    }

    /**
     * Test 7: Multi-tenant isolation under concurrent identical source keys across different companies.
     */
    public function test_multi_tenant_isolation_under_concurrency(): void
    {
        $payloadMain = [
            'reference' => 'EXP-2026-SHARED-KEY',
            'amount' => 500.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Main Vendor',
            'currency' => 'PKR',
        ];

        $payloadKamal = [
            'reference' => 'EXP-2026-SHARED-KEY',
            'amount' => 800.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Kamal Vendor',
            'currency' => 'PKR',
        ];

        // Company 1 (MAIN) posts
        $resultMain = $this->expenseService->processExpense($payloadMain, 'MAIN');
        $this->assertFalse($resultMain['is_idempotent_replay']);

        // Company 2 (KAMAL_EXPRESS) posts with identical reference/idempotency key
        $resultKamal = $this->expenseService->processExpense($payloadKamal, 'KAMAL_EXPRESS');
        $this->assertFalse($resultKamal['is_idempotent_replay']);

        // Both companies succeed independently
        $this->assertNotEquals($resultMain['journal_entry_id'], $resultKamal['journal_entry_id']);

        // Domain isolation check
        $domainMain = $this->getDomainUuid('MAIN');
        $domainKamal = $this->getDomainUuid('KAMAL_EXPRESS');

        $mainEntries = DB::table('domain_journal_entries')->where('domainUuid', $domainMain)->where('reference', 'EXP-2026-SHARED-KEY')->count();
        $kamalEntries = DB::table('domain_journal_entries')->where('domainUuid', $domainKamal)->where('reference', 'EXP-2026-SHARED-KEY')->count();

        $this->assertEquals(1, $mainEntries, 'MAIN must have exactly one entry');
        $this->assertEquals(1, $kamalEntries, 'KAMAL_EXPRESS must have exactly one entry');
    }

    /**
     * Test 8: Deterministic simulation of concurrent identical requests.
     */
    public function test_deterministic_concurrent_worker_simulation(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SIMUL-001',
            'amount' => 2000.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Universal Stationery',
            'currency' => 'PKR',
        ];

        $results = [];

        // Run 5 sequential simulated retries/concurrent attempts
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->expenseService->processExpense($payload, 'MAIN');
        }

        // Exactly one result was the initial creation
        $createdCount = count(array_filter($results, fn($r) => $r['is_idempotent_replay'] === false));
        $replayedCount = count(array_filter($results, fn($r) => $r['is_idempotent_replay'] === true));

        $this->assertEquals(1, $createdCount, 'Exactly one execution should create the initial posting');
        $this->assertEquals(4, $replayedCount, 'All subsequent attempts must return idempotent replay');

        // All results must share the exact same journal entry ID and voucher reference
        $expectedJeId = $results[0]['journal_entry_id'];
        $expectedRef = $results[0]['voucher_reference'];

        foreach ($results as $res) {
            $this->assertEquals($expectedJeId, $res['journal_entry_id']);
            $this->assertEquals($expectedRef, $res['voucher_reference']);
        }

        // Database invariant
        $domainUuid = $this->getDomainUuid('MAIN');
        $entriesInDb = DB::table('domain_journal_entries')
            ->where('domainUuid', $domainUuid)
            ->where('reference', 'EXP-2026-SIMUL-001')
            ->count();

        $this->assertEquals(1, $entriesInDb, 'Strictly 1 domain journal entry exists in database');
    }
}
