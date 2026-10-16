<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\JournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostingIdempotencyPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();
    }

    protected function getDomainUuid(string $code): string
    {
        $domain = LedgerDomain::where('code', $code)->first();
        if (!$domain) {
            throw new \RuntimeException("Domain {$code} not found");
        }
        return $domain->domainUuid;
    }

    protected function createDummyJournalEntry(string $domainUuid, string $description = 'Test Entry'): int
    {
        return (int) DB::table('journal_entries')->insertGetId([
            'domainUuid' => $domainUuid,
            'transDate' => '2026-10-16 10:00:00',
            'currency' => 'PKR',
            'opening' => 0,
            'reviewed' => 0,
            'description' => $description,
            'arguments' => '{}',
            'language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test deterministic persistence and retrieval of PostingIdempotencyRecord.
     */
    public function test_can_persist_and_retrieve_idempotency_record_with_payload_hash_and_status(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $expiresAt = Carbon::now()->addSeconds(60);

        $record = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => 'TEST-KEY-001',
            'source_module' => 'integration',
            'source_transaction_id' => 'TX-9901',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'canonical-payload-data'),
            'request_payload' => ['amount' => '1500.0000', 'currency' => 'PKR'],
            'status' => 'in_flight',
            'lease_token' => 'token-uuid-1234',
            'lease_generation' => 1,
            'lease_expires_at' => $expiresAt,
            'voucher_reference' => 'JV-2026-0001',
            'journal_entry_id' => null,
            'response_data' => null,
            'error_message' => null,
        ]);

        $this->assertNotNull($record->id);
        $this->assertTrue($record->isInFlight());
        $this->assertFalse($record->isCommitted());
        $this->assertFalse($record->isFailed());
        $this->assertFalse($record->isLeaseExpired());

        // Retrieve from database
        $fetched = PostingIdempotencyRecord::where('domain_uuid', $domainUuid)
            ->where('idempotency_key', 'TEST-KEY-001')
            ->first();

        $this->assertNotNull($fetched);
        $this->assertSame('MAIN', $fetched->company_code);
        $this->assertSame(hash('sha256', 'canonical-payload-data'), $fetched->payload_hash);
        $this->assertSame('1500.0000', $fetched->request_payload['amount']);
        $this->assertSame(1, $fetched->lease_generation);
        $this->assertSame('token-uuid-1234', $fetched->lease_token);

        // Update status to committed with cached response
        $fetched->update([
            'status' => 'committed',
            'response_data' => ['success' => true, 'reference' => 'JV-2026-0001'],
        ]);

        $reloaded = $fetched->fresh();
        $this->assertTrue($reloaded->isCommitted());
        $this->assertSame('JV-2026-0001', $reloaded->response_data['reference']);
    }

    /**
     * Test that duplicate (domain_uuid, idempotency_key) triggers a QueryException,
     * while the same idempotency_key in another tenant succeeds.
     */
    public function test_enforces_tenant_scoped_idempotency_key_uniqueness(): void
    {
        $domainMain = $this->getDomainUuid('MAIN');

        // 1. First insert in MAIN domain
        PostingIdempotencyRecord::create([
            'domain_uuid' => $domainMain,
            'company_code' => 'MAIN',
            'idempotency_key' => 'SHARED-CLIENT-KEY',
            'source_module' => 'pos',
            'source_transaction_id' => 'TX-01',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'p1'),
            'status' => 'in_flight',
        ]);

        // 2. Duplicate insert in SAME domain must throw QueryException
        $this->expectException(QueryException::class);
        PostingIdempotencyRecord::create([
            'domain_uuid' => $domainMain,
            'company_code' => 'MAIN',
            'idempotency_key' => 'SHARED-CLIENT-KEY',
            'source_module' => 'pos',
            'source_transaction_id' => 'TX-02',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'p2'),
            'status' => 'in_flight',
        ]);
    }

    /**
     * Test cross-company isolation: identical idempotency_key in different tenants succeeds without collision.
     */
    public function test_allows_same_idempotency_key_in_different_tenants(): void
    {
        $domainMain = $this->getDomainUuid('MAIN');
        $domainKamal = $this->getDomainUuid('KAMAL_EXPRESS');

        $rec1 = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainMain,
            'company_code' => 'MAIN',
            'idempotency_key' => 'TENANT-ISOLATED-KEY',
            'source_module' => 'pos',
            'source_transaction_id' => 'TX-01',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'p1'),
            'status' => 'in_flight',
        ]);

        $rec2 = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainKamal,
            'company_code' => 'KAMAL_EXPRESS',
            'idempotency_key' => 'TENANT-ISOLATED-KEY',
            'source_module' => 'pos',
            'source_transaction_id' => 'TX-01',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'p2'),
            'status' => 'in_flight',
        ]);

        $this->assertNotSame($rec1->domain_uuid, $rec2->domain_uuid);
        $this->assertSame($rec1->idempotency_key, $rec2->idempotency_key);
        $this->assertDatabaseCount('posting_idempotency_records', 2);
    }

    /**
     * Test that canonical 5-tuple uniqueness rejects duplicate postings under divergent client keys.
     */
    public function test_enforces_canonical_identity_tuple_uniqueness(): void
    {
        $domainMain = $this->getDomainUuid('MAIN');

        // Record 1 posted with client key 'KEY-ALPHA'
        PostingIdempotencyRecord::create([
            'domain_uuid' => $domainMain,
            'company_code' => 'MAIN',
            'idempotency_key' => 'KEY-ALPHA',
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-9001',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'p1'),
            'status' => 'in_flight',
        ]);

        // Attempting to post the SAME business transaction with divergent client key 'KEY-BETA'
        // must be rejected by domain_canonical_identity_unique constraint
        $this->expectException(QueryException::class);
        PostingIdempotencyRecord::create([
            'domain_uuid' => $domainMain,
            'company_code' => 'MAIN',
            'idempotency_key' => 'KEY-BETA', // Divergent client key
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-9001', // Identical source transaction
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => hash('sha256', 'p2'),
            'status' => 'in_flight',
        ]);
    }

    /**
     * Test that domain_journal_entries enforces composite uniqueness on (domainUuid, reference).
     */
    public function test_enforces_domain_journal_entries_reference_uniqueness(): void
    {
        $domainMain = $this->getDomainUuid('MAIN');

        $je1Id = $this->createDummyJournalEntry($domainMain, 'Test Entry 1');
        $je2Id = $this->createDummyJournalEntry($domainMain, 'Test Entry 2');

        // 1. Insert entry 1 with reference 'REF-UNIQUE-001'
        DomainJournalEntry::create([
            'domainUuid' => $domainMain,
            'journalEntryId' => $je1Id,
            'reference' => 'REF-UNIQUE-001',
        ]);

        // 2. Duplicate reference in same domain must throw QueryException
        $this->expectException(QueryException::class);
        DomainJournalEntry::create([
            'domainUuid' => $domainMain,
            'journalEntryId' => $je2Id,
            'reference' => 'REF-UNIQUE-001',
        ]);
    }

    /**
     * Test that duplicate reference is allowed across different domains.
     */
    public function test_allows_same_reference_across_different_domains(): void
    {
        $domainMain = $this->getDomainUuid('MAIN');
        $domainKamal = $this->getDomainUuid('KAMAL_EXPRESS');

        $je1Id = $this->createDummyJournalEntry($domainMain, 'Test Entry 1');
        $je2Id = $this->createDummyJournalEntry($domainKamal, 'Test Entry 2');

        $d1 = DomainJournalEntry::create([
            'domainUuid' => $domainMain,
            'journalEntryId' => $je1Id,
            'reference' => 'REF-CROSS-DOMAIN',
        ]);

        $d2 = DomainJournalEntry::create([
            'domainUuid' => $domainKamal,
            'journalEntryId' => $je2Id,
            'reference' => 'REF-CROSS-DOMAIN',
        ]);

        $this->assertNotSame($d1->domainUuid, $d2->domainUuid);
        $this->assertSame($d1->reference, $d2->reference);
    }

    /**
     * Test that operational_sales enforces uniqueness on (company_code, idempotency_key).
     */
    public function test_enforces_operational_sales_company_idempotency_uniqueness(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('operational_sales')) {
            $this->markTestSkipped('operational_sales table not present in current test schema.');
        }

        DB::table('operational_sales')->insert([
            'company_code' => 'MAIN',
            'idempotency_key' => 'SALE-IDEMPOTENCY-001',
            'customer_name' => 'Walk-in Customer',
            'issue_date' => '2026-10-16',
            'gross_amount' => 100.0,
            'tax_amount' => 0.0,
            'net_amount' => 100.0,
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate insert in SAME company must throw QueryException
        $this->expectException(QueryException::class);
        DB::table('operational_sales')->insert([
            'company_code' => 'MAIN',
            'idempotency_key' => 'SALE-IDEMPOTENCY-001',
            'customer_name' => 'Another Customer',
            'issue_date' => '2026-10-16',
            'gross_amount' => 200.0,
            'tax_amount' => 0.0,
            'net_amount' => 200.0,
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test deterministic backfill behavior for legacy entries without reference.
     */
    public function test_legacy_null_references_are_backfilled_deterministically(): void
    {
        $domainMain = $this->getDomainUuid('MAIN');

        // Create a journal entry with extra containing reference
        $je1Id = DB::table('journal_entries')->insertGetId([
            'domainUuid' => $domainMain,
            'transDate' => '2026-10-16 10:00:00',
            'currency' => 'PKR',
            'opening' => 0,
            'reviewed' => 0,
            'description' => 'Legacy with Extra',
            'arguments' => '{}',
            'language' => 'en',
            'extra' => json_encode(['reference' => 'LEGACY-REF-999']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a journal entry without reference in extra
        $je2Id = DB::table('journal_entries')->insertGetId([
            'domainUuid' => $domainMain,
            'transDate' => '2026-10-16 10:00:00',
            'currency' => 'PKR',
            'opening' => 0,
            'reviewed' => 0,
            'description' => 'Legacy without Extra',
            'arguments' => '{}',
            'language' => 'en',
            'extra' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Temporarily insert domain entries without reference simulating legacy data prior to unique constraint
        $d1Id = DB::table('domain_journal_entries')->insertGetId([
            'domainUuid' => $domainMain,
            'journalEntryId' => $je1Id,
            'reference' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $d2Id = DB::table('domain_journal_entries')->insertGetId([
            'domainUuid' => $domainMain,
            'journalEntryId' => $je2Id,
            'reference' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run backfill logic
        $entries = DB::table('domain_journal_entries')
            ->leftJoin('journal_entries', 'domain_journal_entries.journalEntryId', '=', 'journal_entries.journalEntryId')
            ->where(function ($q) {
                $q->whereNull('domain_journal_entries.reference')
                  ->orWhere('domain_journal_entries.reference', '');
            })
            ->select('domain_journal_entries.id', 'domain_journal_entries.journalEntryId', 'journal_entries.extra')
            ->get();

        foreach ($entries as $e) {
            $ref = null;
            if (!empty($e->extra)) {
                $extra = is_string($e->extra) ? json_decode($e->extra, true) : (array)$e->extra;
                $ref = $extra['reference'] ?? $extra['voucher_number'] ?? null;
            }
            if (empty($ref)) {
                $ref = 'LEGACY-' . $e->journalEntryId;
            }
            DB::table('domain_journal_entries')
                ->where('id', $e->id)
                ->update(['reference' => trim((string)$ref)]);
        }

        // Verify that d1 got reference from extra
        $updatedD1 = DB::table('domain_journal_entries')->where('id', $d1Id)->first();
        $this->assertSame('LEGACY-REF-999', $updatedD1->reference);

        // Verify that d2 got fallback deterministic reference
        $updatedD2 = DB::table('domain_journal_entries')->where('id', $d2Id)->first();
        $this->assertSame('LEGACY-' . $je2Id, $updatedD2->reference);
    }
}

