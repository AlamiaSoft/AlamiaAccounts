<?php

namespace AlamiaSoft\AlamiaAccounts\Tests;

use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $reportService;
    protected VoucherService $voucherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->reportService = new ReportService();
        $this->voucherService = new VoucherService();

        // Post a balanced test transaction:
        // Cash (1110) Debit 15,000
        // Sales Revenue (3100) Credit 15,000
        $this->voucherService->createJournalEntry([
            'date' => date('Y-m-d'),
            'description' => 'Test Sales Revenue Receipt',
            'currency' => 'PKR',
            'reference' => 'TEST-REP-01',
            'entries' => [
                ['account_code' => '1110', 'amount' => 15000, 'type' => 'debit'],
                ['account_code' => '3100', 'amount' => 15000, 'type' => 'credit'],
            ],
        ]);

        // Post an expense transaction:
        // Rent Expense (4400) Debit 5,000
        // Cash (1110) Credit 5,000
        $this->voucherService->createJournalEntry([
            'date' => date('Y-m-d'),
            'description' => 'Test Office Rent Payment',
            'currency' => 'PKR',
            'reference' => 'TEST-REP-02',
            'entries' => [
                ['account_code' => '4400', 'amount' => 5000, 'type' => 'debit'],
                ['account_code' => '1110', 'amount' => 5000, 'type' => 'credit'],
            ],
        ]);
    }

    public function test_trial_balance_is_mathematically_balanced()
    {
        $tb = $this->reportService->getTrialBalance(date('Y-m-d'), 'PKR');

        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(15000, $tb['total_debit']); // Cash 10k net debit + Rent 5k debit = 15k
        $this->assertEquals(15000, $tb['total_credit']); // Sales 15k credit = 15k
        $this->assertCount(3, $tb['accounts']); // Cash, Sales Revenue, Rent Expense
    }

    public function test_profit_and_loss_computes_net_profit()
    {
        $pnl = $this->reportService->getProfitAndLoss(date('Y-m-d'), date('Y-m-d'), 'PKR');

        $this->assertEquals(15000, $pnl['total_income']);
        $this->assertEquals(5000, $pnl['total_expenses']);
        $this->assertEquals(10000, $pnl['net_profit']);
    }

    public function test_balance_sheet_is_balanced()
    {
        $bs = $this->reportService->getBalanceSheet(date('Y-m-d'), 'PKR');

        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals(10000, $bs['total_assets']); // Cash = 15k - 5k = 10k
        $this->assertEquals(10000, $bs['retained_earnings']); // Net profit = 10k
        $this->assertEquals(10000, $bs['total_liabilities_and_equity']);
    }

    public function test_account_ledger_statement_shows_running_balance()
    {
        $ledger = $this->reportService->getAccountLedger('1110', date('Y-m-d'), date('Y-m-d'), 'PKR');

        $this->assertEquals(0, $ledger['opening_balance']);
        $this->assertCount(2, $ledger['entries']);

        // First transaction: Debit 15,000, Balance 15,000
        $this->assertEquals(15000, $ledger['entries'][0]['debit']);
        $this->assertEquals(0, $ledger['entries'][0]['credit']);
        $this->assertEquals(15000, $ledger['entries'][0]['balance']);
        $this->assertEquals('TEST-REP-01', $ledger['entries'][0]['reference']);

        // Second transaction: Debit 0, Credit 5,000, Balance 10,000
        $this->assertEquals(0, $ledger['entries'][1]['debit']);
        $this->assertEquals(5000, $ledger['entries'][1]['credit']);
        $this->assertEquals(10000, $ledger['entries'][1]['balance']);
        $this->assertEquals('TEST-REP-02', $ledger['entries'][1]['reference']);

        $this->assertEquals(10000, $ledger['closing_balance']);
    }
}
