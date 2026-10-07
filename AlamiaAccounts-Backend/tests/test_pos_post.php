<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$client = new \Illuminate\Http\Request();
$salesService = app(\AlamiaSoft\AlamiaAccounts\Services\SalesIntegrationService::class);

$payload = [
    'customer_name' => 'Ali Raza',
    'customer_passport' => 'PP123456',
    'service_type' => 'Ticketing',
    'description' => 'Return Flight LHR - DXB',
    'amount' => 10000.00,
    'paid_amount' => 5000.00,
    'payment_method' => 'cash',
    'payment_account' => '1110',
    'revenue_account' => '5100',
    'agent_name' => 'Counter Desk 1',
    'idempotency_key' => 'KEH-' . time() . '-test',
];

$res = $salesService->processSale($payload, 'KAMAL_EXPRESS', '1', 'Counter Staff');
echo "Result Status: " . ($res['status'] ?? 'N/A') . "\n";
echo "Sale Number: " . ($res['sale_number'] ?? 'N/A') . "\n";
echo "Sales Voucher Ref: " . ($res['sales_voucher_ref'] ?? 'N/A') . "\n";
echo "Receipt Voucher Ref: " . ($res['receipt_voucher_ref'] ?? 'N/A') . "\n";
echo "Gross: " . ($res['net_amount'] ?? 'N/A') . " | Paid: " . ($res['paid_amount'] ?? 'N/A') . " | Due: " . ($res['balance_due'] ?? 'N/A') . "\n";

// Verify Voucher in VoucherService
\AlamiaSoft\AlamiaAccounts\Services\DomainContext::set('KAMAL_EXPRESS');
$voucherService = app(\AlamiaSoft\AlamiaAccounts\Services\VoucherService::class);
$entries = $voucherService->getJournalEntries();
echo "\nTotal Journal Entries in KAMAL_EXPRESS company: " . $entries->count() . "\n";
foreach ($entries as $e) {
    echo "- Ref: " . ($e['reference'] ?? 'N/A') . " | " . ($e['description'] ?? '') . " | Lines: " . count($e['entries'] ?? []) . "\n";
}
