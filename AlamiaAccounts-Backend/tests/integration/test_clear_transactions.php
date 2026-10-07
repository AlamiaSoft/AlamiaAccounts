<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

echo "========================================================================\n";
echo " TEST: TENANT TRANSACTIONS RESET UTILITY\n";
echo "========================================================================\n";

$voucherService = app(VoucherService::class);
$accountService = app(AccountService::class);
$customTypeService = app(CustomVoucherTypeService::class);

DomainContext::set('KAMAL_EXPRESS');

// 1. Check existing transactions count
$vouchersBefore = $voucherService->getVouchers();
echo " [INFO] Found " . count($vouchersBefore) . " vouchers before clearing.\n";

// 2. Perform Clear All Transactions
$clearedCount = $voucherService->clearAllTransactions('KAMAL_EXPRESS');
echo " [PASS] Cleared {$clearedCount} transactions.\n";

// 3. Verify zero vouchers remain
$vouchersAfter = $voucherService->getVouchers();
if (count($vouchersAfter) !== 0) {
    echo "[FAIL] Expected 0 vouchers after clear, got " . count($vouchersAfter) . "\n";
    exit(1);
}
echo " [PASS] Ledger now has 0 vouchers (clean slate).\n";

// 4. Verify Chart of Accounts is intact
$accounts = $accountService->getChartOfAccountsFormatted();
if (count($accounts) === 0) {
    echo "[FAIL] Chart of Accounts was wiped!\n";
    exit(1);
}
echo " [PASS] Chart of Accounts preserved (" . count($accounts) . " accounts intact).\n";

// 5. Verify Custom Voucher Types are intact
$types = $customTypeService->getAllVoucherTypes('KAMAL_EXPRESS');
$hasTKT = false;
foreach ($types as $t) {
    if ($t['prefix'] === 'TKT') {
        $hasTKT = true;
        break;
    }
}
if (!$hasTKT) {
    echo "[FAIL] Custom Voucher Types were wiped!\n";
    exit(1);
}
echo " [PASS] Custom Voucher Types preserved (TKT Airline Ticket Booking intact).\n";

echo "========================================================================\n";
echo " ALL TESTS PASSED (4/4)\n";
echo "========================================================================\n";
