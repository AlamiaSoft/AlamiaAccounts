<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;

echo "========================================================================\n";
echo " AUTOMATED VERIFICATION: CUSTOM VOUCHER SUBSYSTEM & KAMAL EXPRESS\n";
echo "========================================================================\n\n";

$service = app(CustomVoucherTypeService::class);
$passed = 0;
$total = 0;

function assertCondition($desc, $cond) {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] $desc\n";
    } else {
        echo " [FAIL] $desc\n";
    }
}

// 1. Verify Kamal Express Airline Ticket Booking Voucher is seeded
$kamalTypes = $service->getAllVoucherTypes('KAMAL_EXPRESS');
$tkt = null;
foreach ($kamalTypes as $vt) {
    if ($vt['prefix'] === 'TKT') {
        $tkt = $vt;
        break;
    }
}

assertCondition("Kamal Express has Airline Ticket Booking voucher seeded", $tkt !== null);
assertCondition("Airline Ticket Booking voucher has prefix TKT", ($tkt['prefix'] ?? '') === 'TKT');
assertCondition("Airline Ticket Booking belongs to KAMAL_EXPRESS", ($tkt['company_code'] ?? '') === 'KAMAL_EXPRESS');

// Check custom fields
$fields = array_column($tkt['custom_fields'] ?? [], 'name');
assertCondition("Has Passenger Name custom field", in_array('Passenger Name', $fields));
assertCondition("Has PNR custom field", in_array('PNR', $fields));
assertCondition("Has Airline dropdown custom field", in_array('Airline', $fields));
assertCondition("Has Gross Fare custom field", in_array('Gross Fare', $fields));

// Check numbering scheme
$ns = $tkt['numbering_scheme'] ?? null;
assertCondition("Has numbering scheme configured", $ns !== null && ($ns['prefix'] ?? '') === 'TKT');

// 2. Verify Tenancy Filter: Other companies do not get KAMAL_EXPRESS specific voucher
$otherTypes = $service->getAllVoucherTypes('OTHER_CORP');
$otherTkt = null;
foreach ($otherTypes as $vt) {
    if ($vt['prefix'] === 'TKT') {
        $otherTkt = $vt;
        break;
    }
}
assertCondition("Tenant isolation: OTHER_CORP does not receive KAMAL_EXPRESS specific voucher", $otherTkt === null);

// 3. Test creating a new custom voucher type (e.g. Visual Builder persistence)
$testPrefix = 'C' . substr(strtoupper(uniqid()), -3);
DB::table('custom_voucher_types')->where('prefix', 'like', 'C%')->delete();
$newType = $service->createVoucherType([
    'name' => 'Cargo Shipment Voucher',
    'prefix' => $testPrefix,
    'company_code' => 'KAMAL_EXPRESS',
    'description' => 'Kamal Express Cargo Booking & Dispatch Voucher',
    'active' => true,
    'custom_fields' => [
        [
            'name' => 'Consignee Name',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'Weight KG',
            'type' => 'number',
            'required' => true,
        ],
        [
            'name' => 'Destination City',
            'type' => 'dropdown',
            'required' => true,
            'options' => ['Karachi', 'Lahore', 'Islamabad', 'Peshawar', 'Quetta', 'Dubai', 'Jeddah'],
        ]
    ],
    'account_rules' => [
        [
            'side' => 'debit',
            'account_groups' => ['Cash', 'Accounts Receivable'],
        ],
        [
            'side' => 'credit',
            'account_groups' => ['Revenue', 'Fee Income'],
        ],
    ],
    'validation_rules' => [
        [
            'field_name' => 'Consignee Name',
            'type' => 'required',
            'message' => 'Consignee Name is mandatory',
        ],
    ],
    'numbering_scheme' => [
        'starting_number' => 1,
        'padding' => 4,
        'separator' => '-',
        'include_year' => true,
        'reset_period' => 'yearly',
    ],
]);

assertCondition("Visual Builder persistence: Successfully created Cargo Shipment Voucher", $newType['id'] > 0);
assertCondition("Saved voucher has correct custom fields", count($newType['custom_fields']) === 3);

// 4. Test validation engine against custom voucher
$validData = [
    'Consignee Name' => 'Ali Ahmed',
    'Weight KG' => 25,
    'Destination City' => 'Jeddah',
];
$errors = $service->validateAgainstType($validData, $newType['id']);
assertCondition("Validation engine passes valid payload", count($errors) === 0);

$invalidData = [
    'Consignee Name' => '',
    'Weight KG' => 25,
];
$errorsInvalid = $service->validateAgainstType($invalidData, $newType['id']);
assertCondition("Validation engine catches missing required fields", count($errorsInvalid) > 0);

// Cleanup test voucher
$service->deleteVoucherType($newType['id']);
assertCondition("Successfully cleaned up test voucher type", true);

echo "\n========================================================================\n";
echo " RESULTS: $passed / $total PASSED\n";
echo "========================================================================\n";

if ($passed === $total) {
    exit(0);
} else {
    exit(1);
}
