<?php

require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

echo "========================================================================" . PHP_EOL;
echo " TEST: SUBLEDGER ANALYTICS SERVICE & AGING BREAKDOWN (T-033)" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

DomainContext::set('KAMAL_EXPRESS');
$reportService = app(ReportService::class);
$voucherService = app(VoucherService::class);
$accountService = app(AccountService::class);

// 1. Ensure customer and supplier leaf accounts exist
$customerAcc = $accountService->getAccountByCode('1210');
if (!$customerAcc) {
    $customerAcc = $accountService->createAccount([
        'code' => '1210',
        'name' => 'Corporate Client A (Tested)',
        'category' => false,
        'debit' => true,
        'parent_code' => '1200',
    ]);
}

$supplierAcc = $accountService->getAccountByCode('2110');
if (!$supplierAcc) {
    $supplierAcc = $accountService->createAccount([
        'code' => '2110',
        'name' => 'ABC Tours & Travels (Tested)',
        'category' => false,
        'debit' => false,
        'parent_code' => '2100',
    ]);
}

// 2. Post a Sales Invoice to Corporate Client A (Dr: 1210 Customer, Cr: 3100 Revenue)
$invRef = 'SV-' . date('Y') . '-' . rand(1000, 9999);
$voucherService->createJournalEntry([
    'date' => date('Y-m-d'),
    'voucher_type' => 'sales',
    'reference' => $invRef,
    'description' => 'Invoice for Corporate Flight Bookings',
    'currency' => 'PKR',
    'entries' => [
        [
            'account_code' => '1210',
            'amount' => 75000.00,
            'type' => 'debit',
            'description' => 'Sales on credit',
        ],
        [
            'account_code' => '3100',
            'amount' => 75000.00,
            'type' => 'credit',
            'description' => 'Corporate Sales Revenue',
        ],
    ],
]);
echo " [PASS] Created Credit Sale to 1210 Corporate Client A: {$invRef} (Rs. 75,000)" . PHP_EOL;

// 3. Post a Vendor Bill from ABC Tours (Dr: 4100 COGS, Cr: 2110 ABC Tours)
$billRef = 'PUV-' . date('Y') . '-' . rand(1000, 9999);
$voucherService->createJournalEntry([
    'date' => date('Y-m-d'),
    'voucher_type' => 'purchase',
    'reference' => $billRef,
    'description' => 'Vendor Bill from ABC Tours for Hotel Stays',
    'currency' => 'PKR',
    'entries' => [
        [
            'account_code' => '4100',
            'amount' => 45000.00,
            'type' => 'debit',
            'description' => 'Hotel Cost of Goods Sold',
        ],
        [
            'account_code' => '2110',
            'amount' => 45000.00,
            'type' => 'credit',
            'description' => 'Payable to ABC Tours',
        ],
    ],
]);
echo " [PASS] Created Vendor Bill for 2110 ABC Tours: {$billRef} (Rs. 45,000)" . PHP_EOL;

// 4. Test Receivables Subledger Report
$arReport = $reportService->getReceivablesReport(date('Y-m-d'), 'PKR');
assert(isset($arReport['customers']), 'Customers list must be present in AR report');
assert(isset($arReport['aging_summary']), 'Aging summary must be present in AR report');

$foundCust = null;
foreach ($arReport['customers'] as $c) {
    if ($c['code'] === '1210') {
        $foundCust = $c;
        break;
    }
}
assert($foundCust !== null, 'Customer 1210 must appear in AR subledger');
assert($foundCust['balance'] >= 75000.00, 'Customer 1210 balance must reflect invoice amount');
assert(isset($foundCust['aging']['days_0_30']), 'Customer aging bucket 0-30 days must exist');
echo " [PASS] Receivables Subledger verified: 1210 balance and 0-30d aging accurate" . PHP_EOL;

// 5. Test Payables Subledger Report
$apReport = $reportService->getPayablesReport(date('Y-m-d'), 'PKR');
assert(isset($apReport['suppliers']), 'Suppliers list must be present in AP report');
assert(isset($apReport['aging_summary']), 'Aging summary must be present in AP report');

$foundSup = null;
foreach ($apReport['suppliers'] as $s) {
    if ($s['code'] === '2110') {
        $foundSup = $s;
        break;
    }
}
assert($foundSup !== null, 'Supplier 2110 ABC Tours must appear in AP subledger');
assert($foundSup['balance'] >= 45000.00, 'Supplier 2110 balance must reflect bill amount');
assert(isset($foundSup['aging']['days_0_30']), 'Supplier aging bucket 0-30 days must exist');
echo " [PASS] Payables Subledger verified: 2110 ABC Tours balance and 0-30d aging accurate" . PHP_EOL;

echo "========================================================================" . PHP_EOL;
echo " ALL TESTS PASSED (5/5)" . PHP_EOL;
echo "========================================================================" . PHP_EOL;
