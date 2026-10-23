<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicCopilotRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);
        DomainContext::reset();
    }

    protected function makeUser(string $companyCode = 'KAMAL_EXPRESS', string $role = 'staff', ?array $allowedCompanies = null, ?string $password = 'secret123'): User
    {
        $id = rand(1000, 9999);
        $user = new User();
        $user->forceFill([
            'id' => $id,
            'name' => "Test User {$companyCode} {$role}",
            'email' => "user_{$companyCode}_{$role}_" . uniqid() . "@example.invalid",
            'password' => Hash::make($password ?? 'secret123'),
            'role' => $role,
            'status' => 'active',
            'company_code' => $companyCode,
            'allowed_companies' => $allowedCompanies ?? [$companyCode],
        ]);
        $user->save();

        return $user;
    }

    public function test_unauthenticated_requests_to_copilot_routes_are_rejected(): void
    {
        $this->postJson('/api/copilot/chat', ['prompt' => 'Hello'])->assertStatus(401);
        $this->getJson('/api/copilot/capabilities')->assertStatus(401);
        $this->getJson('/api/copilot/situations')->assertStatus(401);
        $this->postJson('/api/copilot/capabilities/post_voucher/execute', ['input' => []])->assertStatus(401);
        $this->postJson('/api/alamia-360/capabilities/post_voucher/execute', ['input' => []])->assertStatus(401);
        $this->getJson('/api/copilot/diagnostics')->assertStatus(401);
        $this->getJson('/api/copilot/diagnostics/export')->assertStatus(401);
        $this->getJson('/api/copilot/knowledge')->assertStatus(401);
        $this->postJson('/api/copilot/knowledge', ['topic' => 'test'])->assertStatus(401);
        $this->deleteJson('/api/copilot/knowledge/1')->assertStatus(401);
    }

    public function test_unauthenticated_manual_endpoint_returns_only_public_data_without_custom_policies(): void
    {
        $response = $this->getJson('/api/manual');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'active_company' => 'PUBLIC',
                'custom_policies' => [],
            ],
        ]);
    }

    public function test_read_only_actor_cannot_execute_financial_mutation_capabilities(): void
    {
        $viewer = $this->makeUser('KAMAL_EXPRESS', 'viewer');
        Sanctum::actingAs($viewer);

        // Read capability succeeds
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/lookup_account/execute', [
                'input' => ['query' => 'Meezan'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Write mutation capabilities fail with 403 unauthorized_capability_mutation
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/post_voucher/execute', [
                'input' => [
                    'description' => 'Test Post',
                    'details' => [
                        ['account_code' => '1121', 'amount' => 500, 'type' => 'debit'],
                        ['account_code' => '2111', 'amount' => 500, 'type' => 'credit'],
                    ],
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'unauthorized_capability_mutation');

        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/reverse_voucher/execute', [
                'input' => ['reference' => 'JV-NONEXISTENT'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'unauthorized_capability_mutation');
    }

    public function test_ai_or_system_actor_id_cannot_bypass_human_principal_policy(): void
    {
        $viewer = $this->makeUser('KAMAL_EXPRESS', 'viewer');
        Sanctum::actingAs($viewer);

        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/post_voucher/execute', [
                'actor_id' => 'system_admin_override',
                'input' => [
                    'description' => 'Test Post as AI',
                    'details' => [
                        ['account_code' => '1121', 'amount' => 500, 'type' => 'debit'],
                        ['account_code' => '2111', 'amount' => 500, 'type' => 'credit'],
                    ],
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'unauthorized_capability_mutation');
    }

    public function test_cross_tenant_capability_execution_is_denied_when_supplying_another_company_code(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        // Caller passes X-Company-Code: KAMAL_EXPRESS, but tries to pass company_code: MAIN in JSON payload
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/lookup_account/execute', [
                'company_code' => 'MAIN',
                'input' => ['query' => 'Cash'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'company_access_denied');

        // Similarly on chat endpoint
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/chat', [
                'company_code' => 'MAIN',
                'prompt' => 'Show my balance sheet',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'company_access_denied');
    }

    public function test_maker_checker_threshold_cannot_be_bypassed_by_calling_post_voucher_directly(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        // Posting voucher with total >= Rs. 100,000 without maker-checker approval is rejected
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/post_voucher/execute', [
                'input' => [
                    'description' => 'High Value Direct Post Without Approval',
                    'details' => [
                        ['account_code' => '1121', 'amount' => 125000, 'type' => 'debit'],
                        ['account_code' => '2111', 'amount' => 125000, 'type' => 'credit'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('mandatory for institutional authorization');
    }

    public function test_maker_checker_threshold_enforces_exact_amount_reentry(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        // Valid reason provided, but re-entered amount does not match total debit (125000 vs 120000)
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/post_voucher/execute', [
                'input' => [
                    'description' => 'High Value Direct Post With Amount Mismatch',
                    'approval' => [
                        'reason' => 'Quarterly vendor payment approved by CFO and verified against invoice',
                        'reentered_amount' => 120000,
                    ],
                    'details' => [
                        ['account_code' => '1121', 'amount' => 125000, 'type' => 'debit'],
                        ['account_code' => '2111', 'amount' => 125000, 'type' => 'credit'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('Amount confirmation mismatch');
    }

    public function test_maker_checker_succeeds_with_valid_reason_and_exact_amount_reentry(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        $cfo = $this->makeUser('KAMAL_EXPRESS', 'cfo', null, 'CfoSecret123!');
        Sanctum::actingAs($accountant);

        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/post_voucher/execute', [
                'input' => [
                    'description' => 'High Value Direct Post Fully Authorized',
                    'approval' => [
                        'reason' => 'Quarterly vendor payment approved by CFO and verified against invoice',
                        'reentered_amount' => 125000,
                        'approver_id' => $cfo->id,
                        'password' => 'CfoSecret123!',
                    ],
                    'details' => [
                        ['account_code' => '1121', 'amount' => 125000, 'type' => 'debit'],
                        ['account_code' => '2111', 'amount' => 125000, 'type' => 'credit'],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.posted', true);
        $this->assertNotNull($response->json('data.reference'));
    }

    public function test_reverse_voucher_capability_requires_substantive_business_reason(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        // Trivial or short reason is rejected
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/reverse_voucher/execute', [
                'input' => [
                    'reference' => 'JV-SAMPLE-01',
                    'reason' => 'typo',
                ],
            ])
            ->assertStatus(422)
            ->assertSee('minimum 15 characters and 3 words');
    }

    public function test_diagnostic_and_knowledge_routes_require_administrative_role(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        // Ordinary accountant is denied access to diagnostics
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->getJson('/api/copilot/diagnostics')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_privileges');

        // Ordinary accountant cannot store knowledge
        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/knowledge', [
                'topic' => 'testing',
                'trigger_keywords' => ['test keyword'],
                'title' => 'Test Knowledge',
                'summary' => 'Summary of knowledge',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_privileges');

        // Admin can access diagnostics
        $admin = $this->makeUser('KAMAL_EXPRESS', 'admin');
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->getJson('/api/copilot/diagnostics')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_rate_limiting_headers_present_on_copilot_routes(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->getJson('/api/copilot/capabilities');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertEquals(60, (int) $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_internal_errors_do_not_disclose_sql_or_stack_traces(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant');
        Sanctum::actingAs($accountant);

        // Attempting to draft a voucher with invalid temporal date
        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/draft_voucher/execute', [
                'input' => [
                    'description' => 'Testing century error',
                    'date' => '1899-01-01',
                    'details' => [
                        ['account_code' => '1121', 'amount' => 100, 'type' => 'debit'],
                        ['account_code' => '2111', 'amount' => 100, 'type' => 'credit'],
                    ],
                ],
            ]);

        $response->assertStatus(422);
        // Error payload must NOT leak server file paths or database credentials
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('.php', $content);
    }
}
