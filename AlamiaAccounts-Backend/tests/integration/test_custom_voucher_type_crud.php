<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

echo "========================================================================\n";
echo " TEST: CUSTOM VOUCHER TYPE EDIT & CONFIGURABLE DEFAULT ACCOUNTS (T-025)\n";
echo "========================================================================\n";

$companyCode = 'KAMAL_EXPRESS';
DomainContext::set($companyCode);

$service = app(CustomVoucherTypeService::class);

// 1. Create a custom voucher type with default debit & credit accounts
echo "1. Creating custom voucher type with default posting accounts...\n";
$created = $service->createVoucherType([
    'name' => 'Automated Testing Voucher',
    'prefix' => 'ATV',
    'company_code' => $companyCode,
    'description' => 'Automated voucher for test verification',
    'default_debit_account' => '1110',
    'default_credit_account' => '3100',
    'active' => true,
    'custom_fields' => [
        [
            'name' => 'Passenger CNIC',
            'type' => 'text',
            'required' => true,
        ]
    ],
    'account_rules' => [
        [
            'side' => 'debit',
            'account_groups' => ['Cash', 'Bank Accounts'],
        ]
    ],
]);

assert(!empty($created['id']), "Created voucher type must have an ID");
assert($created['name'] === 'Automated Testing Voucher', "Name must match");
assert($created['prefix'] === 'ATV', "Prefix must match");
assert($created['default_debit_account'] === '1110', "Default debit account must be 1110");
assert($created['default_credit_account'] === '3100', "Default credit account must be 3100");
assert(count($created['custom_fields']) === 1, "Custom fields count must be 1");
echo " [PASS] Created Custom Voucher Type '{$created['name']}' (ID: {$created['id']})\n";

// 2. Retrieve the voucher type
echo "2. Fetching created voucher type...\n";
$fetched = $service->getVoucherType($created['id']);
assert($fetched['default_debit_account'] === '1110', "Fetched default debit account must match");
assert($fetched['default_credit_account'] === '3100', "Fetched default credit account must match");
echo " [PASS] Retrieved voucher type correctly with default accounts Dr: {$fetched['default_debit_account']} / Cr: {$fetched['default_credit_account']}\n";

// 3. Update the voucher type (Edit functionality)
echo "3. Updating voucher type with new default accounts and rules...\n";
$updated = $service->updateVoucherType($created['id'], [
    'name' => 'Updated Testing Voucher',
    'prefix' => 'ATV',
    'company_code' => $companyCode,
    'description' => 'Updated description for testing',
    'default_debit_account' => '1120',
    'default_credit_account' => '3200',
    'active' => true,
    'custom_fields' => [
        [
            'name' => 'Passenger CNIC',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'Ticket Number',
            'type' => 'text',
            'required' => false,
        ]
    ],
    'account_rules' => [
        [
            'side' => 'debit',
            'account_groups' => ['Bank Accounts'],
        ]
    ],
]);

assert($updated['name'] === 'Updated Testing Voucher', "Updated name must match");
assert($updated['default_debit_account'] === '1120', "Updated default debit account must be 1120");
assert($updated['default_credit_account'] === '3200', "Updated default credit account must be 3200");
assert(count($updated['custom_fields']) === 2, "Updated custom fields count must be 2");
echo " [PASS] Updated Custom Voucher Type successfully with new defaults Dr: {$updated['default_debit_account']} / Cr: {$updated['default_credit_account']}\n";

// 4. Validate data against custom voucher type
echo "4. Testing validation against updated voucher type...\n";
$validData = [
    'custom_fields' => [
        'Passenger CNIC' => '42101-1234567-1',
    ]
];
$validationErrors = $service->validateAgainstType($validData, $created['id']);
assert(empty($validationErrors), "Validation errors should be empty for valid payload");

$invalidData = [
    'custom_fields' => [] // Missing required 'Passenger CNIC'
];
$validationErrorsInvalid = $service->validateAgainstType($invalidData, $created['id']);
assert(!empty($validationErrorsInvalid), "Validation must fail when required field is missing");
echo " [PASS] Custom field validation rules verified.\n";

// 5. Delete voucher type
echo "5. Deleting custom voucher type...\n";
$service->deleteVoucherType($created['id']);

$allTypes = $service->getAllVoucherTypes($companyCode);
$ids = array_column($allTypes, 'id');
assert(!in_array($created['id'], $ids), "Deleted voucher type must not be in list");
echo " [PASS] Custom Voucher Type deleted cleanly.\n";

echo "========================================================================\n";
echo " 🎉 ALL T-025 CUSTOM VOUCHER TYPE TESTS PASSED 100%!\n";
echo "========================================================================\n";
exit(0);
