<?php

namespace AlamiaSoft\AlamiaAccounts\Tests;

use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VoucherService $voucherService;
    protected AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        DomainContext::set('MAIN');
        $this->voucherService = new VoucherService();
        $this->accountService = new AccountService();
    }

    public function test_can_create_journal_entry()
    {
        $entry = $this->voucherService->createJournalEntry([
            'date' => '2025-01-01',
            'description' => 'Test Entry',
            'currency' => 'PKR',
            'entries' => [
                [
                    'account_code' => '1110',
                    'amount' => 1000,
                    'type' => 'debit',
                ],
                [
                    'account_code' => '3100',
                    'amount' => 1000,
                    'type' => 'credit',
                ],
            ],
        ]);

        $this->assertIsObject($entry);
    }

    public function test_can_create_sales_voucher()
    {
        $voucher = $this->voucherService->createSalesVoucher([
            'customer_account_code' => '1200',
            'sales_account_code' => '3100',
            'total_amount' => 1000,
            'net_amount' => 1000,
            'date' => '2025-01-01',
            'voucher_number' => 'SV001',
        ]);

        $this->assertIsObject($voucher);
    }

    public function test_can_create_payment_voucher()
    {
        $voucher = $this->voucherService->createPaymentVoucher([
            'payee_account_code' => '4200',
            'bank_account_code' => '1110',
            'amount' => 500,
            'date' => '2025-01-01',
            'voucher_number' => 'PV001',
        ]);

        $this->assertNotEmpty($voucher->journalEntryId, 'The Alamia voucher workflow must return a persisted journal entry ID.');

        $retrieved = $this->voucherService->getVoucher('PV001');
        $this->assertNotNull($retrieved, 'The payment voucher must be retrievable through VoucherService.');
        $this->assertSame('PV001', $retrieved['reference']);
        $this->assertSame(500.0, (float) collect($retrieved['line_items'])->sum('debit'));
        $this->assertSame(500.0, (float) collect($retrieved['line_items'])->sum('credit'));
    }

    public function test_rejects_posting_to_category_accounts_without_writing_a_voucher()
    {
        $journalCountBefore = DB::table('journal_entries')->count();

        $exception = null;
        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'JV-CATEGORY-REJECTED',
                'currency' => 'PKR',
                'entries' => [
                    ['account_code' => '1000', 'amount' => 1000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
                ],
            ]);

        } catch (\Exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception, 'Category accounts must not accept voucher postings.');
        $this->assertStringContainsString('category account', $exception->getMessage());
        $this->assertSame($journalCountBefore, DB::table('journal_entries')->count());
    }

    public function test_rejects_accounts_owned_by_another_domain_without_linking_them()
    {
        $isolatedDomain = app(CompanyService::class)->createCompany(
            'ISOLATED_TEST',
            'Isolated Test Company'
        );
        $mainOnlyAccount = $this->accountService->createAccount([
            'code' => 'TEST_MAIN_ONLY_01',
            'name' => 'Main Only Test Account',
            'debit' => true,
            'parent_code' => '1100',
        ], 'MAIN');

        $pivot = [
            'domainUuid' => $isolatedDomain->domainUuid,
            'ledgerUuid' => $mainOnlyAccount->ledgerUuid,
        ];

        $this->assertDatabaseMissing('domain_ledger_accounts', $pivot);

        $exception = null;
        try {
            DomainContext::scope('ISOLATED_TEST', function () {
                $this->voucherService->createJournalEntry([
                    'reference' => 'JV-CROSS-DOMAIN-REJECTED',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                        ['account_code' => 'TEST_MAIN_ONLY_01', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ]);
            });

        } catch (\Exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception, 'Accounts from another domain must not be available for posting.');
        $this->assertStringContainsString('do not belong to the current domain', $exception->getMessage());
        $this->assertDatabaseMissing('domain_ledger_accounts', $pivot);
        $this->assertSame(0, DomainLedgerAccount::where($pivot)->count());
    }

    public function test_rejects_unbalanced_vouchers_without_persisting_a_journal_entry()
    {
        $journalCountBefore = DB::table('journal_entries')->count();

        $exception = null;
        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'JV-UNBALANCED-REJECTED',
                'currency' => 'PKR',
                'entries' => [
                    ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 900, 'type' => 'credit'],
                ],
            ]);

        } catch (\Exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception, 'Unbalanced vouchers must not be persisted.');
        $this->assertStringContainsString('balance', strtolower($exception->getMessage()));
        $this->assertSame($journalCountBefore, DB::table('journal_entries')->count());
    }

    public function test_rejects_duplicate_voucher_references()
    {
        $voucherData = [
            'reference' => 'JV-DUPLICATE-001',
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
            ],
        ];

        $this->voucherService->createJournalEntry($voucherData);

        $exception = null;
        try {
            $this->voucherService->createJournalEntry($voucherData);
        } catch (\Exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception, 'Duplicate voucher references must be rejected.');
        $this->assertStringContainsString('Duplicate voucher reference', $exception->getMessage());
        $this->assertSame(1, DB::table('journal_entries')
            ->where('extra', 'like', '%JV-DUPLICATE-001%')
            ->count());
    }

    public function test_can_create_voucher_with_multiple_entries_for_same_account_netted()
    {
        // Airline ticket with commission scenario:
        // Debit Cash 1110: 12345
        // Credit Sales 3100: 12345
        // Debit Operating Expenses 4200: 345
        // Credit Cash 1110: 345
        $voucher = $this->voucherService->createJournalEntry([
            'reference' => 'TKT-TEST-MULTI-ENTRY',
            'currency' => 'PKR',
            'date' => '2026-10-15',
            'description' => 'Test ticket with commission',
            'entries' => [
                ['account_code' => '1110', 'amount' => 12345, 'type' => 'debit'],
                ['account_code' => '3100', 'amount' => 12345, 'type' => 'credit'],
                ['account_code' => '4200', 'amount' => 345, 'type' => 'debit'],
                ['account_code' => '1110', 'amount' => 345, 'type' => 'credit'],
            ],
        ]);

        $this->assertNotEmpty($voucher->journalEntryId, 'The multi-line voucher must return a persisted journal entry ID.');

        // Verify the public Alamia service can retrieve the voucher and preserve balanced, netted entries.
        $retrieved = $this->voucherService->getVoucher('TKT-TEST-MULTI-ENTRY');
        $this->assertNotNull($retrieved);
        $this->assertSame('TKT-TEST-MULTI-ENTRY', $retrieved['reference']);
        $this->assertCount(3, $retrieved['line_items'], 'Repeated account lines must be netted into three non-zero account lines.');
        $this->assertSame(12345.0, (float) collect($retrieved['line_items'])->sum('debit'));
        $this->assertSame(12345.0, (float) collect($retrieved['line_items'])->sum('credit'));
    }
}
