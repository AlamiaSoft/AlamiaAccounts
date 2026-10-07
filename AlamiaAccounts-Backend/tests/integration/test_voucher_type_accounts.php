<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;

echo "========================================================================\n";
echo " TEST: CUSTOM VOUCHER DEFAULT POSTING ACCOUNTS & EDIT LIFECYCLE\n";
echo "========================================================================\n";

$service = app(CustomVoucherTypeService::class);

// 1. Verify Kamal Express seeded TKT default accounts
$types = $service->getAllVoucherTypes('KAMAL_EXPRESS');
$kamal = null;
foreach ($types as $t) {
    if ($t['prefix'] === 'TKT') {
        $kamal = $t;
        break;
    }
}

if (!$kamal || $kamal['default_debit_account'] !== '1110' || $kamal['default_credit_account'] !== '3100') {
    echo "[FAIL] TKT default accounts mismatch: Dr={$kamal['default_debit_account']}, Cr={$kamal['default_credit_account']}\n";
    exit(1);
}
echo " [PASS] TKT default accounts correctly configured (Dr: 1110 Cash, Cr: 3100 Sales Revenue)\n";

// 2. Test creating an Installment Received voucher type with Cr: 1200 (Accounts Receivable)
$inst = $service->createVoucherType([
    'name' => 'Installment Received',
    'prefix' => 'INST',
    'company_code' => 'KAMAL_EXPRESS',
    'description' => 'Customer installment payment',
    'default_debit_account' => '1110',
    'default_credit_account' => '1200',
    'custom_fields' => [['name' => 'Customer Name', 'type' => 'text', 'required' => true]],
    'numbering_scheme' => ['starting_number' => 1, 'padding' => 4, 'separator' => '-', 'include_year' => true, 'reset_period' => 'yearly']
]);

if ($inst['default_debit_account'] !== '1110' || $inst['default_credit_account'] !== '1200') {
    echo "[FAIL] Created voucher default accounts mismatch\n";
    exit(1);
}
echo " [PASS] Created Installment Received voucher with Dr: 1110, Cr: 1200\n";

// 3. Test updating voucher type posting accounts via Edit
$updated = $service->updateVoucherType($inst['id'], [
    'name' => 'Installment Collection (Bank)',
    'prefix' => 'INST',
    'company_code' => 'KAMAL_EXPRESS',
    'default_debit_account' => '1120',
    'default_credit_account' => '1200',
]);

if ($updated['default_debit_account'] !== '1120' || $updated['default_credit_account'] !== '1200' || $updated['name'] !== 'Installment Collection (Bank)') {
    echo "[FAIL] Updated voucher default accounts mismatch\n";
    exit(1);
}
echo " [PASS] Successfully updated voucher type to Dr: 1120 (Bank) / Cr: 1200 (Receivable)\n";

// 4. Clean up
$service->deleteVoucherType($inst['id']);
echo " [PASS] Successfully cleaned up test voucher type\n";

echo "========================================================================\n";
echo " ALL TESTS PASSED (4/4)\n";
echo "========================================================================\n";
