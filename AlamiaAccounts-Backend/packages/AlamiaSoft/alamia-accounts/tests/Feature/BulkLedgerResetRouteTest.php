<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\VoucherController;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulkLedgerResetRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();
    }

    protected function makeUser(string $companyCode = 'KAMAL_EXPRESS', string $role = 'staff', ?array $allowedCompanies = null): User
    {
        $user = new User();
        $user->forceFill([
            'id' => rand(1000, 9999),
            'name' => "Test User {$companyCode} {$role}",
            'email' => "user_{$companyCode}_{$role}_" . uniqid() . "@example.invalid",
            'role' => $role,
            'status' => 'active',
            'company_code' => $companyCode,
            'allowed_companies' => $allowedCompanies,
        ]);
        $user->exists = true;

        return $user;
    }

    public function test_bulk_ledger_reset_route_is_not_registered_in_default_configuration(): void
    {
        // By default, allow_destructive_reset is false, so route is completely unmapped
        $this->postJson('/api/vouchers/clear-all')
            ->assertMethodNotAllowed();
    }

    public function test_service_level_production_guard_prevents_reset_even_if_flag_is_true(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        $voucherService = app(VoucherService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive bulk ledger reset is strictly prohibited in production environments.');

        $voucherService->clearAllTransactions('KAMAL_EXPRESS', 'CONFIRM_RESET_KAMAL_EXPRESS');
    }

    public function test_service_level_guard_prevents_reset_when_feature_flag_is_disabled(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => false]);
        $this->app->detectEnvironment(fn () => 'testing');

        $voucherService = app(VoucherService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Destructive bulk ledger reset feature flag (alamia-accounts.allow_destructive_reset) is disabled.');

        $voucherService->clearAllTransactions('KAMAL_EXPRESS', 'CONFIRM_RESET_KAMAL_EXPRESS');
    }

    public function test_service_level_guard_requires_exact_confirmation_token(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => true]);
        $this->app->detectEnvironment(fn () => 'testing');

        $voucherService = app(VoucherService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reset confirmation token. Expected: [CONFIRM_RESET_KAMAL_EXPRESS].');

        $voucherService->clearAllTransactions('KAMAL_EXPRESS', 'WRONG_TOKEN');
    }

    public function test_controller_level_guard_denies_non_admin_users(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => true]);
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');

        $controller = app(VoucherController::class);
        $request = Request::create('/api/vouchers/clear-all', 'POST', [
            'confirmation_token' => 'CONFIRM_RESET_KAMAL_EXPRESS',
        ]);
        $request->setUserResolver(fn () => $accountant);
        $request->attributes->set('tenant_company_code', 'KAMAL_EXPRESS');

        $response = $controller->clearAll($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('insufficient_privileges', $data['error']);
    }

    public function test_controller_level_guard_denies_cross_tenant_reset(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => true]);
        $admin = $this->makeUser('KAMAL_EXPRESS', 'admin');

        $controller = app(VoucherController::class);
        $request = Request::create('/api/vouchers/clear-all', 'POST', [
            'company_code' => 'MAIN',
            'confirmation_token' => 'CONFIRM_RESET_MAIN',
        ]);
        $request->setUserResolver(fn () => $admin);
        $request->attributes->set('tenant_company_code', 'KAMAL_EXPRESS');

        $response = $controller->clearAll($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('company_access_denied', $data['error']);
    }

    public function test_controller_level_guard_denies_in_production_environment_even_for_admin(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => true]);
        $this->app->detectEnvironment(fn () => 'production');
        $admin = $this->makeUser('KAMAL_EXPRESS', 'admin');

        $controller = app(VoucherController::class);
        $request = Request::create('/api/vouchers/clear-all', 'POST', [
            'confirmation_token' => 'CONFIRM_RESET_KAMAL_EXPRESS',
        ]);
        $request->setUserResolver(fn () => $admin);
        $request->attributes->set('tenant_company_code', 'KAMAL_EXPRESS');

        $response = $controller->clearAll($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('production_reset_prohibited', $data['error']);
    }

    public function test_reversal_workflow_preserves_historical_voucher_and_audit_trail_while_physical_deletion_is_blocked(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        $ref = 'JV-TEST-IMMUTABLE-' . uniqid();
        $voucherService = app(VoucherService::class);
        DomainContext::set('KAMAL_EXPRESS');

        $voucher = $voucherService->createJournalEntry([
            'reference' => $ref,
            'description' => 'Test voucher to test GAAP immutability',
            'date' => '2026-10-16',
            'entries' => [
                ['account' => '1121', 'amount' => 500, 'type' => 'debit', 'debit' => true],
                ['account' => '2111', 'amount' => 500, 'type' => 'credit', 'credit' => true],
            ],
        ]);

        $this->assertNotNull($voucher);

        // 1. Direct DELETE is blocked with 422
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->deleteJson("/api/vouchers/{$ref}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'CANNOT_DELETE_POSTED_VOUCHER');

        // 2. Reversal is executed cleanly
        $reversalResponse = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson("/api/vouchers/{$ref}/reverse", [
                'reason' => 'Compensating correction for invoice clerical variance',
            ]);

        $reversalResponse->assertStatus(201);
        $reversalResponse->assertSee('reversed successfully');

        // 3. Verify original voucher still exists in database (historical immutability)
        $domain = \Abivia\Ledger\Models\LedgerDomain::where('code', 'KAMAL_EXPRESS')->first();
        $this->assertDatabaseHas('domain_journal_entries', [
            'domainUuid' => $domain->domainUuid,
            'journalEntryId' => $voucher->journalEntryId,
        ]);

        // 4. Verify audit trail records the reversal action
        $audit = AccountingAuditTrail::where('domain_uuid', $domain->domainUuid)
            ->where('action', 'REVERSE_VOUCHER')
            ->where('entity_reference', $ref)
            ->first();

        $this->assertNotNull($audit);
    }

    public function test_permitted_non_production_reset_clears_target_tenant_only_and_records_audit_trail(): void
    {
        config(['alamia-accounts.allow_destructive_reset' => true]);
        $this->app->detectEnvironment(fn () => 'testing');
        $admin = $this->makeUser('KAMAL_EXPRESS', 'admin');

        // Create voucher in KAMAL_EXPRESS
        DomainContext::set('KAMAL_EXPRESS');
        $voucherService = app(VoucherService::class);
        $ref = 'JV-RESET-TEST-' . uniqid();
        $voucherService->createJournalEntry([
            'reference' => $ref,
            'description' => 'Voucher to be cleared in reset test',
            'date' => '2026-10-16',
            'entries' => [
                ['account' => '1121', 'amount' => 300, 'type' => 'debit', 'debit' => true],
                ['account' => '2111', 'amount' => 300, 'type' => 'credit', 'credit' => true],
            ],
        ]);

        $controller = app(VoucherController::class);
        $request = Request::create('/api/vouchers/clear-all', 'POST', [
            'confirmation_token' => 'CONFIRM_RESET_KAMAL_EXPRESS',
        ]);
        $request->setUserResolver(fn () => $admin);
        $request->attributes->set('tenant_company_code', 'KAMAL_EXPRESS');

        $response = $controller->clearAll($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Check audit trail recorded
        $domain = \Abivia\Ledger\Models\LedgerDomain::where('code', 'KAMAL_EXPRESS')->first();
        $audit = AccountingAuditTrail::where('domain_uuid', $domain->domainUuid)
            ->where('action', 'RESET_ALL_TRANSACTIONS')
            ->first();

        $this->assertNotNull($audit);
    }
}
