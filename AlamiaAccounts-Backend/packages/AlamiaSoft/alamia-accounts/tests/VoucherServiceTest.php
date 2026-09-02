<?php

namespace AlamiaSoft\AlamiaAccounts\Tests;

use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Abivia\Ledger\Models\JournalEntry;
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

        $this->assertInstanceOf(JournalEntry::class, $entry);
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

        $this->assertInstanceOf(JournalEntry::class, $voucher);
    }

    public function test_can_create_payment_voucher()
    {
        $voucher = $this->voucherService->createPaymentVoucher([
            'payee_account_code' => '4200',
            'bank_account_code' => '1120',
            'amount' => 500,
            'date' => '2025-01-01',
            'voucher_number' => 'PV001',
        ]);

        $this->assertInstanceOf(JournalEntry::class, $voucher);
    }
}
