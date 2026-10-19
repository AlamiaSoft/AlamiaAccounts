<?php

namespace AlamiaSoft\\AlamiaAccounts\\Tests\\Feature;

use App\\Models\\User;
use Laravel\\Sanctum\\Sanctum;
use Tests\\TestCase;

class SalesApprovalAuthorizationTest extends TestCase
{
    protected function actingAsStaffInCompany(string $companyCode = 'KAMAL_EXPRESS'): void
    {
        $user = new User();
        $user->forceFill([
            'id' => 9001,
            'name' => 'POS Test Staff',
            'email' => 'pos-staff-test@example.invalid',
            'role' => 'staff',
            'company_code' => $companyCode,
        ]);
        $user->exists = true;

        Sanctum::actingAs($user);
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

    public function test_sale_details_require_an_authorized_company_context(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/v1/sales/123')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_context_required');
    }

    public function test_receipt_printing_requires_an_authorized_company_context(): void
    {
        $response = $this->withoutMiddleware()
            ->get('/api/v1/receipts/123/print');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('An authorized company context is required.', $response->getContent());
    }
}
