<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\PostingIdempotencyService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Issue #42: Enforce company-scoped voucher-reference uniqueness.
 *
 * Verifies:
 * 1. Duplicate voucher references within the same company are rejected (case-insensitively and whitespace-trimmed).
 * 2. Different companies can independently use the same voucher reference without cross-tenant collision,
 *    including when `$domainCode` is passed explicitly to `VoucherService::createJournalEntry()`.
 * 3. Database-level unique constraints (`domain_reference_unique` and `domain_reference_ci_unique`) reject
 *    concurrent duplicate inserts at the persistence boundary and roll back the Abivia `journal_entries` row.
 * 4. Historical NULL/blank and duplicate references are deterministically backfilled and disambiguated via
 *    `DomainJournalEntry::reconcileAndBackfillReferences()`.
 * 5. Exact voucher lookup (`getVoucher`) does not suffer from substring collisions (`JV-1` vs `JV-10`).
 */
class CompanyScopedVoucherReferenceUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $voucherService;
    private CompanyService $companyService;
    private string $mainDomainUuid;
    private string $secondDomainUuid;
    private string $debitAccountCode = '1110';
    private string $creditAccountCode = '5100';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);

        $this->voucherService = app(VoucherService::class);
        $this->companyService = app(CompanyService::class);

        $this->mainDomainUuid = (string) DB::table('ledger_domains')->where('code', 'MAIN')->value('domainUuid');
        $this->secondDomainUuid = (string) DB::table('ledger_domains')->where('code', 'KAMAL_EXPRESS')->value('domainUuid');

        $this->assertNotEmpty($this->mainDomainUuid, 'Default MAIN company must exist');
        $this->assertNotEmpty($this->secondDomainUuid, 'KAMAL_EXPRESS company must exist');

        DomainContext::set('MAIN');
    }

    protected function tearDown(): void
    {
        DomainJournalEntry::flushEventListeners();
        DomainJournalEntry::boot();
        DomainContext::reset();
        parent::tearDown();
    }

    public function test_it_rejects_duplicate_voucher_reference_within_same_company_case_insensitively(): void
    {
        $first = $this->voucherService->createJournalEntry([
            'reference' => 'JV-UNIQ-001',
            'date' => '2026-10-22',
            'description' => 'Original voucher in MAIN',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 5000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $this->assertNotNull($first->journalEntryId);

        foreach (['JV-UNIQ-001', 'jv-uniq-001', '  JV-uniq-001  '] as $duplicateVariant) {
            try {
                $this->voucherService->createJournalEntry([
                    'reference' => $duplicateVariant,
                    'date' => '2026-10-22',
                    'description' => 'Duplicate attempt in MAIN',
                    'entries' => [
                        ['account_code' => $this->debitAccountCode, 'debit' => 7500, 'credit' => 0],
                        ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 7500],
                    ],
                ]);
                $this->fail("Expected duplicate voucher reference variant [{$duplicateVariant}] to be rejected.");
            } catch (Exception $e) {
                $this->assertStringContainsString('Duplicate voucher reference', $e->getMessage());
                $this->assertStringContainsString('is not allowed for this company', $e->getMessage());
            }
        }

        $this->assertSame(
            1,
            DomainJournalEntry::where('domainUuid', $this->mainDomainUuid)->count(),
            'Only one domain_journal_entries row should exist in MAIN'
        );
        $this->assertSame(
            1,
            DB::table('journal_entries')->count(),
            'No duplicate journal_entries row should be persisted'
        );
    }

    public function test_it_allows_identical_voucher_reference_across_different_companies_with_strict_isolation(): void
    {
        DomainContext::set('MAIN');
        $mainEntry = $this->voucherService->createJournalEntry([
            'reference' => 'JV-SHARED-100',
            'date' => '2026-10-22',
            'description' => 'MAIN company voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 12000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 12000],
            ],
        ]);

        // Post the exact same reference to KAMAL_EXPRESS via explicit $domainCode while DomainContext is MAIN
        $secondEntry = $this->voucherService->createJournalEntry([
            'reference' => 'JV-SHARED-100',
            'date' => '2026-10-22',
            'description' => 'KAMAL_EXPRESS company voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 45000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 45000],
            ],
        ], 'KAMAL_EXPRESS');

        $this->assertNotSame($mainEntry->journalEntryId, $secondEntry->journalEntryId);
        $this->assertSame('MAIN', DomainContext::get(), 'Caller DomainContext must be preserved after explicit domainCode call');

        // Verify duplicate in KAMAL_EXPRESS via explicit $domainCode is rejected even while DomainContext is MAIN
        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'jv-shared-100',
                'date' => '2026-10-22',
                'description' => 'Duplicate in KAMAL_EXPRESS',
                'entries' => [
                    ['account_code' => $this->debitAccountCode, 'debit' => 9000, 'credit' => 0],
                    ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 9000],
                ],
            ], 'KAMAL_EXPRESS');
            $this->fail('Expected duplicate reference in KAMAL_EXPRESS to be rejected when called via explicit $domainCode.');
        } catch (Exception $e) {
            $this->assertStringContainsString("Duplicate voucher reference 'jv-shared-100' is not allowed for this company", $e->getMessage());
        }

        // Verify each tenant sees only its own voucher
        $mainVoucher = $this->voucherService->getVoucher('JV-SHARED-100');
        $this->assertNotNull($mainVoucher);
        $this->assertEquals(12000.0, (float) $mainVoucher['amount']);

        DomainContext::set('KAMAL_EXPRESS');
        $secondVoucher = $this->voucherService->getVoucher('JV-SHARED-100');
        $this->assertNotNull($secondVoucher);
        $this->assertEquals(45000.0, (float) $secondVoucher['amount']);
    }

    public function test_it_enforces_database_level_unique_index_and_rolls_back_abivia_journal_entry_on_concurrent_collision(): void
    {
        // 1. Direct DB boundary proof: case-exact and case-insensitive duplicates in same domain fail at DB level
        $firstEntry = $this->voucherService->createJournalEntry([
            'reference' => 'JV-DB-BOUNDARY-01',
            'date' => '2026-10-22',
            'description' => 'First boundary voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 3000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 3000],
            ],
        ]);
        $secondHolder = $this->voucherService->createJournalEntry([
            'reference' => 'JV-DB-HOLDER-02',
            'date' => '2026-10-22',
            'description' => 'Second boundary holder voucher',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 3500, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 3500],
            ],
        ]);
        $this->assertNotNull($firstEntry->journalEntryId);
        $this->assertNotNull($secondHolder->journalEntryId);

        try {
            DB::table('domain_journal_entries')
                ->where('journalEntryId', $secondHolder->journalEntryId)
                ->update(['reference' => 'jv-db-boundary-01']);
            $this->fail('Expected database unique index domain_reference_ci_unique to reject lowercase duplicate.');
        } catch (QueryException $qe) {
            $this->assertTrue(
                PostingIdempotencyService::isUniqueConstraintViolation($qe),
                'Expected unique constraint violation from database engine, got: ' . $qe->getMessage()
            );
        }

        // 2. Concurrent race simulation: a competing worker claims 'jv-race-001' after preflight check
        //    but right before DomainJournalEntry::create() inside VoucherService::createJournalEntry().
        $entriesCountBefore = DB::table('journal_entries')->count();
        $detailsCountBefore = DB::table('journal_details')->count();

        $raceInjected = false;
        DomainJournalEntry::creating(function (DomainJournalEntry $pivot) use (&$raceInjected, $secondHolder) {
            if (!$raceInjected && $pivot->reference === 'JV-RACE-001') {
                $raceInjected = true;
                DB::table('domain_journal_entries')
                    ->where('journalEntryId', $secondHolder->journalEntryId)
                    ->update(['reference' => 'jv-race-001']);
            }
        });

        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'JV-RACE-001',
                'date' => '2026-10-22',
                'description' => 'Concurrent contender voucher',
                'entries' => [
                    ['account_code' => $this->debitAccountCode, 'debit' => 6400, 'credit' => 0],
                    ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 6400],
                ],
            ]);
            $this->fail('Expected concurrent duplicate reference collision to throw deterministic domain Exception.');
        } catch (Exception $e) {
            $this->assertStringContainsString(
                "Duplicate voucher reference 'JV-RACE-001' is not allowed for this company",
                $e->getMessage()
            );
            $this->assertInstanceOf(QueryException::class, $e->getPrevious());
        } finally {
            DomainJournalEntry::flushEventListeners();
            DomainJournalEntry::boot();
        }

        $this->assertSame(
            $entriesCountBefore,
            DB::table('journal_entries')->count(),
            'Abivia journal_entries row must roll back atomically when DomainJournalEntry unique index rejects duplicate reference'
        );
        $this->assertSame(
            $detailsCountBefore,
            DB::table('journal_details')->count(),
            'Abivia journal_details rows must roll back atomically when DomainJournalEntry unique index rejects duplicate reference'
        );
    }

    public function test_it_backfills_null_references_and_disambiguates_historical_duplicates_deterministically(): void
    {
        // Create 4 valid vouchers first so we have real journal_entries rows
        $v1 = $this->voucherService->createJournalEntry([
            'reference' => 'TEMP-1',
            'date' => '2026-10-22',
            'description' => 'Legacy row 1 (JSON extra reference)',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 1000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 1000],
            ],
        ]);
        $v2 = $this->voucherService->createJournalEntry([
            'reference' => 'TEMP-2',
            'date' => '2026-10-22',
            'description' => 'Legacy row 2 (Duplicate of Legacy row 1 in MAIN)',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 2000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 2000],
            ],
        ]);
        $v3 = $this->voucherService->createJournalEntry([
            'reference' => 'TEMP-3',
            'date' => '2026-10-22',
            'description' => 'Legacy row 3 (Null extra & null reference)',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 3000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 3000],
            ],
        ]);
        $v4 = $this->voucherService->createJournalEntry([
            'reference' => 'TEMP-4',
            'date' => '2026-10-22',
            'description' => 'Legacy row 4 (Same reference in KAMAL_EXPRESS)',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 4000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 4000],
            ],
        ], 'KAMAL_EXPRESS');

        // Temporarily drop unique indexes to simulate pre-constraint legacy state
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS domain_reference_ci_unique');
        }
        Schema::table('domain_journal_entries', function (Blueprint $table) {
            $table->dropUnique('domain_reference_unique');
        });

        // Simulate legacy dirty state:
        // - v1 has NULL pivot reference and extra={"reference":"JV-HIST-01"}
        // - v2 has pivot reference="jv-hist-01" (case-insensitive collision with v1 in MAIN)
        // - v3 has whitespace pivot reference="   " and NULL extra
        // - v4 in KAMAL_EXPRESS has NULL pivot reference and extra={"reference":"JV-HIST-01"}
        DB::table('journal_entries')->where('journalEntryId', $v1->journalEntryId)->update([
            'extra' => json_encode(['reference' => 'JV-HIST-01', 'voucher_type' => 'journal']),
        ]);
        DB::table('domain_journal_entries')->where('journalEntryId', $v1->journalEntryId)->update([
            'reference' => null,
        ]);

        DB::table('journal_entries')->where('journalEntryId', $v2->journalEntryId)->update([
            'extra' => json_encode(['reference' => 'jv-hist-01', 'voucher_type' => 'journal']),
        ]);
        DB::table('domain_journal_entries')->where('journalEntryId', $v2->journalEntryId)->update([
            'reference' => 'jv-hist-01',
        ]);

        DB::table('journal_entries')->where('journalEntryId', $v3->journalEntryId)->update([
            'extra' => null,
        ]);
        DB::table('domain_journal_entries')->where('journalEntryId', $v3->journalEntryId)->update([
            'reference' => '   ',
        ]);

        DB::table('journal_entries')->where('journalEntryId', $v4->journalEntryId)->update([
            'extra' => json_encode(['reference' => 'JV-HIST-01', 'voucher_type' => 'journal']),
        ]);
        DB::table('domain_journal_entries')->where('journalEntryId', $v4->journalEntryId)->update([
            'reference' => null,
        ]);

        $summary = DomainJournalEntry::reconcileAndBackfillReferences();

        $this->assertSame(3, $summary['backfilled_count']);
        $this->assertSame(1, $summary['deduplicated_count']);

        // Re-apply both database unique constraints to prove reconciled data satisfies them cleanly
        Schema::table('domain_journal_entries', function (Blueprint $table) {
            $table->unique(['domainUuid', 'reference'], 'domain_reference_unique');
        });
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS domain_reference_ci_unique ON domain_journal_entries ("domainUuid", UPPER("reference"))'
            );
        }

        $ref1 = DB::table('domain_journal_entries')->where('journalEntryId', $v1->journalEntryId)->value('reference');
        $ref2 = DB::table('domain_journal_entries')->where('journalEntryId', $v2->journalEntryId)->value('reference');
        $ref3 = DB::table('domain_journal_entries')->where('journalEntryId', $v3->journalEntryId)->value('reference');
        $ref4 = DB::table('domain_journal_entries')->where('journalEntryId', $v4->journalEntryId)->value('reference');

        $this->assertSame('JV-HIST-01', $ref1);
        $this->assertSame("jv-hist-01-DUP-{$v2->journalEntryId}", $ref2);
        $this->assertSame("LEGACY-{$v3->journalEntryId}", $ref3);
        $this->assertSame('JV-HIST-01', $ref4, 'Identical reference in KAMAL_EXPRESS must not be altered by MAIN deduplication');
    }

    public function test_it_resolves_exact_voucher_by_reference_without_substring_collision(): void
    {
        $this->voucherService->createJournalEntry([
            'reference' => 'JV-10',
            'date' => '2026-10-22',
            'description' => 'Voucher Ten',
            'entries' => [
                ['account_code' => $this->debitAccountCode, 'debit' => 1000, 'credit' => 0],
                ['account_code' => $this->creditAccountCode, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $this->assertNull(
            $this->voucherService->getVoucher('JV-1'),
            'getVoucher("JV-1") must not match "JV-10" by substring'
        );

        $found = $this->voucherService->getVoucher('jv-10');
        $this->assertNotNull($found);
        $this->assertSame('JV-10', $found['reference']);
    }
}
