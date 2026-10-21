<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Models\OperationalSale;
use AlamiaSoft\AlamiaAccounts\Models\OperationalSaleItem;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesApprovalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();
    }

    protected function actingAsStaffInCompany(string $companyCode = 'KAMAL_EXPRESS', int $userId = 9001): User
    {
        $user = new User();
        $user->forceFill([
            'id' => $userId,
            'name' => 'POS Test Staff',
            'email' => "pos-staff-{$userId}@example.invalid",
            'role' => 'staff',
            'company_code' => $companyCode,
        ]);
        $user->exists = true;

        Sanctum::actingAs($user);
        return $user;
    }

    protected function actingAsManagerInCompany(string $companyCode = 'KAMAL_EXPRESS', int $userId = 9002): User
    {
        $user = new User();
        $user->forceFill([
            'id' => $userId,
            'name' => 'POS Test Manager',
            'email' => "pos-manager-{$userId}@example.invalid",
            'role' => 'manager',
            'company_code' => $companyCode,
        ]);
        $user->exists = true;

        Sanctum::actingAs($user);
        return $user;
    }

    protected function createTestSale(
        string $companyCode,
        string $status = 'staged',
        string $userId = '9001',
        float $amount = 50000.0,
        float $paidAmount = 0.0
    ): OperationalSale {
        $sale = OperationalSale::create([
            'company_code' => $companyCode,
            'client_reference_id' => 'TEST-' . uniqid(),
            'created_by_user_id' => $userId,
            'created_by_user_name' => 'Agent ' . $userId,
            'customer_name' => 'Test Customer',
            'customer_phone' => '03001234567',
            'customer_subledger_code' => '1200',
            'issue_date' => now()->toDateString(),
            'status' => $status,
            'workflow_mode' => $status === 'posted' ? 'instant_post' : 'pending_approval',
            'gross_amount' => $amount,
            'tax_amount' => 0.00,
            'discount_amount' => 0.00,
            'net_amount' => $amount,
            'paid_amount' => $paidAmount,
            'balance_due' => $amount - $paidAmount,
            'sales_voucher_ref' => $status === 'posted' ? 'SV-2026-TEST' : null,
            'receipt_voucher_ref' => ($status === 'posted' && $paidAmount > 0) ? 'RV-2026-TEST' : null,
        ]);

        OperationalSaleItem::create([
            'operational_sale_id' => $sale->id,
            'description' => 'Test Air Ticket',
            'quantity' => 1.0,
            'unit_price' => $amount,
            'total_price' => $amount,
            'revenue_account_code' => '4100',
        ]);

        return $sale;
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/sales')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');

        $this->getJson('/api/v1/sales/1')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');

        $this->postJson('/api/v1/sales/1/approve')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');

        $this->getJson('/api/v1/receipts/1/print')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');

        $this->postJson('/api/v1/sales/reconcile')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');
    }

    public function test_regular_staff_cannot_approve_a_sale(): void
    {
        $this->actingAsStaffInCompany();

        $this->postJson('/api/v1/sales/123/approve')
            ->assertForbidden()
            ->assertJsonPath('error', 'approval_forbidden');
    }

    public function test_staff_cannot_select_another_company_for_approval(): void
    {
        $this->actingAsStaffInCompany();

        $this->withHeader('X-Company-Code', 'OTHER_COMPANY')
            ->postJson('/api/v1/sales/123/approve')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');
    }

    public function test_sale_approval_requires_an_authorized_company_context(): void
    {
        $response = $this->withoutMiddleware()
            ->postJson('/api/v1/sales/123/approve');

        $response->assertForbidden()
            ->assertJsonPath('error', 'company_context_required');
    }

    public function test_cross_tenant_show_returns_404_not_found(): void
    {
        $otherCompanySale = $this->createTestSale('MAIN', 'posted', '8001', 30000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        // Manager in KAMAL_EXPRESS cannot view sale belonging to MAIN
        $this->getJson("/api/v1/sales/{$otherCompanySale->id}")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');
    }

    public function test_cross_tenant_print_receipt_returns_404_not_found(): void
    {
        $otherCompanySale = $this->createTestSale('MAIN', 'posted', '8001', 30000.0, 30000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        // JSON print query returns safe 404
        $this->getJson("/api/v1/receipts/{$otherCompanySale->id}/print")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');

        // Direct browser/HTML print query returns HTTP 404
        $this->get("/api/v1/receipts/{$otherCompanySale->id}/print")
            ->assertNotFound();
    }

    public function test_cross_tenant_approve_returns_404_not_found(): void
    {
        $otherCompanySale = $this->createTestSale('MAIN', 'staged', '8001', 45000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        // KAMAL_EXPRESS manager cannot approve MAIN sale by guessing numeric ID
        $this->postJson("/api/v1/sales/{$otherCompanySale->id}/approve")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');
    }

    public function test_cross_tenant_reconcile_shift_ignores_other_companies_and_rejects_cross_company_params(): void
    {
        // KAMAL_EXPRESS cash sale (Rs. 15,000 paid)
        $this->createTestSale('KAMAL_EXPRESS', 'posted', '9001', 15000.0, 15000.0);

        // MAIN cash sale (Rs. 50,000 paid)
        $this->createTestSale('MAIN', 'posted', '8001', 50000.0, 50000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        // Supplying a mismatched company_code parameter is rejected with 403 company_access_denied
        $this->postJson('/api/v1/sales/reconcile', [
            'company_code' => 'MAIN',
            'actual_cash' => 15000,
        ])
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');

        // Reconciling without company parameter automatically scopes strictly to authenticated KAMAL_EXPRESS
        $response = $this->postJson('/api/v1/sales/reconcile', [
            'actual_cash' => 15000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company_code', 'KAMAL_EXPRESS')
            ->assertJsonPath('data.cash_drawer.status', 'balanced');

        $this->assertEquals(15000, (float) $response->json('data.summary.total_cash_collected'));
    }

    public function test_staff_cannot_reconcile_other_staff_shift(): void
    {
        $this->actingAsStaffInCompany('KAMAL_EXPRESS', 9001);

        $this->postJson('/api/v1/sales/reconcile', [
            'agent_id' => '9999',
            'actual_cash' => 5000,
        ])
            ->assertForbidden()
            ->assertJsonPath('error', 'shift_reconciliation_forbidden');
    }

    public function test_staff_reconciliation_is_scoped_strictly_to_own_sales(): void
    {
        // Staff 9001 sale (Rs. 10,000 cash)
        $this->createTestSale('KAMAL_EXPRESS', 'posted', '9001', 10000.0, 10000.0);

        // Staff 9002 sale (Rs. 25,000 cash)
        $this->createTestSale('KAMAL_EXPRESS', 'posted', '9002', 25000.0, 25000.0);

        $this->actingAsStaffInCompany('KAMAL_EXPRESS', 9001);

        $response = $this->postJson('/api/v1/sales/reconcile', [
            'actual_cash' => 10000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.agent_id', '9001')
            ->assertJsonPath('data.cash_drawer.status', 'balanced');

        $this->assertEquals(10000, (float) $response->json('data.summary.total_cash_collected'));
    }

    public function test_manager_can_approve_staged_sale_in_own_company(): void
    {
        $sale = $this->createTestSale('KAMAL_EXPRESS', 'staged', '9001', 75000.0, 75000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        $response = $this->postJson("/api/v1/sales/{$sale->id}/approve");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sale approved and committed to ledger.')
            ->assertJsonPath('data.status', 'posted');

        $sale->refresh();
        $this->assertEquals('posted', $sale->status);
        $this->assertNotNull($sale->sales_voucher_ref);
        $this->assertNotNull($sale->receipt_voucher_ref);

        // Verify Accounting Audit Trail record was logged
        $this->assertDatabaseHas('accounting_audit_trails', [
            'action' => 'SALE_APPROVED',
            'entity_type' => 'operational_sales',
            'entity_reference' => (string) $sale->id,
        ]);
    }

    public function test_approving_already_posted_sale_is_rejected(): void
    {
        $sale = $this->createTestSale('KAMAL_EXPRESS', 'posted', '9001', 50000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        $this->postJson("/api/v1/sales/{$sale->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error', 'sale_already_approved');
    }

    public function test_approving_sale_in_invalid_state_is_rejected(): void
    {
        $sale = $this->createTestSale('KAMAL_EXPRESS', 'cancelled', '9001', 50000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        $this->postJson("/api/v1/sales/{$sale->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_sale_state');
    }

    public function test_numeric_id_enumeration_returns_safe_404(): void
    {
        $this->actingAsManagerInCompany('KAMAL_EXPRESS');

        $nonExistentId = 9999999;

        $this->getJson("/api/v1/sales/{$nonExistentId}")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');

        $this->postJson("/api/v1/sales/{$nonExistentId}/approve")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');

        $this->getJson("/api/v1/receipts/{$nonExistentId}/print")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');
    }

    public function test_non_manager_cannot_view_other_staff_sales_in_same_company(): void
    {
        $saleOtherStaff = $this->createTestSale('KAMAL_EXPRESS', 'posted', '9002', 20000.0);

        $this->actingAsStaffInCompany('KAMAL_EXPRESS', 9001);

        $this->getJson("/api/v1/sales/{$saleOtherStaff->id}")
            ->assertNotFound()
            ->assertJsonPath('error', 'sale_not_found');
    }

    public function test_manager_can_view_any_staff_sales_in_own_company(): void
    {
        $saleStaff = $this->createTestSale('KAMAL_EXPRESS', 'posted', '9001', 20000.0);

        $this->actingAsManagerInCompany('KAMAL_EXPRESS', 9002);

        $this->getJson("/api/v1/sales/{$saleStaff->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $saleStaff->id);
    }
}
