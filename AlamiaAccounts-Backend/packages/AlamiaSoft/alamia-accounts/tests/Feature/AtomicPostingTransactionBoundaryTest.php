<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyLeaseFencingException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord;
use AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ExpenseIntegrationService;
use AlamiaSoft\AlamiaAccounts\Services\PostingIdempotencyService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AtomicPostingTransactionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseIntegrationService $expenseService;
    protected VoucherService $voucherService;
    protected PostingIdempotencyService $idempotencyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();

        $this->expenseService = app(ExpenseIntegrationService::class);
        $this->voucherService = app(VoucherService::class);
        $this->idempotencyService = app(PostingIdempotencyService::class);
    }

    protected function tearDown(): void
    {
        DomainJournalEntry::flushEventListeners();
        AccountingAuditTrail::flushEventListeners();
        PostingIdempotencyRecord::flushEventListeners();
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
     * Test 1: Happy path commits journal entry, domain association, idempotency committed state,
     * and both voucher/integration audit records atomically, returning only at transaction depth 0.
     */
    public function test_successful_posting_commits_journal_domain_idempotency_and_audit_atomically(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $baselineLevel = DB::transactionLevel();

        $payload = [
            'reference' => 'EXP-2026-ATOM-001',
            'amount' => 1800.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Stationery Hub',
            'description' => 'Atomic happy path verification',
            'currency' => 'PKR',
        ];

        $result = $this->expenseService->processExpense($payload, 'MAIN');

        // Returned only after outer posting transaction completed back to baseline level
        $this->assertSame($baselineLevel, DB::transactionLevel());
        $this->assertSame('posted', $result['status']);
        $this->assertFalse($result['is_idempotent_replay']);
        $this->assertNotEmpty($result['journal_entry_id']);

        $journalEntryId = (int) $result['journal_entry_id'];

        // Verify Abivia journal entry & details exist
        $this->assertTrue(
            DB::table('journal_entries')->where('journalEntryId', $journalEntryId)->exists(),
            'Journal entry row must be durably persisted'
        );
        $this->assertSame(
            2,
            DB::table('journal_details')->where('journalEntryId', $journalEntryId)->count(),
            'Balanced debit/credit journal details must be durably persisted'
        );

        // Verify domain association
        $this->assertTrue(
            DomainJournalEntry::where('domainUuid', $domainUuid)
                ->where('journalEntryId', $journalEntryId)
                ->where('reference', 'EXP-2026-ATOM-001')
                ->exists(),
            'DomainJournalEntry association must be durably persisted'
        );

        // Verify idempotency state is committed and linked to the exact journalEntryId
        $record = PostingIdempotencyRecord::forDomain($domainUuid)
            ->where('voucher_reference', 'EXP-2026-ATOM-001')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('committed', $record->status);
        $this->assertSame($journalEntryId, $record->journal_entry_id);

        // Verify both CREATE_VOUCHER and INTEGRATION_EXPENSE_POSTED audits exist
        $this->assertTrue(
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('action', 'CREATE_VOUCHER')
                ->where('entity_reference', 'EXP-2026-ATOM-001')
                ->exists()
        );
        $this->assertTrue(
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('action', 'INTEGRATION_EXPENSE_POSTED')
                ->where('entity_reference', 'EXP-2026-ATOM-001')
                ->exists()
        );
    }

    /**
     * Test 2: Failure during DomainJournalEntry creation rolls back the Abivia JournalEntry,
     * JournalDetail, and LedgerBalance writes (via nested savepoint), leaving zero unassociated
     * journal entries and marking the idempotency record as failed.
     */
    public function test_domain_association_failure_rolls_back_abivia_journal_entry_and_leaves_no_orphan(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeCountBefore = DB::table('journal_entries')->count();
        $jdCountBefore = DB::table('journal_details')->count();
        $djeCountBefore = DomainJournalEntry::count();

        DomainJournalEntry::creating(function () {
            throw new RuntimeException('Simulated database failure while associating DomainJournalEntry');
        });

        $payload = [
            'reference' => 'EXP-2026-ATOM-002',
            'amount' => 2400.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Apex Supplies',
            'description' => 'Domain association rollback test',
            'currency' => 'PKR',
        ];

        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected RuntimeException from DomainJournalEntry::creating listener');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated database failure while associating DomainJournalEntry', $e->getMessage());
        }

        // Invariant: Abivia journal_entries and journal_details must be rolled back (zero unassociated entries)
        $this->assertSame($jeCountBefore, DB::table('journal_entries')->count(), 'Abivia journal_entries must roll back when DomainJournalEntry fails');
        $this->assertSame($jdCountBefore, DB::table('journal_details')->count(), 'Abivia journal_details must roll back when DomainJournalEntry fails');
        $this->assertSame($djeCountBefore, DomainJournalEntry::count(), 'No DomainJournalEntry row should exist');

        // Invariant: Idempotency record must NOT be committed; it must transition to failed
        $record = PostingIdempotencyRecord::forDomain($domainUuid)
            ->where('source_transaction_id', 'EXP-2026-ATOM-002')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('failed', $record->status);
        $this->assertNull($record->journal_entry_id);

        // Invariant: No success audits exist, while failure audit and unposted queue record exist
        $this->assertFalse(
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->whereIn('action', ['CREATE_VOUCHER', 'INTEGRATION_EXPENSE_POSTED'])
                ->where('entity_reference', 'EXP-2026-ATOM-002')
                ->exists()
        );
        $this->assertTrue(
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('action', 'INTEGRATION_POSTING_FAILED')
                ->where('entity_reference', 'EXP-2026-ATOM-002')
                ->exists()
        );
        $this->assertTrue(
            UnpostedIntegrationEntry::where('domain_uuid', $domainUuid)
                ->where('source_reference', 'EXP-2026-ATOM-002')
                ->where('status', 'failed')
                ->exists()
        );
    }

    /**
     * Test 3: If lease fencing fails in markCommittedFenced() (e.g., concurrent worker preempted an
     * expired lease while VoucherService::createJournalEntry() was executing), the outer DB::transaction
     * rolls back the stale worker's JournalEntry and DomainJournalEntry, and the stale worker's catch
     * handler does not overwrite the new lease holder's idempotency record.
     */
    public function test_lease_fencing_failure_rolls_back_voucher_and_preserves_winner_idempotency_state(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeCountBefore = DB::table('journal_entries')->count();
        $djeCountBefore = DomainJournalEntry::count();
        $staleLeaseToken = (string) Str::uuid();
        $winnerLeaseToken = (string) Str::uuid();

        $payload = [
            'reference' => 'EXP-2026-ATOM-003',
            'amount' => 3100.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Lease Preemption Vendor',
            'description' => 'Lease fencing atomic rollback test',
            'currency' => 'PKR',
        ];

        // Persist the winner's committed state outside the stale worker's transaction
        $dbRecord = PostingIdempotencyRecord::create([
            'domain_uuid' => $domainUuid,
            'company_code' => 'MAIN',
            'idempotency_key' => 'travelos:MAIN:EXP-2026-ATOM-003:post:v1',
            'source_module' => 'travelos',
            'source_transaction_id' => 'EXP-2026-ATOM-003',
            'operation' => 'post',
            'version' => 'v1',
            'payload_hash' => str_repeat('a', 64),
            'request_payload' => $payload,
            'status' => 'committed',
            'lease_token' => $winnerLeaseToken,
            'lease_generation' => 2,
            'voucher_reference' => 'EXP-2026-ATOM-003',
            'journal_entry_id' => 999999,
        ]);

        // Stale worker holds an in-memory snapshot from generation 1 with $staleLeaseToken
        $staleWorkerRecord = clone $dbRecord;
        $staleWorkerRecord->status = 'in_flight';
        $staleWorkerRecord->lease_token = $staleLeaseToken;
        $staleWorkerRecord->lease_generation = 1;

        $mockIdempotency = $this->getMockBuilder(PostingIdempotencyService::class)
            ->onlyMethods(['acquireReservation'])
            ->getMock();
        $mockIdempotency->expects($this->once())
            ->method('acquireReservation')
            ->willReturn([
                'status' => 'acquired',
                'record' => $staleWorkerRecord,
            ]);

        $serviceWithStaleLease = new ExpenseIntegrationService(
            $this->voucherService,
            app(\AlamiaSoft\AlamiaAccounts\Services\AccountMappingResolver::class),
            app(CompanyService::class),
            $mockIdempotency
        );

        try {
            $serviceWithStaleLease->processExpense($payload, 'MAIN');
            $this->fail('Expected IdempotencyLeaseFencingException when lease_token was preempted');
        } catch (IdempotencyLeaseFencingException $e) {
            $this->assertStringContainsString('Lease lost or preempted', $e->getMessage());
        }

        // Invariant 1: The preempted worker's JournalEntry and DomainJournalEntry were completely rolled back!
        $this->assertSame($jeCountBefore, DB::table('journal_entries')->count(), 'Preempted worker JournalEntry must be rolled back');
        $this->assertSame($djeCountBefore, DomainJournalEntry::count(), 'Preempted worker DomainJournalEntry must be rolled back');

        // Invariant 2: The winner's idempotency record state ('committed', token=$winnerLeaseToken) was NOT overwritten to 'failed'
        $record = PostingIdempotencyRecord::forDomain($domainUuid)
            ->where('source_transaction_id', 'EXP-2026-ATOM-003')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('committed', $record->status);
        $this->assertSame($winnerLeaseToken, $record->lease_token);
        $this->assertSame(999999, $record->journal_entry_id);

        // Invariant 3: Audit trail records IDEMPOTENCY_LEASE_FENCED
        $this->assertTrue(
            AccountingAuditTrail::where('domain_uuid', $domainUuid)
                ->where('action', 'IDEMPOTENCY_LEASE_FENCED')
                ->where('entity_reference', 'EXP-2026-ATOM-003')
                ->exists()
        );
    }

    /**
     * Test 4: Failure when recording INTEGRATION_EXPENSE_POSTED audit trail (after createJournalEntry
     * and markCommittedFenced have already run inside the transaction) rolls back the entire transaction:
     * journal entry, domain association, and the 'committed' idempotency status are all rolled back,
     * and the idempotency record transitions cleanly to 'failed'.
     */
    public function test_integration_audit_failure_rolls_back_journal_entry_domain_association_and_idempotency_commit(): void
    {
        $domainUuid = $this->getDomainUuid('MAIN');
        $jeCountBefore = DB::table('journal_entries')->count();
        $djeCountBefore = DomainJournalEntry::count();

        AccountingAuditTrail::creating(function (AccountingAuditTrail $audit) {
            if ($audit->action === 'INTEGRATION_EXPENSE_POSTED') {
                throw new RuntimeException('Simulated failure in INTEGRATION_EXPENSE_POSTED audit write');
            }
        });

        $payload = [
            'reference' => 'EXP-2026-ATOM-004',
            'amount' => 4200.00,
            'category' => 'office_supplies',
            'payment_method' => 'cash',
            'vendor_name' => 'Audit Rollback Vendor',
            'description' => 'Integration audit rollback test',
            'currency' => 'PKR',
        ];

        try {
            $this->expenseService->processExpense($payload, 'MAIN');
            $this->fail('Expected RuntimeException from INTEGRATION_EXPENSE_POSTED audit listener');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated failure in INTEGRATION_EXPENSE_POSTED audit write', $e->getMessage());
        }

        // Invariant 1: JournalEntry and DomainJournalEntry were rolled back
        $this->assertSame($jeCountBefore, DB::table('journal_entries')->count(), 'JournalEntry must roll back when INTEGRATION_EXPENSE_POSTED audit fails');
        $this->assertSame($djeCountBefore, DomainJournalEntry::count(), 'DomainJournalEntry must roll back when INTEGRATION_EXPENSE_POSTED audit fails');

        // Invariant 2: Idempotency record must NOT remain 'committed'; it rolled back to 'in_flight' and then transitioned to 'failed'
        $record = PostingIdempotencyRecord::forDomain($domainUuid)
            ->where('source_transaction_id', 'EXP-2026-ATOM-004')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('failed', $record->status, 'Idempotency record must not be left in a false committed state');
        $this->assertNull($record->journal_entry_id);

        // Invariant 3: Because the first attempt rolled back cleanly and marked the reservation 'failed',
        // removing the fault and retrying the exact same payload succeeds cleanly!
        AccountingAuditTrail::flushEventListeners();
        $retryResult = $this->expenseService->processExpense($payload, 'MAIN');
        $this->assertSame('posted', $retryResult['status']);
        $this->assertFalse($retryResult['is_idempotent_replay']);
        $this->assertSame($jeCountBefore + 1, DB::table('journal_entries')->count());
        $this->assertSame($djeCountBefore + 1, DomainJournalEntry::count());

        $record->refresh();
        $this->assertSame('committed', $record->status);
        $this->assertSame((int) $retryResult['journal_entry_id'], $record->journal_entry_id);
    }

    /**
     * Test 5: Direct VoucherService::createJournalEntry() rolls back Abivia JournalEntry and
     * DomainJournalEntry if CREATE_VOUCHER audit recording fails.
     */
    public function test_create_voucher_audit_failure_rolls_back_journal_entry_and_domain_association(): void
    {
        DomainContext::set('MAIN');
        $domainUuid = $this->getDomainUuid('MAIN');
        $resolver = app(\AlamiaSoft\AlamiaAccounts\Services\AccountMappingResolver::class);
        $debitCode = $resolver->resolveExpenseAccount('office_supplies', $domainUuid);
        $creditCode = $resolver->resolvePaymentSourceAccount('cash', $domainUuid);

        $jeCountBefore = DB::table('journal_entries')->count();
        $djeCountBefore = DomainJournalEntry::count();

        AccountingAuditTrail::creating(function (AccountingAuditTrail $audit) {
            if ($audit->action === 'CREATE_VOUCHER') {
                throw new RuntimeException('Simulated CREATE_VOUCHER audit write failure');
            }
        });

        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'JV-ATOM-005',
                'description' => 'Direct voucher atomic audit test',
                'date' => '2026-10-22',
                'currency' => 'PKR',
                'entries' => [
                    ['account_code' => $debitCode, 'debit' => 950.00, 'credit' => 0.00],
                    ['account_code' => $creditCode, 'debit' => 0.00, 'credit' => 950.00],
                ],
            ], 'MAIN');
            $this->fail('Expected RuntimeException when CREATE_VOUCHER audit fails');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated CREATE_VOUCHER audit write failure', $e->getMessage());
        }

        $this->assertSame($jeCountBefore, DB::table('journal_entries')->count(), 'Abivia JournalEntry must roll back when CREATE_VOUCHER audit fails');
        $this->assertSame($djeCountBefore, DomainJournalEntry::count(), 'DomainJournalEntry must roll back when CREATE_VOUCHER audit fails');
    }

    /**
     * Test 6: VoucherService::reverseVoucher() wraps compensating voucher creation and REVERSE_VOUCHER
     * audit trail in a single transaction so failure of REVERSE_VOUCHER audit rolls back the REV- entry.
     */
    public function test_voucher_reversal_audit_failure_rolls_back_compensating_reversal_entry(): void
    {
        DomainContext::set('MAIN');
        $domainUuid = $this->getDomainUuid('MAIN');
        $resolver = app(\AlamiaSoft\AlamiaAccounts\Services\AccountMappingResolver::class);
        $debitCode = $resolver->resolveExpenseAccount('office_supplies', $domainUuid);
        $creditCode = $resolver->resolvePaymentSourceAccount('cash', $domainUuid);

        $this->voucherService->createJournalEntry([
            'reference' => 'JV-ORIG-006',
            'description' => 'Original voucher to test atomic reversal',
            'date' => '2026-10-22',
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => $debitCode, 'debit' => 1500.00, 'credit' => 0.00],
                ['account_code' => $creditCode, 'debit' => 0.00, 'credit' => 1500.00],
            ],
        ], 'MAIN');

        $jeCountAfterOriginal = DB::table('journal_entries')->count();
        $djeCountAfterOriginal = DomainJournalEntry::count();

        AccountingAuditTrail::creating(function (AccountingAuditTrail $audit) {
            if ($audit->action === 'REVERSE_VOUCHER') {
                throw new RuntimeException('Simulated REVERSE_VOUCHER audit write failure');
            }
        });

        try {
            $this->voucherService->reverseVoucher('JV-ORIG-006', '2026-10-22', 'Testing reversal atomicity');
            $this->fail('Expected RuntimeException when REVERSE_VOUCHER audit fails');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated REVERSE_VOUCHER audit write failure', $e->getMessage());
        }

        // The compensating REV-JV-ORIG-006 entry must be rolled back completely
        $this->assertSame($jeCountAfterOriginal, DB::table('journal_entries')->count(), 'Compensating REV- JournalEntry must roll back when REVERSE_VOUCHER audit fails');
        $this->assertSame($djeCountAfterOriginal, DomainJournalEntry::count(), 'Compensating REV- DomainJournalEntry must roll back when REVERSE_VOUCHER audit fails');
        $this->assertNull($this->voucherService->getVoucher('REV-JV-ORIG-006'));
    }
}
