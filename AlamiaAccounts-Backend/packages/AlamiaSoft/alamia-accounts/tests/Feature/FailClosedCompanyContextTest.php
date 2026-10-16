<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ExpenseIntegrationService;
use AlamiaSoft\AlamiaAccounts\Services\OpeningBalanceService;
use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FailClosedCompanyContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();
    }

    protected function makeUser(string $companyCode = 'MAIN', string $role = 'staff'): User
    {
        $user = new User();
        $user->forceFill([
            'id' => rand(1000, 9999),
            'name' => "Test User {$companyCode}",
            'email' => "user_{$companyCode}_" . uniqid() . "@example.invalid",
            'role' => $role,
            'status' => 'active',
            'company_code' => $companyCode,
        ]);
        $user->exists = true;

        return $user;
    }

    public function test_missing_company_context_fails_closed_in_voucher_service(): void
    {
        DomainContext::reset();
        $voucherService = app(VoucherService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Company context is required for voucher operations. No active company domain set.');

        $voucherService->createJournalEntry([
            'date' => '2026-10-16',
            'reference' => 'FAIL-VCH-01',
            'description' => 'Should fail without domain context',
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 100, 'type' => 'debit'],
                ['account_code' => '1200', 'amount' => 100, 'type' => 'credit'],
            ],
        ]);
    }

    public function test_missing_company_context_fails_closed_in_account_service(): void
    {
        DomainContext::reset();
        $accountService = app(AccountService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Company context is required for account operations. No active company domain set.');

        $accountService->getChartOfAccounts();
    }

    public function test_missing_company_context_fails_closed_in_report_service(): void
    {
        DomainContext::reset();
        $reportService = app(ReportService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Company context is required for financial reports. No active company domain set.');

        $reportService->getTrialBalance('2026-10-16');
    }

    public function test_missing_company_context_fails_closed_in_opening_balance_service(): void
    {
        DomainContext::reset();
        $obService = app(OpeningBalanceService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Company context is required for opening balance operations. No active company domain set.');

        $obService->getOpeningBalanceStatus();
    }

    public function test_expense_integration_fails_closed_when_company_is_missing(): void
    {
        DomainContext::reset();
        $expenseService = app(ExpenseIntegrationService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Explicit company context is required for expense integration. Missing or unassigned company.');

        $expenseService->processExpense([
            'reference' => 'EXP-FAIL-01',
            'amount' => 500,
            'category' => 'office_supplies',
        ], null);
    }

    public function test_expense_integration_fails_closed_when_company_is_unknown(): void
    {
        DomainContext::reset();
        $expenseService = app(ExpenseIntegrationService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Ledger domain for company 'NONEXISTENT_CO' could not be resolved.");

        $expenseService->processExpense([
            'reference' => 'EXP-FAIL-02',
            'amount' => 500,
            'category' => 'office_supplies',
        ], 'NONEXISTENT_CO');
    }

    public function test_user_without_assigned_company_is_rejected_on_accounting_routes(): void
    {
        $user = $this->makeUser('');
        Sanctum::actingAs($user);

        $this->getJson('/api/accounts')
            ->assertForbidden()
            ->assertJsonPath('error', 'user_company_unassigned');

        $this->getJson('/api/vouchers')
            ->assertForbidden()
            ->assertJsonPath('error', 'user_company_unassigned');

        $this->getJson('/api/reports/trial-balance?as_of=2026-10-16')
            ->assertForbidden()
            ->assertJsonPath('error', 'user_company_unassigned');

        $this->getJson('/api/opening-balances')
            ->assertForbidden()
            ->assertJsonPath('error', 'user_company_unassigned');
    }

    public function test_user_authorized_for_company_a_cannot_read_or_write_company_b(): void
    {
        $userA = $this->makeUser('MAIN');
        Sanctum::actingAs($userA);

        // Cannot read accounts of Company B by passing header
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->getJson('/api/accounts')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');

        // Cannot read vouchers of Company B by passing header
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->getJson('/api/vouchers')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');

        // Cannot post voucher to Company B by passing header
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/vouchers', [
                'date' => '2026-10-16',
                'reference' => 'X-VCH-ATTEMPT',
                'currency' => 'PKR',
                'entries' => [
                    ['account_code' => '1110', 'amount' => 100, 'type' => 'debit'],
                    ['account_code' => '1200', 'amount' => 100, 'type' => 'credit'],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');

        // Cannot post voucher to Company B by passing company_code in body
        $this->postJson('/api/vouchers', [
            'company_code' => 'KAMAL_EXPRESS',
            'date' => '2026-10-16',
            'reference' => 'X-VCH-ATTEMPT-2',
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 100, 'type' => 'debit'],
                ['account_code' => '1200', 'amount' => 100, 'type' => 'credit'],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');
    }

    public function test_user_with_nonexistent_company_fails_closed(): void
    {
        $user = $this->makeUser('NON_EXISTENT_COMPANY');
        Sanctum::actingAs($user);

        $this->getJson('/api/accounts')
            ->assertNotFound()
            ->assertJsonPath('error', 'company_not_found');
    }

    public function test_sequential_requests_for_different_tenants_do_not_leak_context(): void
    {
        $userA = $this->makeUser('MAIN');
        $userB = $this->makeUser('KAMAL_EXPRESS');

        // Request 1: User A (MAIN)
        Sanctum::actingAs($userA);
        $resA = $this->getJson('/api/accounts');
        $resA->assertOk();

        // Immediately after request, DomainContext must be clean (reset in finally)
        $this->assertNull(DomainContext::get());

        // Request 2: User B (KAMAL_EXPRESS)
        Sanctum::actingAs($userB);
        $resB = $this->getJson('/api/accounts');
        $resB->assertOk();

        // Immediately after request, DomainContext must be clean
        $this->assertNull(DomainContext::get());
    }

    public function test_domain_context_scope_restores_previous_context_in_finally(): void
    {
        DomainContext::set('MAIN');
        $this->assertEquals('MAIN', DomainContext::get());

        try {
            DomainContext::scope('KAMAL_EXPRESS', function () {
                $this->assertEquals('KAMAL_EXPRESS', DomainContext::get());
                throw new \RuntimeException('Deliberate test failure inside scope');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Must be restored back to 'MAIN'
        $this->assertEquals('MAIN', DomainContext::get());
        DomainContext::reset();
    }

    public function test_authorized_user_can_read_and_post_within_assigned_company(): void
    {
        $user = $this->makeUser('MAIN', 'admin');
        Sanctum::actingAs($user);

        // 1. Can read accounts
        $this->getJson('/api/accounts')
            ->assertOk();

        // 2. Can post balanced voucher
        $postRes = $this->postJson('/api/vouchers', [
            'date' => '2026-10-16',
            'reference' => 'AUTH-POST-OK-01',
            'description' => 'Authorized tenant posting',
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 500, 'type' => 'debit'],
                ['account_code' => '1200', 'amount' => 500, 'type' => 'credit'],
            ],
        ]);
        $postRes->assertCreated();

        // 3. Can read trial balance
        $this->getJson('/api/reports/trial-balance?as_of_date=2026-10-16&currency=PKR')
            ->assertOk();
    }
}
