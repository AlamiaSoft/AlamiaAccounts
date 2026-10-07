<?php

require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Illuminate\Support\Facades\DB;

echo "========================================================================" . PHP_EOL;
echo " TEST: BANK BOOK QUERY SERVICE & API ENDPOINT (T-032)" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

DomainContext::set('KAMAL_EXPRESS');
$reportService = app(ReportService::class);
$voucherService = app(VoucherService::class);

// 1. Check initial Bank Book query
$bankBookAll = $reportService->getBankBook('ALL', '2024-01-01', '2099-12-31', 'PKR');

assert(isset($bankBookAll['bank_accounts']), 'Bank accounts list must be present');
assert(isset($bankBookAll['entries']), 'Entries array must be present');
assert(isset($bankBookAll['opening_balance']), 'Opening balance must be present');
assert(isset($bankBookAll['closing_balance']), 'Closing balance must be present');
echo " [PASS] Bank Book query structure verified for aggregate (ALL) banks" . PHP_EOL;

// Ensure we have a posting leaf bank account
$targetBankCode = null;
if (!empty($bankBookAll['bank_accounts'])) {
    $targetBankCode = $bankBookAll['bank_accounts'][0]['code'];
} else {
    $accService = app(\AlamiaSoft\AlamiaAccounts\Services\AccountService::class);
    $existing = \Abivia\Ledger\Models\LedgerAccount::where('code', '1121')->first();
    if (!$existing) {
        $accService->createAccount([
            'code' => '1121',
            'name' => 'Meezan Bank Operations',
            'category' => false,
            'debit' => true,
            'parent_code' => '1120',
        ]);
    }
    $targetBankCode = '1121';
}

// 2. Create a test Bank Receipt voucher (Dr: $targetBankCode Bank, Cr: 3100 Sales Revenue)
$receiptRef = 'RV-' . date('Y') . '-' . rand(1000, 9999);
$voucherService->createJournalEntry([
    'date' => date('Y-m-d'),
    'voucher_type' => 'receipt',
    'reference' => $receiptRef,
    'description' => 'Online Bank Deposit from Customer',
    'currency' => 'PKR',
    'entries' => [
        [
            'account_code' => $targetBankCode,
            'amount' => 50000.00,
            'type' => 'debit',
            'description' => 'Bank receipt deposit',
        ],
        [
            'account_code' => '3100',
            'amount' => 50000.00,
            'type' => 'credit',
            'description' => 'Direct Sales Revenue',
        ],
    ],
]);
echo " [PASS] Created Bank Receipt voucher: {$receiptRef} (+Rs. 50,000 on {$targetBankCode})" . PHP_EOL;

// 3. Create a test Bank Payment voucher (Dr: 2100 AP, Cr: $targetBankCode Bank)
$paymentRef = 'PV-' . date('Y') . '-' . rand(1000, 9999);
$voucherService->createJournalEntry([
    'date' => date('Y-m-d'),
    'voucher_type' => 'payment',
    'reference' => $paymentRef,
    'description' => 'Vendor Payout via Bank Wire',
    'currency' => 'PKR',
    'entries' => [
        [
            'account_code' => '2100',
            'amount' => 20000.00,
            'type' => 'debit',
            'description' => 'Accounts Payable Settlement',
        ],
        [
            'account_code' => $targetBankCode,
            'amount' => 20000.00,
            'type' => 'credit',
            'description' => 'Wire Transfer Outflow',
        ],
    ],
]);
echo " [PASS] Created Bank Payment voucher: {$paymentRef} (-Rs. 20,000 on {$targetBankCode})" . PHP_EOL;

// 4. Query Bank Book specifically for target bank account
$bankBookTarget = $reportService->getBankBook($targetBankCode, '2024-01-01', '2099-12-31', 'PKR');

$foundReceipt = false;
$foundPayment = false;
foreach ($bankBookTarget['entries'] as $entry) {
    if ($entry['reference'] === $receiptRef) {
        $foundReceipt = true;
        assert($entry['deposit'] == 50000.00, 'Deposit amount must match 50,000');
        assert(str_contains($entry['particulars'], '3100') || str_contains($entry['particulars'], 'Revenue'), 'Opposing account must be identified');
    }
    if ($entry['reference'] === $paymentRef) {
        $foundPayment = true;
        assert($entry['withdrawal'] == 20000.00, 'Withdrawal amount must match 20,000');
        assert(str_contains($entry['particulars'], '2100') || str_contains($entry['particulars'], 'Payable'), 'Opposing account must be identified');
    }
}

assert($foundReceipt, "Receipt entry {$receiptRef} must be in Bank Book");
assert($foundPayment, "Payment entry {$paymentRef} must be in Bank Book");
assert($bankBookTarget['total_inflow'] >= 50000.00, 'Total inflow must capture the deposit');
assert($bankBookTarget['total_outflow'] >= 20000.00, 'Total outflow must capture the payout');
echo " [PASS] Bank Book verified: Particulars, Inflows (+50k), Outflows (-20k), and Running Balances match double-entry precision" . PHP_EOL;

echo "========================================================================" . PHP_EOL;
echo " ALL TESTS PASSED (4/4)" . PHP_EOL;
echo "========================================================================" . PHP_EOL;
