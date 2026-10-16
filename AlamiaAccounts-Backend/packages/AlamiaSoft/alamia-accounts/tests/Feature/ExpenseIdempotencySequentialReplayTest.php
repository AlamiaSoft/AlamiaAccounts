<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyPayloadMismatchException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ExpenseIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseIdempotencySequentialReplayTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseIntegrationService $expenseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();

        $this->expenseService = app(ExpenseIntegrationService::class);
    }

    protected function getDomainUuid(string $code): string
    {
        $domain = LedgerDomain::where('code', $code)->first();
        if (!$domain) {
            throw new \RuntimeException("Domain {$code} not found");
        }
        return $domain->domainUuid;
    }

    /**
     * Test 1: Identical sequential replay returns original posting and creates no second journal entry.
     */
    public function test_identical_sequential_replay_returns_original_posting_and_creates_no_second_entry(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SEQ-001',
            'amount' => 500.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Alpha Stationery',
            'description' => 'Office Paper & Supplies',
            'currency' => 'PKR',
        ];

        // 1. Initial post
        $result1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);
        $this->assertEquals('EXP-2026-SEQ-001', $result1['voucher_reference']);
        $this->assertNotNull($result1['journal_entry_id']);

        $jeCountBefore = DB::table('journal_entries')->count();
        $djeCountBefore = DB::table('domain_journal_entries')->count();
        $idempCountBefore = DB::table('posting_idempotency_records')->count();

        // 2. Sequential replay of exact same payload
        $result2 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertTrue($result2['is_idempotent_replay']);
        $this->assertEquals($result1['voucher_reference'], $result2['voucher_reference']);
        $this->assertEquals($result1['journal_entry_id'], $result2['journal_entry_id']);
        $this->assertEquals($result1['amount'], $result2['amount']);

        // 3. Invariant: Ledger counts and idempotency record counts must NOT increase
        $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count(), 'No new journal entry should be created on replay');
        $this->assertEquals($djeCountBefore, DB::table('domain_journal_entries')->count(), 'No new domain association should be created on replay');
        $this->assertEquals($idempCountBefore, DB::table('posting_idempotency_records')->count(), 'No duplicate idempotency record should be created');
    }

    /**
     * Test 2: Reusing identity with mutated amount is rejected and leaves no second posting.
     */
    public function test_reusing_identity_with_different_amount_is_rejected_and_leaves_no_second_posting(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SEQ-002',
            'amount' => 150.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Alpha Stationery',
        ];

        // 1. Initial post
        $result1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);

        $jeCountBefore = DB::table('journal_entries')->count();
        $djeCountBefore = DB::table('domain_journal_entries')->count();

        // 2. Replay with altered amount (150 -> 250)
        $mutatedPayload = $payload;
        $mutatedPayload['amount'] = 250.00;

        try {
            $this->expenseService->processExpense($mutatedPayload, 'MAIN');
            $this->fail('Expected IdempotencyPayloadMismatchException was not thrown');
        } catch (IdempotencyPayloadMismatchException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertNotEmpty($e->getOriginalHash());
            $this->assertNotEmpty($e->getAttemptedHash());
            $this->assertNotEquals($e->getOriginalHash(), $e->getAttemptedHash());
        }

        // 3. Invariant: Ledger entries must remain strictly unchanged
        $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count());
        $this->assertEquals($djeCountBefore, DB::table('domain_journal_entries')->count());

        // 4. Audit trail must record the mismatch violation
        $audit = AccountingAuditTrail::where('action', 'IDEMPOTENCY_PAYLOAD_MISMATCH')
            ->where('entity_reference', 'EXP-2026-SEQ-002')
            ->first();
        $this->assertNotNull($audit, 'Mismatch must be recorded in accounting audit trail');
    }

    /**
     * Test 3: Reusing identity with different category/account mapping is rejected.
     */
    public function test_reusing_identity_with_different_category_or_account_mapping_is_rejected(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SEQ-003',
            'amount' => 300.00,
            'category' => 'fuel',
            'payment_method' => 'cash',
        ];

        $result1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);

        $jeCountBefore = DB::table('journal_entries')->count();

        // Replay with mutated category
        $mutatedPayload = $payload;
        $mutatedPayload['category'] = 'meals';

        $this->expectException(IdempotencyPayloadMismatchException::class);
        try {
            $this->expenseService->processExpense($mutatedPayload, 'MAIN');
        } finally {
            $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count());
        }
    }

    /**
     * Test 4: Reusing identity with different payment method is rejected.
     */
    public function test_reusing_identity_with_different_payment_method_is_rejected(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SEQ-004',
            'amount' => 200.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
        ];

        $result1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);

        $jeCountBefore = DB::table('journal_entries')->count();

        // Mutate payment method (credit account mapping change)
        $mutatedPayload = $payload;
        $mutatedPayload['payment_method'] = 'bank_transfer';

        $this->expectException(IdempotencyPayloadMismatchException::class);
        try {
            $this->expenseService->processExpense($mutatedPayload, 'MAIN');
        } finally {
            $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count());
        }
    }

    /**
     * Test 5: Divergent client idempotency key with same canonical source transaction rejects mutation.
     */
    public function test_divergent_client_key_with_same_source_transaction_rejects_mutation(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SEQ-005',
            'idempotency_key' => 'CLIENT-KEY-ALPHA',
            'amount' => 400.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
        ];

        $result1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($result1['is_idempotent_replay']);

        $jeCountBefore = DB::table('journal_entries')->count();

        // Caller attempts to supply divergent client key 'CLIENT-KEY-BETA' for SAME source reference with mutated amount
        $divergentPayload = $payload;
        $divergentPayload['idempotency_key'] = 'CLIENT-KEY-BETA';
        $divergentPayload['amount'] = 600.00;

        $this->expectException(IdempotencyPayloadMismatchException::class);
        try {
            $this->expenseService->processExpense($divergentPayload, 'MAIN');
        } finally {
            $this->assertEquals($jeCountBefore, DB::table('journal_entries')->count());
        }
    }

    /**
     * Test 6: Multi-tenant isolation allows same reference and idempotency key across different companies without collision.
     */
    public function test_multi_tenant_isolation_allows_same_identity_in_different_companies(): void
    {
        $payload = [
            'reference' => 'EXP-CROSS-TENANT-001',
            'idempotency_key' => 'SHARED-CLIENT-KEY-999',
            'amount' => 350.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
        ];

        // 1. Post in Company MAIN
        $resMain = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($resMain['is_idempotent_replay']);
        $this->assertNotNull($resMain['journal_entry_id']);

        // 2. Post identical reference & key in Company KAMAL_EXPRESS
        $resKamal = $this->expenseService->processExpense($payload, 'KAMAL_EXPRESS');
        $this->assertFalse($resKamal['is_idempotent_replay']);
        $this->assertNotNull($resKamal['journal_entry_id']);

        // 3. Invariants: Must be two distinct ledger entries in separate domains
        $this->assertNotEquals(
            $resMain['journal_entry_id'],
            $resKamal['journal_entry_id'],
            'Different companies must produce distinct journal entries'
        );

        $mainDomain = $this->getDomainUuid('MAIN');
        $kamalDomain = $this->getDomainUuid('KAMAL_EXPRESS');

        $mainEntry = DomainJournalEntry::where('journalEntryId', $resMain['journal_entry_id'])->first();
        $kamalEntry = DomainJournalEntry::where('journalEntryId', $resKamal['journal_entry_id'])->first();

        $this->assertEquals($mainDomain, $mainEntry->domainUuid);
        $this->assertEquals($kamalDomain, $kamalEntry->domainUuid);
    }

    /**
     * Test 7: Transient fields (timestamps, request IDs, user names) do not cause false payload mismatch.
     */
    public function test_transient_fields_do_not_cause_false_payload_mismatch(): void
    {
        $payload1 = [
            'reference' => 'EXP-2026-SEQ-007',
            'amount' => 125.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'timestamp' => '2026-10-16T10:00:00Z',
            'request_id' => 'REQ-UUID-1',
            'user_name' => 'Agent Worker 1',
        ];

        $res1 = $this->expenseService->processExpense($payload1, 'MAIN');
        $this->assertFalse($res1['is_idempotent_replay']);

        // Replay with mutated transient fields
        $payload2 = $payload1;
        $payload2['timestamp'] = '2026-10-16T10:00:45Z';
        $payload2['request_id'] = 'REQ-UUID-2';
        $payload2['user_name'] = 'Agent Worker 2';

        $res2 = $this->expenseService->processExpense($payload2, 'MAIN');
        $this->assertTrue($res2['is_idempotent_replay'], 'Transient fields must not invalidate replay equality');
        $this->assertEquals($res1['journal_entry_id'], $res2['journal_entry_id']);
    }

    /**
     * Test 8: Deduplication does not depend on SQL LIKE scans on journal_entries.extra.
     */
    public function test_deduplication_does_not_depend_on_extra_substring_search(): void
    {
        $payload = [
            'reference' => 'EXP-2026-SEQ-008',
            'amount' => 750.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
        ];

        $res1 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertFalse($res1['is_idempotent_replay']);

        // Deliberately wipe extra on the created journal entry to prove no SQL LIKE dependency
        DB::table('journal_entries')
            ->where('journalEntryId', $res1['journal_entry_id'])
            ->update(['extra' => null]);

        // Replay must still succeed via durable posting_idempotency_records
        $res2 = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertTrue($res2['is_idempotent_replay']);
        $this->assertEquals($res1['journal_entry_id'], $res2['journal_entry_id']);
    }
}
