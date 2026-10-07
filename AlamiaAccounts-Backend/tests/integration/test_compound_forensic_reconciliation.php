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
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\JournalDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "========================================================================\n";
echo " COMPOUND FORENSIC RECONCILIATION & LEDGER INTEGRITY AUDIT (EP-11 / T-044)\n";
echo "========================================================================\n";

$companyService = app(CompanyService::class);
$companyCode = 'FORENSIC_CORP';

// Create clean tenant domain if not exists
$domain = LedgerDomain::where('code', $companyCode)->first();
if (!$domain) {
    $domain = $companyService->createDomain($companyCode, 'Forensic Test Corporation', 'company', null, ['currency' => 'PKR', 'default_currency' => 'PKR']);
}

$kamal = LedgerDomain::where('code', 'KAMAL_EXPRESS')->firstOrFail();
$kamalUuids = DomainLedgerAccount::where('domainUuid', $kamal->domainUuid)->pluck('ledgerUuid');
foreach ($kamalUuids as $u) {
    DomainLedgerAccount::firstOrCreate(['domainUuid' => $domain->domainUuid, 'ledgerUuid' => $u]);
}

DomainContext::set($companyCode);

$reportService = app(ReportService::class);
$accountService = app(AccountService::class);
$diagnosticService = new AccountingDiagnosticService($reportService, $accountService);

$asOfDate = date('Y-m-d');
$currency = 'PKR';

$domainAccountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domain->domainUuid);
$cashAccount = LedgerAccount::where('code', '1110')->whereIn('ledgerUuid', $domainAccountUuids)->firstOrFail();
$capitalAccount = LedgerAccount::where('code', '5100')->whereIn('ledgerUuid', $domainAccountUuids)->firstOrFail();
$apAccount = LedgerAccount::where('code', '2110')->whereIn('ledgerUuid', $domainAccountUuids)->firstOrFail();

// Verify clean initial state
$initialBS = $reportService->getBalanceSheet($asOfDate, $currency);
assert($initialBS['is_balanced'] === true, "Initial test tenant must be in perfect balance (Rs. 0 discrepancy)");
echo " [PASS] Test tenant '{$companyCode}' initialized in perfect balance (0.00 variance).\n";

echo "\n>>> SCENARIO 1: Compound Multi-Vector Discrepancy Reconciliation <<<\n";
echo "Injecting Finding A: Unclassified Leaf Account '1991' (Rs. 30,000)...\n";
$acc1991Uuid = Str::uuid()->toString();
DB::table('ledger_accounts')->insert([
    'ledgerUuid' => $acc1991Uuid,
    'code' => '1991',
    'category' => 0,
    'debit' => 1,
    'credit' => 1,
    'closed' => 0,
    'parentUuid' => null,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('ledger_names')->insert([
    'ownerUuid' => $acc1991Uuid,
    'language' => 'en',
    'name' => 'Compound Test Unclassified Account',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_ledger_accounts')->insert([
    'domainUuid' => $domain->domainUuid,
    'ledgerUuid' => $acc1991Uuid,
    'created_at' => now(),
    'updated_at' => now(),
]);

$entryAId = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'description' => 'Balanced Entry with Unclassified 1991',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    'domainUuid' => $domain->domainUuid,
    'journalEntryId' => $entryAId,
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('journal_details')->insert([
    ['journalEntryId' => $entryAId, 'ledgerUuid' => $acc1991Uuid, 'amount' => 30000.00],
    ['journalEntryId' => $entryAId, 'ledgerUuid' => $capitalAccount->ledgerUuid, 'amount' => -30000.00],
]);

echo "Injecting Finding B: Unbalanced Single-Leg Voucher 'CORRUPT-VCH-888' (Rs. 50,000)...\n";
$entryBId = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => Carbon::parse($asOfDate)->toDateTimeString(),
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'extra' => json_encode(['reference' => 'CORRUPT-VCH-888']),
    'description' => 'Unbalanced Single-Leg Entry',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    'domainUuid' => $domain->domainUuid,
    'journalEntryId' => $entryBId,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Single leg credit to Capital of 50,000
DB::table('journal_details')->insert([
    ['journalEntryId' => $entryBId, 'ledgerUuid' => $capitalAccount->ledgerUuid, 'amount' => -50000.00],
]);

// Run compound diagnosis
echo "Running multi-vector non-overlapping reconciliation...\n";
$diag = $diagnosticService->diagnoseBalanceSheet($asOfDate, $currency);

echo "Total Assets: Rs. " . number_format($diag['total_assets'], 2) . "\n";
echo "Total Liab + Equity: Rs. " . number_format($diag['total_liabilities_and_equity'], 2) . "\n";
echo "Discrepancy: Rs. " . number_format($diag['discrepancy'], 2) . "\n";
echo "Diagnosis Status: {$diag['diagnosis']['status']}\n";
echo "Reconciled Amount: Rs. " . number_format($diag['diagnosis']['reconciled_amount'], 2) . "\n";
echo "Unreconciled Residual: Rs. " . number_format($diag['diagnosis']['unreconciled_amount'], 2) . "\n";
echo "Minimal Explanatory Set: " . implode(', ', $diag['diagnosis']['minimal_explanatory_set']) . "\n";

assert(abs($diag['discrepancy'] - 80000.00) < 0.01, "Discrepancy must be exactly 80,000 (30k unclassified + 50k unbalanced voucher)");
assert($diag['diagnosis']['status'] === 'EXPLAINED', "Diagnosis status must be EXPLAINED");
assert(abs($diag['diagnosis']['reconciled_amount'] - 80000.00) < 0.01, "Reconciled amount must be 80,000");
assert($diag['diagnosis']['unreconciled_amount'] < 0.01, "Unreconciled residual must be 0");
assert(count($diag['diagnosis']['minimal_explanatory_set']) === 2, "Minimal explanatory set must contain exactly 2 non-overlapping findings");

echo " [PASS] Compound non-overlapping reconciliation proved 100% of the 80,000 discrepancy!\n";

// Clean up Scenario 1
DB::table('journal_details')->whereIn('journalEntryId', [$entryAId, $entryBId])->delete();
DB::table('domain_journal_entries')->whereIn('journalEntryId', [$entryAId, $entryBId])->delete();
DB::table('journal_entries')->whereIn('journalEntryId', [$entryAId, $entryBId])->delete();
DB::table('domain_ledger_accounts')->where('ledgerUuid', $acc1991Uuid)->delete();
DB::table('ledger_names')->where('ownerUuid', $acc1991Uuid)->delete();
DB::table('ledger_accounts')->where('ledgerUuid', $acc1991Uuid)->delete();
echo " [PASS] Scenario 1 cleaned up.\n";


echo "\n>>> SCENARIO 2: Ledger Integrity Audit (Balanced Books Health Checks) <<<\n";
echo "Injecting suspicious duplicate vouchers 'DUP-A-001' and 'DUP-B-001'...\n";
$dupDate = Carbon::parse($asOfDate)->toDateTimeString();
$dup1Id = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => $dupDate,
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'extra' => json_encode(['reference' => 'DUP-A-001']),
    'description' => 'Original Vendor Payment',
    'created_at' => now(),
    'updated_at' => now(),
]);

$dup2Id = DB::table('journal_entries')->insertGetId([
    'domainUuid' => $domain->domainUuid,
    'transDate' => $dupDate,
    'currency' => $currency,
    'language' => 'en',
    'opening' => 0,
    'reviewed' => 1,
    'arguments' => json_encode([]),
    'extra' => json_encode(['reference' => 'DUP-B-001']),
    'description' => 'Accidental Duplicate Vendor Payment',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('domain_journal_entries')->insert([
    ['domainUuid' => $domain->domainUuid, 'journalEntryId' => $dup1Id, 'created_at' => now(), 'updated_at' => now()],
    ['domainUuid' => $domain->domainUuid, 'journalEntryId' => $dup2Id, 'created_at' => now(), 'updated_at' => now()],
]);

DB::table('journal_details')->insert([
    ['journalEntryId' => $dup1Id, 'ledgerUuid' => $apAccount->ledgerUuid, 'amount' => 12500.00],
    ['journalEntryId' => $dup1Id, 'ledgerUuid' => $cashAccount->ledgerUuid, 'amount' => -12500.00],
    ['journalEntryId' => $dup2Id, 'ledgerUuid' => $apAccount->ledgerUuid, 'amount' => 12500.00],
    ['journalEntryId' => $dup2Id, 'ledgerUuid' => $cashAccount->ledgerUuid, 'amount' => -12500.00],
]);

echo "Executing auditLedgerIntegrity()...\n";
$auditRes = $diagnosticService->auditLedgerIntegrity($asOfDate, $currency);

$foundDup = false;
foreach ($auditRes['findings'] as $f) {
    if (($f['audit_type'] ?? '') === 'SUSPICIOUS_DUPLICATE_VOUCHERS') {
        if (in_array('DUP-A-001', $f['affected_vouchers'] ?? []) && in_array('DUP-B-001', $f['affected_vouchers'] ?? [])) {
            $foundDup = true;
            assert(abs($f['impact_amount'] - 12500.00) < 0.01, "Duplicate amount must be 12,500");
            echo " [PASS] Ledger Integrity layer detected duplicate vouchers DUP-A-001 & DUP-B-001 (Amount: Rs. " . number_format($f['impact_amount'], 2) . ")\n";
        }
    }
}
assert($foundDup === true, "Ledger Integrity audit MUST flag duplicate vouchers!");

// Clean up Scenario 2
DB::table('journal_details')->whereIn('journalEntryId', [$dup1Id, $dup2Id])->delete();
DB::table('domain_journal_entries')->whereIn('journalEntryId', [$dup1Id, $dup2Id])->delete();
DB::table('journal_entries')->whereIn('journalEntryId', [$dup1Id, $dup2Id])->delete();
echo " [PASS] Scenario 2 cleaned up.\n";

echo "\n========================================================================\n";
echo " 🎉 ALL COMPOUND RECONCILIATION & INTEGRITY TESTS PASSED 100%!\n";
echo "========================================================================\n";
exit(0);
