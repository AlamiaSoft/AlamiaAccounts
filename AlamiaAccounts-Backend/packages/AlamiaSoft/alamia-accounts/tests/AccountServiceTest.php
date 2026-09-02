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
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->accountService = new AccountService();
    }

    public function test_can_create_account()
    {
        $account = $this->accountService->createAccount([
            'code' => 'TEST_ACC_01',
            'name' => 'Test Account One',
            'debit' => true,
        ]);

        $this->assertInstanceOf(LedgerAccount::class, $account);
        $this->assertEquals('TEST_ACC_01', $account->code);
    }

    public function test_can_create_account_with_parent()
    {
        $parent = $this->accountService->createAccount([
            'code' => 'TEST_GRP',
            'name' => 'Test Group Category',
            'category' => true,
            'debit' => true,
        ]);

        $child = $this->accountService->createAccount([
            'code' => 'TEST_SUB',
            'name' => 'Test Sub Account',
            'parent_code' => 'TEST_GRP',
            'debit' => true,
        ]);

        $this->assertEquals($parent->ledgerUuid, $child->parentUuid);
    }

    public function test_can_get_chart_of_accounts()
    {
        $accounts = $this->accountService->getChartOfAccounts();
        
        $this->assertGreaterThanOrEqual(5, $accounts->count());
    }

    public function test_can_update_account()
    {
        $this->accountService->createAccount([
            'code' => 'UPDATE_ME',
            'name' => 'Before Update Name',
            'debit' => true,
        ]);

        $updated = $this->accountService->updateAccount('UPDATE_ME', 'After Update Name');

        $this->assertInstanceOf(LedgerAccount::class, $updated);
        $fetched = $this->accountService->getAccount('UPDATE_ME');
        $this->assertEquals('After Update Name', $fetched['name']);
    }

    public function test_can_delete_account()
    {
        $this->accountService->createAccount([
            'code' => 'TEMP001',
            'name' => 'Temporary Account To Delete',
            'debit' => true,
        ]);

        $result = $this->accountService->deleteAccount('TEMP001');
        
        $this->assertTrue($result);
        $this->assertNull($this->accountService->getAccountByCode('TEMP001'));
    }
}
