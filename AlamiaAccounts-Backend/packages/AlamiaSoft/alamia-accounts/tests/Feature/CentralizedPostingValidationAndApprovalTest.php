<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use AlamiaSoft\AlamiaAccounts\Exceptions\PostingValidationException;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\PostingValidationService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Issue #9: Centralize posting validation and enforce approval at the write boundary.
 *
 * Acceptance Criteria & Test Invariants:
 * 1. Parameterized invalid payloads (unbalanced, zero, negative, contradictory, missing-account,
 *    category-account, cross-domain, invalid-date, currency mismatch) are rejected consistently.
 * 2. Direct API call (POST /api/vouchers) above maker-checker threshold (>= Rs. 100,000) is blocked without approval.
 * 3. Maker-checker policy rejects trivial/short reasons and mismatched re-entered amount.
 * 4. Maker cannot approve own transaction (self-approval prohibited) where segregation of duties is required.
 * 5. Distinct approver authorization succeeds across direct API, Copilot, and VoucherService.
 * 6. All entry points (API, Copilot, VoucherService) produce identical validation outcomes.
 * 7. Failure injection proves atomic rollback (no partial journal entry or audit trail created).
 * 8. All postings have authenticated actor, source, and audit correlation IDs.
 */
class CentralizedPostingValidationAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected VoucherService $voucherService;
    protected AccountService $accountService;
    protected CompanyService $companyService;
    protected PostingValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);
        $this->seed(\Database\Seeders\KamalExpressPilotSeeder::class);

        DomainContext::set('KAMAL_EXPRESS');

        $this->voucherService = app(VoucherService::class);
        $this->accountService = app(AccountService::class);
        $this->companyService = app(CompanyService::class);
        $this->validationService = app(PostingValidationService::class);
    }

    protected function makeUser(string $companyCode = 'KAMAL_EXPRESS', string $role = 'accountant', int $id = 1001): User
    {
        $user = new User();
        $user->forceFill([
            'id' => $id,
            'name' => "User {$id} ({$role})",
            'email' => "user_{$id}_{$companyCode}@example.invalid",
            'role' => $role,
            'status' => 'active',
            'company_code' => $companyCode,
        ]);
        $user->exists = true;

        return $user;
    }

    /**
     * Test 1: Parameterized tests for unbalanced, zero/negative, contradictory, missing-account,
     * category-account, cross-domain, and invalid-date payloads.
     */
    public function test_parameterized_invalid_payloads_are_rejected(): void
    {
        // Set up an isolated second company with an account that does NOT belong to KAMAL_EXPRESS
        $isolatedCompany = $this->companyService->createCompany('ISOLATED_CORP', 'Isolated Corporation');
        $isolatedAccount = $this->accountService->createAccount([
            'code' => 'TEST_ISOLATED_ACC_99',
            'name' => 'Isolated Corp Account',
            'debit' => true,
            'parent_code' => '1100',
        ], 'ISOLATED_CORP');

        $testCases = [
            'unbalanced_payload' => [
                'payload' => [
                    'reference' => 'TEST-UNBAL-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                        ['account_code' => '3100', 'amount' => 900, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'unbalanced',
            ],
            'zero_amount_payload' => [
                'payload' => [
                    'reference' => 'TEST-ZERO-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 0, 'type' => 'debit'],
                        ['account_code' => '3100', 'amount' => 0, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'greater than zero',
            ],
            'negative_amount_payload' => [
                'payload' => [
                    'reference' => 'TEST-NEG-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => -500, 'type' => 'debit'],
                        ['account_code' => '3100', 'amount' => -500, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'negative values are prohibited',
            ],
            'contradictory_positive_debit_and_credit_on_same_line' => [
                'payload' => [
                    'reference' => 'TEST-CONTRADICT-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'debit' => 500, 'credit' => 500],
                        ['account_code' => '3100', 'amount' => 500, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'Contradictory line payload: line item for account',
            ],
            'contradictory_type_and_amount_side' => [
                'payload' => [
                    'reference' => 'TEST-CONTRADICT-02',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'type' => 'debit', 'credit' => 500],
                        ['account_code' => '3100', 'amount' => 500, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'Contradictory line payload',
            ],
            'ambiguous_amount_and_debit_mismatch' => [
                'payload' => [
                    'reference' => 'TEST-AMBIGUOUS-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 1000, 'debit' => 500],
                        ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'Ambiguous line payload',
            ],
            'missing_account_code' => [
                'payload' => [
                    'reference' => 'TEST-NO-ACC-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '', 'amount' => 1000, 'type' => 'debit'],
                        ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'must specify a valid account code',
            ],
            'category_account_posting_prohibited' => [
                'payload' => [
                    'reference' => 'TEST-CAT-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1000', 'amount' => 1000, 'type' => 'debit'], // 1000 Assets (category folder)
                        ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'is a category account and cannot receive postings',
            ],
            'cross_domain_account_posting_prohibited' => [
                'payload' => [
                    'reference' => 'TEST-CROSS-01',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                        ['account_code' => 'TEST_ISOLATED_ACC_99', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'do not belong to the current domain',
            ],
            'invalid_date_format' => [
                'payload' => [
                    'reference' => 'TEST-DATE-01',
                    'date' => 'invalid-not-a-date-at-all',
                    'currency' => 'PKR',
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                        ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'Invalid posting date format',
            ],
            'mismatched_currency' => [
                'payload' => [
                    'reference' => 'TEST-CURR-01',
                    'date' => '2026-10-16',
                    'currency' => 'EUR', // KAMAL_EXPRESS default is PKR
                    'entries' => [
                        ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                        ['account_code' => '3100', 'amount' => 1000, 'type' => 'credit'],
                    ],
                ],
                'expectedError' => 'does not match company default currency',
            ],
        ];

        foreach ($testCases as $caseName => $case) {
            $caught = null;
            try {
                $this->voucherService->createJournalEntry($case['payload'], 'KAMAL_EXPRESS');
            } catch (Exception $e) {
                $caught = $e;
            }

            $this->assertNotNull(
                $caught,
                "Expected test case '{$caseName}' to throw a validation exception, but no exception was thrown."
            );
            $this->assertStringContainsString(
                strtolower($case['expectedError']),
                strtolower($caught->getMessage()),
                "Test case '{$caseName}' error message did not contain expected fragment '{$case['expectedError']}'. Got: {$caught->getMessage()}"
            );
        }
    }

    /**
     * Test 2: Direct API post call (POST /api/vouchers) above maker-checker threshold is blocked without approval.
     */
    public function test_direct_api_call_above_maker_checker_threshold_is_blocked_without_approval(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant', 2001);
        Sanctum::actingAs($accountant);

        // High value voucher (Rs. 150,000 >= threshold Rs. 100,000) posted directly to API without approval
        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/vouchers', [
                'reference' => 'JV-HIGH-DIRECT-01',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'description' => 'Direct API Call Without Approval',
                'entries' => [
                    ['account_code' => '1110', 'amount' => 150000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 150000, 'type' => 'credit'],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'A detailed business reason (minimum 15 characters and 3 words) is mandatory for institutional authorization.',
        ]);

        $this->assertDatabaseMissing('journal_entries', [
            'description' => 'Direct API Call Without Approval',
        ]);
    }

    /**
     * Test 3: Direct API call above maker-checker threshold blocks trivial or short reason.
     */
    public function test_direct_api_call_above_maker_checker_threshold_blocks_trivial_reason(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant', 2001);
        Sanctum::actingAs($accountant);

        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/vouchers', [
                'reference' => 'JV-HIGH-TRIVIAL-01',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'description' => 'High Value Voucher With Trivial Reason',
                'approval' => [
                    'reason' => 'ok done approved',
                    'reentered_amount' => 150000,
                ],
                'entries' => [
                    ['account_code' => '1110', 'amount' => 150000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 150000, 'type' => 'credit'],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertSee('mandatory for institutional authorization');
    }

    /**
     * Test 4: Direct API call above maker-checker threshold blocks mismatched re-entered amount.
     */
    public function test_direct_api_call_above_maker_checker_threshold_blocks_mismatched_amount(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant', 2001);
        Sanctum::actingAs($accountant);

        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/vouchers', [
                'reference' => 'JV-HIGH-MISMATCH-01',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'description' => 'High Value Voucher With Amount Mismatch',
                'approval' => [
                    'reason' => 'Quarterly vendor reconciliation authorized by management per invoice',
                    'reentered_amount' => 140000, // Discrepancy: 140000 vs 150000
                ],
                'entries' => [
                    ['account_code' => '1110', 'amount' => 150000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 150000, 'type' => 'credit'],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertSee('Amount confirmation mismatch');
    }

    /**
     * Test 5: Maker cannot approve own transaction where segregation of duties is required.
     */
    public function test_maker_cannot_approve_own_transaction_when_segregation_required(): void
    {
        $maker = $this->makeUser('KAMAL_EXPRESS', 'accountant', 3001);
        Sanctum::actingAs($maker);

        // Submitting high-value voucher where approver_id matches the authenticated maker's id
        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/vouchers', [
                'reference' => 'JV-SELF-APPROVE-REJECTED',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'description' => 'Attempted Self Approval High Value',
                'approval' => [
                    'reason' => 'Annual dividend distribution authorized by managing partner review',
                    'reentered_amount' => 200000,
                    'approver_id' => 3001, // Self-approval!
                ],
                'entries' => [
                    ['account_code' => '1110', 'amount' => 200000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 200000, 'type' => 'credit'],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertSee('Self-approval prohibited: maker and approver cannot be the same user');

        // Also test directly via VoucherService with explicit actorContext
        $exception = null;
        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'JV-SERVICE-SELF-APPROVE',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'description' => 'Direct Service Self Approval',
                'approval' => [
                    'reason' => 'Annual dividend distribution authorized by managing partner review',
                    'reentered_amount' => 200000,
                    'approver_id' => 'maker_4001',
                ],
                'entries' => [
                    ['account_code' => '1110', 'amount' => 200000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 200000, 'type' => 'credit'],
                ],
            ], 'KAMAL_EXPRESS', [
                'maker_id' => 'maker_4001',
                'source' => 'test',
            ]);
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Self-approval prohibited', $exception->getMessage());
    }

    /**
     * Test 6: Maker-checker succeeds with distinct approver and valid confirmation.
     */
    public function test_maker_checker_succeeds_with_distinct_approver(): void
    {
        $maker = $this->makeUser('KAMAL_EXPRESS', 'accountant', 4001);
        $checker = $this->makeUser('KAMAL_EXPRESS', 'manager', 4002);
        Sanctum::actingAs($maker);

        $response = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->withHeader('X-Correlation-Id', 'CORR-MC-SUCCESS-001')
            ->postJson('/api/vouchers', [
                'reference' => 'JV-APPROVED-SUCCESS-01',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'description' => 'Fully Authorized High Value Voucher',
                'approval' => [
                    'reason' => 'Quarterly vendor audit settlement verified against contract number 7890',
                    'reentered_amount' => 250000,
                    'approver_id' => 4002, // Distinct approver!
                ],
                'entries' => [
                    ['account_code' => '1110', 'amount' => 250000, 'type' => 'debit'],
                    ['account_code' => '3100', 'amount' => 250000, 'type' => 'credit'],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.reference', 'JV-APPROVED-SUCCESS-01');

        // Verify audit trail captured maker, approver, reason, source, and correlation ID
        $audit = AccountingAuditTrail::where('entity_reference', 'JV-APPROVED-SUCCESS-01')->first();
        $this->assertNotNull($audit, 'Audit trail must record the posted voucher');
        $this->assertEquals('CREATE_VOUCHER', $audit->action);
        $this->assertEquals('4001', $audit->user_id);

        $details = $audit->details;
        $this->assertIsArray($details);
        $this->assertEquals('4001', $details['maker_id']);
        $this->assertEquals('4002', $details['approver_id']);
        $this->assertEquals('api', $details['source']);
        $this->assertEquals('CORR-MC-SUCCESS-001', $details['correlation_id']);
        $this->assertEquals(250000.0, (float) $details['total_amount']);
        $this->assertStringContainsString('Quarterly vendor audit settlement', $details['approval_reason']);
    }

    /**
     * Test 7: All supported entry points (Direct Service, API Controller, Copilot) produce
     * consistent validation outcomes for the exact same payload.
     */
    public function test_all_entry_points_produce_consistent_validation_results(): void
    {
        $accountant = $this->makeUser('KAMAL_EXPRESS', 'accountant', 5001);
        Sanctum::actingAs($accountant);

        // Contradictory payload: line with positive debit AND credit
        $invalidPayload = [
            'reference' => 'JV-CONTRADICT-CONSISTENT',
            'date' => '2026-10-16',
            'currency' => 'PKR',
            'description' => 'Contradictory Line Test',
            'entries' => [
                ['account_code' => '1110', 'debit' => 500, 'credit' => 500],
                ['account_code' => '3100', 'amount' => 500, 'type' => 'credit'],
            ],
        ];

        // 1. Direct VoucherService call
        $serviceException = null;
        try {
            $this->voucherService->createJournalEntry($invalidPayload, 'KAMAL_EXPRESS');
        } catch (PostingValidationException $e) {
            $serviceException = $e;
        }
        $this->assertNotNull($serviceException);
        $this->assertStringContainsString('Contradictory line payload', $serviceException->getMessage());

        // 2. Direct API Controller call (POST /api/vouchers)
        $apiResponse = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/vouchers', $invalidPayload);
        $apiResponse->assertStatus(422);
        $this->assertStringContainsString('Contradictory line payload', $apiResponse->json('message'));

        // 3. Copilot capability call (post_voucher)
        $copilotResponse = $this->withHeader('X-Company-Code', 'KAMAL_EXPRESS')
            ->postJson('/api/copilot/capabilities/post_voucher/execute', [
                'input' => [
                    'reference' => 'JV-CONTRADICT-CONSISTENT',
                    'date' => '2026-10-16',
                    'currency' => 'PKR',
                    'description' => 'Contradictory Line Test',
                    'details' => [
                        ['account_code' => '1110', 'debit' => 500, 'credit' => 500],
                        ['account_code' => '3100', 'amount' => 500, 'type' => 'credit'],
                    ],
                ],
            ]);
        $copilotResponse->assertStatus(422);
        $this->assertStringContainsString('Contradictory line payload', $copilotResponse->json('error'));
    }

    /**
     * Test 8: Failure injection proves atomic rollback (no partial records created).
     */
    public function test_failure_injection_proves_atomic_rollback(): void
    {
        $initialJournals = DB::table('journal_entries')->count();
        $initialAudits = DB::table('accounting_audit_trails')->count();
        $initialPivots = DB::table('domain_journal_entries')->count();

        // Inject failure during posting by supplying invalid locked period
        $exception = null;
        try {
            $this->voucherService->createJournalEntry([
                'reference' => 'JV-ROLLBACK-TEST',
                'date' => '2026-10-16',
                'currency' => 'PKR',
                'entries' => [
                    ['account_code' => '1110', 'amount' => 1000, 'type' => 'debit'],
                    ['account_code' => '99999_NON_EXISTENT', 'amount' => 1000, 'type' => 'credit'],
                ],
            ], 'KAMAL_EXPRESS');
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame($initialJournals, DB::table('journal_entries')->count(), 'No journal entry must be persisted');
        $this->assertSame($initialAudits, DB::table('accounting_audit_trails')->count(), 'No audit record must be persisted');
        $this->assertSame($initialPivots, DB::table('domain_journal_entries')->count(), 'No domain pivot must be persisted');
    }
}
