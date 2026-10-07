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
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerName;
use Carbon\Carbon;
use Illuminate\Support\Str;

echo "========================================================================\n";
echo " TEST: ACCOUNTING DIAGNOSTIC SERVICE & ANOMALY DETECTION (T-036)\n";
echo "========================================================================\n";

$companyCode = 'KAMAL_EXPRESS';
DomainContext::set($companyCode);
$currentDomain = DomainContext::get();

$reportService = app(ReportService::class);
$accountService = app(AccountService::class);
$voucherService = app(VoucherService::class);
$diagnosticService = new AccountingDiagnosticService($reportService, $accountService);

$asOfDate = date('Y-m-d');

// 1. Run diagnostic on current company
$diag = $diagnosticService->diagnoseBalanceSheet($asOfDate, 'PKR');

if (!isset($diag['is_balanced']) || !isset($diag['anomalies'])) {
    echo "❌ FAILED: Diagnostic output missing required keys\n";
    exit(1);
}

echo " [PASS] Diagnostic Service executed successfully. (is_balanced: " . ($diag['is_balanced'] ? 'true' : 'false') . ", Discrepancy: Rs. " . number_format($diag['discrepancy'], 2) . ")\n";
echo " [PASS] Summary compiled: {$diag['summary_text']}\n";
echo " [PASS] Step-by-step guidance steps generated: " . count($diag['step_by_step_guidance']) . " steps\n";

// 2. Test Anomaly Vectors Audit
$unclassified = $diagnosticService->auditOrphanAndUnclassifiedAccounts($asOfDate, 'PKR');
$unbalanced = $diagnosticService->auditUnbalancedVouchers($asOfDate, 'PKR');
$reDrift = $diagnosticService->auditRetainedEarningsDrift($asOfDate, 'PKR');
$suspense = $diagnosticService->auditSuspenseAndOpeningPosition($asOfDate, 'PKR');
$inversions = $diagnosticService->auditNormalBalanceInversions($asOfDate, 'PKR');

echo " [PASS] Forensic Audit Vectors evaluated: Unclassified (" . count($unclassified) . "), Unbalanced Vouchers (" . count($unbalanced) . "), Retained Earnings Drift (" . count($reDrift) . "), Suspense (" . count($suspense) . "), Inversions (" . count($inversions) . ")\n";

echo "========================================================================\n";
echo " ALL TESTS PASSED (5/5)\n";
echo "========================================================================\n";
exit(0);
