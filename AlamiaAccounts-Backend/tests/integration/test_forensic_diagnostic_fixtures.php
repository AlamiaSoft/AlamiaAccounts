<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\AccountingDiagnosticService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\JournalDetail;
use App\Copilot\CopilotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "========================================================================\n";
echo " DETERMINISTIC FORENSIC ACCOUNTING CERTIFICATION (EP-10 / T-040)\n";
echo "========================================================================\n";

$companyCode = 'KAMAL_EXPRESS';
DomainContext::set($companyCode);
$currentDomain = DomainContext::get();
$domain = LedgerDomain::where('code', $companyCode)->firstOrFail();

$reportService = app(ReportService::class);
$accountService = app(AccountService::class);
$voucherService = app(VoucherService::class);
$diagnosticService = new AccountingDiagnosticService($reportService, $accountService);
$copilotService = app(CopilotService::class);

$asOfDate = date('Y-m-d');
$currency = 'PKR';

echo "\n>>> FIXTURE A: Corrupted Single-Legged / Unbalanced Voucher <<<\n";
$vchRef = 'CORRUPT-VCH-999';
$corruptEntryId = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'extra' => json_encode(['reference' => $vchRef, 'voucher_type' => 'journal']),
    'description' => 'Deliberate Asymmetric Corruption Test Voucher',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    'domainUuid' => $domain->domainUuid,
    'journalEntryId' => $corruptEntryId,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Single leg: Rs. 100,000 Debit to Cash without matching credit leg
$cashAccount = LedgerAccount::where('code', '1110')->first();
DB::table('journal_details')->insert([
    'journalEntryId' => $corruptEntryId,
    'ledgerUuid' => $cashAccount->ledgerUuid,
    'amount' => 100000.00,
]);

// 2. Execute Forensic Audit and Assert Exact Detection
echo "Running forensic audit against corrupted voucher...\n";
$unbalancedAnomalies = $diagnosticService->auditUnbalancedVouchers($asOfDate, $currency);

$foundVch = false;
foreach ($unbalancedAnomalies as $anom) {
    if (in_array($vchRef, $anom['affected_vouchers'] ?? [])) {
        $foundVch = true;
        assert($anom['vector'] === 'UNBALANCED_VOUCHER', "Vector must be UNBALANCED_VOUCHER");
        assert($anom['severity'] === 'critical', "Severity must be critical");
        assert(abs($anom['impact_amount'] - 100000.00) < 0.01, "Impact amount must be exactly 100,000");
        echo " [PASS] Engine pinpointed corrupted voucher '{$vchRef}' with exact impact Rs. " . number_format($anom['impact_amount'], 2) . "\n";
    }
}
assert($foundVch === true, "Engine MUST detect the corrupted single-legged voucher!");

// 3. Test Copilot Chat Diagnostic Explanation
$copilotReply = $copilotService->handleChat("Why is the balance sheet out of balance?", $companyCode, []);
assert($copilotReply['card_type'] === 'balance_sheet_diagnostics', "Card type must be balance_sheet_diagnostics");
assert(str_contains($copilotReply['message'], 'CORRUPT-VCH-999') || str_contains(json_encode($copilotReply['data']), 'CORRUPT-VCH-999'), "Copilot response must cite the corrupted voucher");
echo " [PASS] Taliya Copilot synthesized natural language explanation citing {$vchRef}\n";

// 4. Closed-Loop Remediation: Balance the corrupted voucher
echo "Remediating corrupted voucher (adding compensating credit leg)...\n";
$capitalAccount = LedgerAccount::where('code', '5100')->first();
DB::table('journal_details')->insert([
    'journalEntryId' => $corruptEntryId,
    'ledgerUuid' => $capitalAccount->ledgerUuid,
    'amount' => -100000.00,
]);

// 5. Verify re-run eliminates the anomaly
$reAudited = $diagnosticService->auditUnbalancedVouchers($asOfDate, $currency);
$stillCorrupt = false;
foreach ($reAudited as $anom) {
    if (in_array($vchRef, $anom['affected_vouchers'] ?? [])) {
        $stillCorrupt = true;
    }
}
assert($stillCorrupt === false, "Voucher MUST NOT be reported as unbalanced after remediation!");
echo " [PASS] Re-audit verified: {$vchRef} is now balanced and cleared from anomaly list.\n";

// Clean up test fixture voucher
DB::table('journal_details')->where('journalEntryId', $corruptEntryId)->delete();
DB::table('domain_journal_entries')->where('journalEntryId', $corruptEntryId)->delete();
DB::table('journal_entries')->where('journalEntryId', $corruptEntryId)->delete();


echo "\n>>> FIXTURE B: Unclassified / Orphan Account Injection & Closed-Loop Fix <<<\n";
// Pre-cleanup any leftover 1995
$old1995 = LedgerAccount::where('code', '1995')->first();
if ($old1995) {
    DB::table('domain_ledger_accounts')->where('ledgerUuid', $old1995->ledgerUuid)->delete();
    DB::table('ledger_names')->where('ownerUuid', $old1995->ledgerUuid)->delete();
    DB::table('ledger_accounts')->where('ledgerUuid', $old1995->ledgerUuid)->delete();
}

// 1. Create an unclassified account with code '1995'
$orphanUuid = Str::uuid()->toString();
DB::table('ledger_accounts')->insert([
    'ledgerUuid' => $orphanUuid,
    'code' => '1995',
    'category' => 0,
    'debit' => 1,
    'credit' => 1,
    'closed' => 0,
    'parentUuid' => null,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('ledger_names')->insert([
    'ownerUuid' => $orphanUuid,
    'language' => 'en',
    'name' => 'Unclassified Forensic Test Account',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_ledger_accounts')->insert([
    'domainUuid' => $domain->domainUuid,
    'ledgerUuid' => $orphanUuid,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Post balanced voucher with 1995
$orphanEntryId = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'description' => 'Unclassified Account Test Transaction',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    'domainUuid' => $domain->domainUuid,
    'journalEntryId' => $orphanEntryId,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('journal_details')->insert([
    [
        'journalEntryId' => $orphanEntryId,
        'ledgerUuid' => $orphanUuid,
        'amount' => 45000.00,
    ],
    [
        'journalEntryId' => $orphanEntryId,
        'ledgerUuid' => $capitalAccount->ledgerUuid,
        'amount' => -45000.00,
    ]
]);

// 2. Execute Forensic Audit
echo "Running forensic audit against unclassified account 1995...\n";
$unclassAnomalies = $diagnosticService->auditOrphanAndUnclassifiedAccounts($asOfDate, $currency);

$foundOrphan = false;
foreach ($unclassAnomalies as $anom) {
    if (in_array('1995', $anom['affected_accounts'] ?? [])) {
        $foundOrphan = true;
        assert($anom['vector'] === 'UNCLASSIFIED_ACCOUNT', "Vector must be UNCLASSIFIED_ACCOUNT");
        assert(abs($anom['impact_amount'] - 45000.00) < 0.01, "Impact amount must be exactly 45,000");
        echo " [PASS] Engine identified unclassified account '1995' with exact variance Rs. " . number_format($anom['impact_amount'], 2) . "\n";
    }
}
assert($foundOrphan === true, "Engine MUST detect unclassified account 1995!");

// 3. Closed-Loop Remediation: Classify account 1995 by assigning parent 1100 (Current Assets)
echo "Remediating unclassified account (linking parent to 1100 Current Assets)...\n";
$parentAsset = LedgerAccount::where('code', '1100')->first();
if ($parentAsset) {
    DB::table('ledger_accounts')->where('ledgerUuid', $orphanUuid)->update([
        'parentUuid' => $parentAsset->ledgerUuid
    ]);
}

// 4. Verification Re-Run
$reAuditedOrphan = $diagnosticService->auditOrphanAndUnclassifiedAccounts($asOfDate, $currency);
$stillOrphan = false;
foreach ($reAuditedOrphan as $anom) {
    if (in_array('1995', $anom['affected_accounts'] ?? [])) {
        $stillOrphan = true;
    }
}
assert($stillOrphan === false, "Account 1995 MUST NOT be reported as unclassified after remediation!");
echo " [PASS] Re-audit verified: Account 1995 classified and excluded from anomaly list.\n";

// Clean up fixture B
DB::table('journal_details')->where('journalEntryId', $orphanEntryId)->delete();
DB::table('domain_journal_entries')->where('journalEntryId', $orphanEntryId)->delete();
DB::table('journal_entries')->where('journalEntryId', $orphanEntryId)->delete();
DB::table('domain_ledger_accounts')->where('ledgerUuid', $orphanUuid)->delete();
DB::table('ledger_names')->where('ownerUuid', $orphanUuid)->delete();
DB::table('ledger_accounts')->where('ledgerUuid', $orphanUuid)->delete();


echo "\n>>> FIXTURE C: Direct Retained Earnings (5200) Posting Detection & Remediation <<<\n";
$reAccount = LedgerAccount::where('code', '5200')->first();
if ($reAccount) {
    $reEntryId = DB::table('journal_entries')->insertGetId([
        'domainUuid' => $domain->domainUuid,
        'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
        'currency' => $currency,
        'language' => 'en',
        'opening' => 0,
        'reviewed' => 1,
        'arguments' => json_encode([]),
        'description' => 'Direct Retained Earnings Distortion Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('domain_journal_entries')->insert([
        'domainUuid' => $domain->domainUuid,
        'journalEntryId' => $reEntryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('journal_details')->insert([
        [
            'journalEntryId' => $reEntryId,
            'ledgerUuid' => $cashAccount->ledgerUuid,
            'amount' => 30000.00,
        ],
        [
            'journalEntryId' => $reEntryId,
            'ledgerUuid' => $reAccount->ledgerUuid,
            'amount' => -30000.00,
        ]
    ]);

    echo "Running forensic audit against direct 5200 posting...\n";
    $reAnomalies = $diagnosticService->auditRetainedEarningsDrift($asOfDate, $currency);
    $foundRe = false;
    foreach ($reAnomalies as $anom) {
        if ($anom['vector'] === 'DIRECT_RETAINED_EARNINGS_POSTING') {
            $foundRe = true;
            assert(abs($anom['impact_amount'] - 30000.00) < 0.01, "Impact amount must be 30,000");
            echo " [PASS] Engine detected direct Retained Earnings posting with impact Rs. " . number_format($anom['impact_amount'], 2) . "\n";
        }
    }
    assert($foundRe === true, "Engine MUST flag direct entries into Retained Earnings (5200)!");

    // Clean up Fixture C
    DB::table('journal_details')->where('journalEntryId', $reEntryId)->delete();
    DB::table('domain_journal_entries')->where('journalEntryId', $reEntryId)->delete();
    DB::table('journal_entries')->where('journalEntryId', $reEntryId)->delete();
    echo " [PASS] Fixture C cleaned up and certified.\n";
}


echo "\n>>> FIXTURE D: Uncleared Suspense Account (9999) <<<\n";
$suspenseAccount = LedgerAccount::where('code', '9999')->first();
if (!$suspenseAccount) {
    $suspenseUuid = Str::uuid()->toString();
    DB::table('ledger_accounts')->insert([
        'ledgerUuid' => $suspenseUuid,
        'code' => '9999',
        'category' => 0,
        'debit' => 1,
        'credit' => 1,
        'closed' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('ledger_names')->insert([
        'ownerUuid' => $suspenseUuid,
        'language' => 'en',
        'name' => 'Suspense Account',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('domain_ledger_accounts')->insert([
        'domainUuid' => $domain->domainUuid,
        'ledgerUuid' => $suspenseUuid,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $suspenseAccount = LedgerAccount::where('code', '9999')->first();
}

$suspEntryId = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'description' => 'Uncleared Suspense Test Entry',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    'domainUuid' => $domain->domainUuid,
    'journalEntryId' => $suspEntryId,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('journal_details')->insert([
    [
        'journalEntryId' => $suspEntryId,
        'ledgerUuid' => $suspenseAccount->ledgerUuid,
        'amount' => 20000.00,
    ],
    [
        'journalEntryId' => $suspEntryId,
        'ledgerUuid' => $cashAccount->ledgerUuid,
        'amount' => -20000.00,
    ]
]);

echo "Running forensic audit against uncleared suspense account...\n";
$suspAnomalies = $diagnosticService->auditSuspenseAndOpeningPosition($asOfDate, $currency);
$foundSusp = false;
foreach ($suspAnomalies as $anom) {
    if (in_array('9999', $anom['affected_accounts'] ?? [])) {
        $foundSusp = true;
        assert($anom['vector'] === 'UNCLEARED_SUSPENSE_ACCOUNT', "Vector must be UNCLEARED_SUSPENSE_ACCOUNT");
        assert(abs($anom['impact_amount'] - 20000.00) < 0.01, "Impact amount must be exactly 20,000");
        echo " [PASS] Engine detected uncleared suspense account '9999' with balance Rs. " . number_format($anom['impact_amount'], 2) . "\n";
    }
}
assert($foundSusp === true, "Engine MUST detect uncleared suspense account 9999!");

// Clean up Fixture D
DB::table('journal_details')->where('journalEntryId', $suspEntryId)->delete();
DB::table('domain_journal_entries')->where('journalEntryId', $suspEntryId)->delete();
DB::table('journal_entries')->where('journalEntryId', $suspEntryId)->delete();
echo " [PASS] Fixture D cleaned up and certified.\n";


echo "\n>>> FIXTURE E: Non-Suppressed Forensic Scan on Balanced Books & Discrepancy Reconciliation <<<\n";
// 1. Post a balanced voucher involving suspense account
$balSuspEntryId = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'description' => 'Balanced Suspense Audit Entry',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    'domainUuid' => $domain->domainUuid,
    'journalEntryId' => $balSuspEntryId,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('journal_details')->insert([
    [
        'journalEntryId' => $balSuspEntryId,
        'ledgerUuid' => $suspenseAccount->ledgerUuid,
        'amount' => 15000.00,
    ],
    [
        'journalEntryId' => $balSuspEntryId,
        'ledgerUuid' => $cashAccount->ledgerUuid,
        'amount' => -15000.00,
    ]
]);

// 2. Run full diagnosis
$fullDiag = $diagnosticService->diagnoseBalanceSheet($asOfDate, $currency);

echo "Checking forensic scan behavior (Findings must NOT be suppressed)...\n";
assert(count($fullDiag['findings']) > 0, "Forensic findings must be populated!");
$suspFinding = null;
foreach ($fullDiag['findings'] as $f) {
    if (in_array('9999', $f['affected_accounts'] ?? [])) {
        $suspFinding = $f;
        break;
    }
}
assert($suspFinding !== null, "Suspense account 9999 finding MUST be present!");
assert(isset($suspFinding['reconciliation_status']), "Finding must have reconciliation_status");
assert(isset($suspFinding['causal_rank']), "Finding must have causal_rank");
assert(isset($fullDiag['diagnosis']), "Diagnosis object must be present");
echo " [PASS] Forensic scan produced finding with reconciliation_status='{$suspFinding['reconciliation_status']}', causal_rank={$suspFinding['causal_rank']}\n";
echo " [PASS] Diagnosis summary: Status={$fullDiag['diagnosis']['status']}, Reconciled=Rs. {$fullDiag['diagnosis']['reconciled_amount']}, Unreconciled=Rs. {$fullDiag['diagnosis']['unreconciled_amount']}\n";

// Clean up Fixture E
DB::table('journal_details')->where('journalEntryId', $balSuspEntryId)->delete();
DB::table('domain_journal_entries')->where('journalEntryId', $balSuspEntryId)->delete();
DB::table('journal_entries')->where('journalEntryId', $balSuspEntryId)->delete();
echo " [PASS] Fixture E cleaned up and certified.\n";

echo "\n========================================================================\n";
echo " 🎉 ALL 5 DETERMINISTIC FORENSIC FIXTURES & CLOSED-LOOP REMEDIATIONS PASSED 100%!\n";
echo "========================================================================\n";
exit(0);
