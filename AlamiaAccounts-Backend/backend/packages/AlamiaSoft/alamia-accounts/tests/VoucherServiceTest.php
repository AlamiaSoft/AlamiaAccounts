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
        $this->voucherService = new VoucherService();
        $this->accountService = new AccountService();
        
        // Create test accounts
        $this->accountService->createAccount([
            'code' => 'CASH',
            'name' => 'Cash',
            'debit' => true,
        ]);
        
        $this->accountService->createAccount([
            'code' => 'SALES',
            'name' => 'Sales',
            'debit' => false,
        ]);
    }

    public function test_can_create_journal_entry()
    {
        $entry = $this->voucherService->createJournalEntry([
            'date' => '2025-01-01',
            'description' => 'Test Entry',
            'currency' => 'USD',
            'entries' => [
                [
                    'account_code' => 'CASH',
                    'amount' => 1000,
                    'type' => 'debit',
                ],
                [
                    'account_code' => 'SALES',
                    'amount' => 1000,
                    'type' => 'credit',
                ],
            ],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
    }

    public function test_can_create_sales_voucher()
    {
        $this->accountService->createAccount([
            'code' => 'CUSTOMER001',
            'name' => 'Customer Account',
            'debit' => true,
        ]);

        $voucher = $this->voucherService->createSalesVoucher([
            'customer_account_code' => 'CUSTOMER001',
            'sales_account_code' => 'SALES',
            'total_amount' => 1000,
            'net_amount' => 1000,
            'date' => '2025-01-01',
            'voucher_number' => 'SV001',
        ]);

        $this->assertInstanceOf(JournalEntry::class, $voucher);
    }

    public function test_can_create_payment_voucher()
    {
        $this->accountService->createAccount([
            'code' => 'BANK',
            'name' => 'Bank Account',
            'debit' => true,
        ]);

        $this->accountService->createAccount([
            'code' => 'EXPENSE',
            'name' => 'Expense',
            'debit' => true,
        ]);

        $voucher = $this->voucherService->createPaymentVoucher([
            'payee_account_code' => 'EXPENSE',
            'bank_account_code' => 'BANK',
            'amount' => 500,
            'date' => '2025-01-01',
            'voucher_number' => 'PV001',
        ]);

        $this->assertInstanceOf(JournalEntry::class, $voucher);
    }
}
