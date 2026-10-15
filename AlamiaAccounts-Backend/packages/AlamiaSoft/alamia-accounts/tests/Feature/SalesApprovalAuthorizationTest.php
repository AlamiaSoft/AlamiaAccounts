<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Tests\TestCase;

class SalesApprovalAuthorizationTest extends TestCase
{
    public function test_machine_pos_credential_cannot_approve_a_sale(): void
    {
        $secret = 'test-pos-secret-with-sufficient-entropy';
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $secret) => 'KAMAL_EXPRESS',
        ]);

        $this->withHeader('X-POS-Key', $secret)
            ->postJson('/api/v1/sales/123/approve')
            ->assertForbidden()
            ->assertJsonPath('error', 'approval_forbidden');
    }

    public function test_sale_approval_requires_an_authorized_company_context(): void
    {
        $response = $this->withoutMiddleware()
            ->postJson('/api/v1/sales/123/approve');

        // Without the integration middleware there is no trusted company context,
        // so the controller must fail closed before attempting a database lookup.
        $response->assertForbidden()
            ->assertJsonPath('error', 'company_context_required');
    }
}
