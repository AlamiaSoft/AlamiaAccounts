<?php

require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Illuminate\Http\Request;

echo "========================================================================\n";
echo " TEST: CUSTOM VOUCHER PAYLOAD NORMALIZATION & SUBMISSION\n";
echo "========================================================================\n";

DomainContext::set('KAMAL_EXPRESS');

// Test 1: Simulating user's exact payload without currency and with lineItems
$controller = app(\AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\VoucherController::class);

$uniqueRef = 'TKT-2026-' . rand(1000, 9999);
$payload = [
    'number' => $uniqueRef,
    'reference' => $uniqueRef,
    'type' => 'tkt',
    'voucher_type' => 'Airline Ticket Booking',
    'company_code' => 'KAMAL_EXPRESS',
    'custom_fields' => [
        'Passenger Name' => 'Malik Jawad Tanveer',
        'Passport or CNIC' => 'PP123456',
        'PNR' => '123',
        'Airline' => 'PIA'
    ],
    'date' => '2026-10-07',
    'description' => 'Ticket Booking: Malik Jawad Tanveer (PNR: 123) Sector: LHR-DXB',
    'narration' => 'Ticket Booking: Malik Jawad Tanveer (PNR: 123) Sector: LHR-DXB',
    'lineItems' => [
        [
            'account' => '1110',
            'account_code' => '1110',
            'accountName' => 'Cash',
            'account_name' => 'Cash',
            'debit' => 100000,
            'credit' => 0,
            'description' => 'Passenger Cash Payment'
        ],
        [
            'account' => '5100',
            'account_code' => '5100',
            'accountName' => 'Ticket Revenue',
            'account_name' => 'Ticket Revenue',
            'debit' => 0,
            'credit' => 100000,
            'description' => 'Ticket Revenue'
        ]
    ]
];

$request = Request::create('/api/vouchers', 'POST', $payload);
$request->headers->set('X-Company-Code', 'KAMAL_EXPRESS');

$response = $controller->store($request);
$statusCode = $response->getStatusCode();
$responseData = json_decode($response->getContent(), true);

if ($statusCode === 201) {
    echo " [PASS] Successfully posted Airline Ticket Voucher (HTTP 201)\n";
} else {
    echo " [FAIL] Failed to post voucher (HTTP {$statusCode}): " . json_encode($responseData) . "\n";
    exit(1);
}

// Test 2: Verify custom fields and metadata are stored and retrievable in Daybook / Voucher Details
$voucherService = app(VoucherService::class);
$voucher = $voucherService->getVoucher($uniqueRef);

if ($voucher) {
    echo " [PASS] Retrieved voucher from ledger: {$voucher['reference']}\n";
    if (!empty($voucher['custom_fields']) && ($voucher['custom_fields']['Passenger Name'] ?? '') === 'Malik Jawad Tanveer') {
        echo " [PASS] Custom fields correctly preserved in voucher extra metadata\n";
    } else {
        echo " [FAIL] Custom fields missing or mismatch: " . json_encode($voucher['custom_fields'] ?? []) . "\n";
        exit(1);
    }
    if (($voucher['type'] ?? '') === 'Airline Ticket Booking' || ($voucher['custom_voucher_type'] ?? '') === 'Airline Ticket Booking') {
        echo " [PASS] Custom voucher type correctly resolved: " . ($voucher['type'] ?? '') . "\n";
    }
} else {
    echo " [FAIL] Could not retrieve posted voucher {$uniqueRef}\n";
    exit(1);
}

echo "========================================================================\n";
echo " ALL TESTS PASSED!\n";
echo "========================================================================\n";
