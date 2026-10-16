<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

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

    protected function makeUser(string $companyCode = 'MAIN', string $role = 'staff', ?array $allowedCompanies = null): User
    {
        $user = new User();
        $user->forceFill([
            'id' => rand(1000, 9999),
            'name' => "Test User {$companyCode}",
            'email' => "user_{$companyCode}_" . uniqid() . "@example.invalid",
            'role' => $role,
            'status' => 'active',
            'company_code' => $companyCode,
            'allowed_companies' => $allowedCompanies,
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

    public function test_company_code_and_allowed_companies_are_not_mass_assignable(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'company_code' => 'MAIN',
            'allowed_companies' => ['*'],
        ]);

        $this->assertNull($user->company_code, 'company_code must not be mass-assignable via fill/constructor');
        $this->assertNull($user->allowed_companies, 'allowed_companies must not be mass-assignable via fill/constructor');

        $user->fill([
            'company_code' => 'KAMAL_EXPRESS',
            'allowed_companies' => ['MAIN'],
        ]);

        $this->assertNull($user->company_code, 'company_code must not be mass-assignable via fill()');
        $this->assertNull($user->allowed_companies, 'allowed_companies must not be mass-assignable via fill()');
    }

    public function test_ordinary_user_update_endpoint_cannot_alter_company_assignment(): void
    {
        $targetUser = new User();
        $targetUser->name = 'Staff Member';
        $targetUser->email = 'staff_' . uniqid() . '@example.invalid';
        $targetUser->password = bcrypt('password');
        $targetUser->role = 'viewer';
        $targetUser->status = 'active';
        $targetUser->company_code = 'KAMAL_EXPRESS';
        $targetUser->allowed_companies = ['KAMAL_EXPRESS'];
        $targetUser->save();

        $adminUser = $this->makeUser('MAIN', 'admin');
        Sanctum::actingAs($adminUser);

        // Attempt to update target user and smuggle company_code and allowed_companies
        $response = $this->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Updated Staff Name',
            'company_code' => 'MAIN',
            'allowed_companies' => ['*'],
        ]);

        $response->assertOk();
        $targetUser->refresh();

        $this->assertEquals('Updated Staff Name', $targetUser->name);
        $this->assertEquals('KAMAL_EXPRESS', $targetUser->company_code, 'Target user company_code must remain unchanged');
        $this->assertEquals(['KAMAL_EXPRESS'], $targetUser->allowed_companies, 'Target user allowed_companies must remain unchanged');
    }

    public function test_multi_company_authorized_user_can_access_any_permitted_company_via_header(): void
    {
        // User assigned to MAIN, with authorization for both MAIN and KAMAL_EXPRESS
        $multiUser = $this->makeUser('MAIN', 'staff', ['MAIN', 'KAMAL_EXPRESS']);
        Sanctum::actingAs($multiUser);

        // 1. Default request uses selected company (MAIN)
        $this->getJson('/api/accounts')
            ->assertOk();

        // 2. Explicit header selects permitted company (KAMAL_EXPRESS)
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->getJson('/api/accounts')
            ->assertOk();
    }

    public function test_multi_company_authorized_user_is_denied_access_to_unpermitted_company(): void
    {
        // User authorized only for MAIN and KAMAL_EXPRESS
        $multiUser = $this->makeUser('MAIN', 'staff', ['MAIN', 'KAMAL_EXPRESS']);
        Sanctum::actingAs($multiUser);

        // Attempt to access unpermitted company domain
        $this->withHeader('X-Company-Code', 'UNAUTHORIZED_CORP')
            ->getJson('/api/accounts')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');
    }

    public function test_multi_company_user_can_switch_active_company_via_switch_endpoint(): void
    {
        $user = new User();
        $user->name = 'Multi Tenant Accountant';
        $user->email = 'accountant_' . uniqid() . '@example.invalid';
        $user->password = bcrypt('password');
        $user->role = 'accountant';
        $user->status = 'active';
        $user->company_code = 'MAIN';
        $user->allowed_companies = ['MAIN', 'KAMAL_EXPRESS'];
        $user->save();

        Sanctum::actingAs($user);

        // 1. Switch to authorized company KAMAL_EXPRESS
        $switchRes = $this->postJson('/api/companies/KAMAL_EXPRESS/switch');
        $switchRes->assertOk()
            ->assertJsonPath('current_company', 'KAMAL_EXPRESS');

        $user->refresh();
        $this->assertEquals('KAMAL_EXPRESS', $user->company_code);

        // 2. Next request without headers defaults to the newly selected company (KAMAL_EXPRESS)
        $this->getJson('/api/accounts')
            ->assertOk();
    }

    public function test_single_tenant_user_cannot_switch_to_another_company(): void
    {
        $singleUser = new User();
        $singleUser->name = 'Single Company Clerk';
        $singleUser->email = 'single_' . uniqid() . '@example.invalid';
        $singleUser->password = bcrypt('password');
        $singleUser->role = 'accountant';
        $singleUser->status = 'active';
        $singleUser->company_code = 'MAIN';
        $singleUser->allowed_companies = null;
        $singleUser->save();

        Sanctum::actingAs($singleUser);

        $switchRes = $this->postJson('/api/companies/KAMAL_EXPRESS/switch');
        $switchRes->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');

        $singleUser->refresh();
        $this->assertEquals('MAIN', $singleUser->company_code);
    }

    public function test_company_index_returns_only_authorized_companies_for_user(): void
    {
        // 1. Scoped user authorized only for KAMAL_EXPRESS
        $scopedUser = $this->makeUser('KAMAL_EXPRESS', 'staff', ['KAMAL_EXPRESS']);
        Sanctum::actingAs($scopedUser);

        $res = $this->getJson('/api/companies');
        $res->assertOk();
        $codes = collect($res->json('data'))->pluck('code')->all();
        $this->assertContains('KAMAL_EXPRESS', $codes);
        $this->assertNotContains('MAIN', $codes);

        // 2. Admin user sees all companies
        $adminUser = $this->makeUser('MAIN', 'admin');
        Sanctum::actingAs($adminUser);

        $adminRes = $this->getJson('/api/companies');
        $adminRes->assertOk();
        $adminCodes = collect($adminRes->json('data'))->pluck('code')->all();
        $this->assertContains('MAIN', $adminCodes);
        $this->assertContains('KAMAL_EXPRESS', $adminCodes);
    }
}
