<?php

namespace AlamiaSoft\AlamiaAccounts\Tests;

use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use Abivia\Ledger\Models\LedgerAccount;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = new AccountService();
    }

    public function test_can_create_account()
    {
        $account = $this->accountService->createAccount([
            'code' => 'CASH001',
            'name' => 'Cash in Hand',
            'debit' => true,
        ]);

        $this->assertInstanceOf(LedgerAccount::class, $account);
        $this->assertEquals('CASH001', $account->code);
    }

    public function test_can_create_account_with_parent()
    {
        $parent = $this->accountService->createAccount([
            'code' => 'ASSETS',
            'name' => 'Assets',
            'debit' => true,
        ]);

        $child = $this->accountService->createAccount([
            'code' => 'CASH',
            'name' => 'Cash',
            'parent_code' => 'ASSETS',
            'debit' => true,
        ]);

        $this->assertEquals($parent->ledgerUuid, $child->parentUuid);
    }

    public function test_can_get_chart_of_accounts()
    {
        $this->accountService->createAccount([
            'code' => 'ASSETS',
            'name' => 'Assets',
            'debit' => true,
        ]);

        $this->accountService->createAccount([
            'code' => 'LIABILITIES',
            'name' => 'Liabilities',
            'debit' => false,
        ]);

        $accounts = $this->accountService->getChartOfAccounts();
        
        $this->assertGreaterThanOrEqual(2, $accounts->count());
    }

    public function test_can_update_account()
    {
        $this->accountService->createAccount([
            'code' => 'CASH001',
            'name' => 'Cash in Hand',
            'debit' => true,
        ]);

        $result = $this->accountService->updateAccount('CASH001', [
            'name' => 'Cash Updated',
        ]);

        $this->assertTrue($result);
    }

    public function test_can_delete_account()
    {
        $this->accountService->createAccount([
            'code' => 'TEMP001',
            'name' => 'Temporary Account',
            'debit' => true,
        ]);

        $result = $this->accountService->deleteAccount('TEMP001');
        
        $this->assertTrue($result);
        $this->assertNull($this->accountService->getAccountByCode('TEMP001'));
    }
}
