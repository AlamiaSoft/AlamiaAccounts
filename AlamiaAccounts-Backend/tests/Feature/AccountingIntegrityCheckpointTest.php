<?php

namespace Tests\Feature;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\AccountingIntegrityCheckpoint;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use AlamiaSoft\AlamiaAccounts\Services\AccountingDiagnosticService;
use AlamiaSoft\AlamiaAccounts\Services\AccountingIntegrityGuard;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingIntegrityCheckpointTest extends TestCase
{
    protected string $domainCode = 'CKP-TEST-CORP';
    protected ?LedgerDomain $domain = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed(\AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class);

        // Find or create test domain
        $companyService = app(\AlamiaSoft\AlamiaAccounts\Services\CompanyService::class);
        $this->domain = LedgerDomain::where('code', $this->domainCode)->first();
        if (!$this->domain) {
            $this->domain = $companyService->createCompany($this->domainCode, 'Checkpoint Test Corp');
        }

        DomainContext::set($this->domainCode);

        // Clean checkpoints and transactions for this domain
        AccountingIntegrityCheckpoint::where('domain_uuid', $this->domain->domainUuid)->delete();
        $entryIds = DomainJournalEntry::where('domainUuid', $this->domain->domainUuid)->pluck('journalEntryId');
        DB::table('journal_details')->whereIn('journalEntryId', $entryIds)->delete();
        DB::table('journal_entries')->whereIn('journalEntryId', $entryIds)->delete();
        DomainJournalEntry::where('domainUuid', $this->domain->domainUuid)->delete();
    }

    public function test_checkpoints_record_pass_to_pass_on_valid_vouchers(): void
    {
        $voucherService = app(VoucherService::class);

        // Post Voucher 1 (Standard JV)
        $v1 = $voucherService->createJournalEntry([
            'reference' => 'JV-TEST-001',
            'voucher_type' => 'journal',
            'description' => 'Initial Capital Deposit',
            'date' => Carbon::today()->toDateString(),
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 50000.00, 'type' => 'debit'],
                ['account_code' => '5100', 'amount' => 50000.00, 'type' => 'credit'],
            ]
        ]);

        $ckp1 = AccountingIntegrityCheckpoint::where('domain_uuid', $this->domain->domainUuid)
            ->where('voucher_reference', 'JV-TEST-001')
            ->first();

        $this->assertNotNull($ckp1);
        $this->assertTrue($ckp1->invariants_passed);
        $this->assertEquals('PASS_TO_PASS', $ckp1->transition_state);
        $this->assertEquals(0.00, $ckp1->trial_balance_difference);
        $this->assertEquals(0.00, $ckp1->bs_difference);

        // Post Voucher 2 (Custom Fee / Sale Voucher)
        $v2 = $voucherService->createJournalEntry([
            'reference' => 'SAL-TEST-002',
            'voucher_type' => 'Airticket Sale Voucher',
            'description' => 'Airticket sale to customer',
            'date' => Carbon::today()->toDateString(),
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 15000.00, 'type' => 'debit'],
                ['account_code' => '3100', 'amount' => 15000.00, 'type' => 'credit'],
            ]
        ]);

        $ckp2 = AccountingIntegrityCheckpoint::where('domain_uuid', $this->domain->domainUuid)
            ->where('voucher_reference', 'SAL-TEST-002')
            ->first();

        $this->assertNotNull($ckp2);
        $this->assertTrue($ckp2->invariants_passed);
        $this->assertEquals('PASS_TO_PASS', $ckp2->transition_state);
        $this->assertEquals('Airticket Sale Voucher', $ckp2->voucher_type);
        $this->assertEquals($ckp1->id, $ckp2->previous_checkpoint_id);
    }

    public function test_closed_loop_fault_injection_and_causal_diagnosis_certification(): void
    {
        $voucherService = app(VoucherService::class);
        $guard = app(AccountingIntegrityGuard::class);
        $diagnosticService = app(AccountingDiagnosticService::class);

        // 1. Establish Clean Baseline (V001)
        $voucherService->createJournalEntry([
            'reference' => 'V-GOOD-001',
            'voucher_type' => 'journal',
            'description' => 'Owner Capital',
            'date' => Carbon::today()->toDateString(),
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '1110', 'amount' => 100000.00, 'type' => 'debit'],
                ['account_code' => '5100', 'amount' => 100000.00, 'type' => 'credit'],
            ]
        ]);

        // 2. Fault Injection (Single-legged or corrupted posting V-FAULT-002)
        // Directly insert raw unbalanced journal detail simulating an external anomaly or corrupt posting
        $faultEntryId = DB::table('journal_entries')->insertGetId([
            'domainUuid' => $this->domain->domainUuid,
            'currency' => 'PKR',
            'opening' => 0,
            'clearing' => 0,
            'reviewed' => 0,
            'locked' => 0,
            'language' => 'en',
            'arguments' => '[]',
            'transDate' => Carbon::today(),
            'description' => 'Corrupted Single-Legged Post',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DomainJournalEntry::create([
            'domainUuid' => $this->domain->domainUuid,
            'journalEntryId' => $faultEntryId,
        ]);

        // Insert only Debit without matching Credit (Rs. 25,000 discrepancy)
        $acc1110 = LedgerAccount::where('code', '1110')->first();
        DB::table('journal_details')->insert([
            'journalEntryId' => $faultEntryId,
            'ledgerUuid' => $acc1110->ledgerUuid,
            'amount' => '25000.00', // Debit only
        ]);

        // Record checkpoint for the fault event
        $faultCkp = $guard->recordCheckpoint(
            $this->domain->domainUuid,
            'POST_VOUCHER',
            'V-FAULT-002',
            'Corrupted Voucher',
            $faultEntryId,
            Carbon::today()->toDateString(),
            'PKR'
        );

        $this->assertFalse($faultCkp->invariants_passed);
        $this->assertEquals('PASS_TO_FAIL', $faultCkp->transition_state);
        $this->assertEquals(25000.00, $faultCkp->trial_balance_difference);

        // 3. Post Subsequent Valid Voucher (V-GOOD-003)
        $voucherService->createJournalEntry([
            'reference' => 'V-GOOD-003',
            'voucher_type' => 'journal',
            'description' => 'Office Expense',
            'date' => Carbon::today()->toDateString(),
            'currency' => 'PKR',
            'entries' => [
                ['account_code' => '4200', 'amount' => 5000.00, 'type' => 'debit'],
                ['account_code' => '1110', 'amount' => 5000.00, 'type' => 'credit'],
            ]
        ]);

        $ckp3 = AccountingIntegrityCheckpoint::where('domain_uuid', $this->domain->domainUuid)
            ->where('voucher_reference', 'V-GOOD-003')
            ->first();

        $this->assertNotNull($ckp3);
        $this->assertFalse($ckp3->invariants_passed);
        $this->assertEquals('FAIL_TO_FAIL', $ckp3->transition_state);

        // 4. Execute Causal Forensic Diagnosis
        $diagnosis = $diagnosticService->diagnoseBalanceSheet(Carbon::today()->toDateString(), 'PKR');

        $this->assertFalse($diagnosis['is_balanced']);
        $this->assertGreaterThan(0, $diagnosis['findings_count']);

        // Assert that the first causal transition finding points directly to V-FAULT-002
        $causalFinding = collect($diagnosis['findings'])->firstWhere('type', 'FIRST_CAUSAL_STATE_TRANSITION');
        $this->assertNotNull($causalFinding);
        $this->assertEquals('V-FAULT-002', $causalFinding['voucher_reference']);
        $this->assertEquals(25000.00, (float)$causalFinding['amount']);
        $this->assertEquals('PASS_TO_FAIL', $causalFinding['transition_state']);

        // 5. Closed-Loop Remediation: Remove/Reconcile the Fault
        DB::table('journal_details')->where('journalEntryId', $faultEntryId)->delete();
        DB::table('journal_entries')->where('journalEntryId', $faultEntryId)->delete();
        DomainJournalEntry::where('journalEntryId', $faultEntryId)->delete();

        // Record Remediation Checkpoint
        $remedyCkp = $guard->recordCheckpoint(
            $this->domain->domainUuid,
            'REMEDIATION',
            'REV-V-FAULT-002',
            'Remediation',
            null,
            Carbon::today()->toDateString(),
            'PKR'
        );

        $this->assertTrue($remedyCkp->invariants_passed);
        $this->assertEquals('FAIL_TO_PASS', $remedyCkp->transition_state);
        $this->assertEquals(0.00, $remedyCkp->trial_balance_difference);
        $this->assertEquals(0.00, $remedyCkp->bs_difference);
    }
}
