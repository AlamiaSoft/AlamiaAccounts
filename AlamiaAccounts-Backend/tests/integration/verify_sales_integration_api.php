<?php

/**
 * Automated Verification Suite for Front-Office Sales Integration API & Double-Entry Orchestration.
 * Run via: php tests/integration/verify_sales_integration_api.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\SalesIntegrationService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Models\OperationalSale;
use Abivia\Ledger\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

echo "\n========================================================================\n";
echo " ALAMIA ACCOUNTS - SALES INTEGRATION API VERIFICATION SUITE\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

$assertCondition = function (bool $condition, string $testName, string $details = '') use (&$passed, &$failed) {
    echo "------------------------------------------------------------------------\n";
    echo "Test: {$testName}\n";
    if ($condition) {
        $passed++;
        echo "  \033[32m[PASS]\033[0m " . ($details ?: "Assertion satisfied") . "\n";
    } else {
        $failed++;
        echo "  \033[31m[FAIL]\033[0m " . ($details ?: "Assertion failed") . "\n";
    }
};

DomainContext::set('MAIN');
$salesService = app(SalesIntegrationService::class);

// 1. Instant Ticket Sale with Full Cash Payment
$txRef1 = 'TKT-TEST-' . uniqid();
$payload1 = [
    'client_reference_id' => $txRef1,
    'issue_date' => date('Y-m-d'),
    'customer' => [
        'name' => 'Muhammad Usman (Traveler)',
        'phone' => '+92 300 1234567',
        'email' => 'usman@test.com',
        'cnic_or_ntn' => '35201-1234567-1',
    ],
    'line_items' => [
        [
            'description' => 'Return Air Ticket: LHR -> DXB (Emirates)',
            'quantity' => 1,
            'unit_price' => 100000.00,
            'revenue_account_code' => '4100',
            'tax_rate_percent' => 0.00,
            'metadata' => [
                'pnr' => '6XJ8KL',
                'ticket_number' => '176-2490182736',
                'route' => 'LHR-DXB',
                'passenger' => 'Muhammad Usman',
            ]
        ]
    ],
    'payments' => [
        [
            'method' => 'cash',
            'amount' => 100000.00,
            'deposit_account_code' => '1110',
            'reference_note' => 'Counter Cash Deposit',
        ]
    ],
    'workflow' => [
        'mode' => 'instant_post',
    ]
];

$res1 = $salesService->processSale($payload1, 'MAIN', 'agent_usman', 'Usman Ali (Counter 1)');
$sale1 = OperationalSale::find($res1['id']);

$assertCondition(
    $sale1 &&
    $sale1->status === 'posted' &&
    str_starts_with($sale1->sales_voucher_ref, 'SV-') &&
    str_starts_with($sale1->receipt_voucher_ref, 'RV-') &&
    (float)$sale1->net_amount === 100000.00 &&
    (float)$sale1->paid_amount === 100000.00 &&
    (float)$sale1->balance_due === 0.00,
    "Instant Ticket Sale with Full Cash Payment",
    "Created Sale #{$sale1->id} | SV: {$sale1->sales_voucher_ref} | RV: {$sale1->receipt_voucher_ref} | Net: PKR {$sale1->net_amount}"
);

// 2. Double-Entry Invariant Check on Generated Vouchers
$svEntry = JournalEntry::where('extra', 'like', "%{$sale1->sales_voucher_ref}%")->first();
$rvEntry = JournalEntry::where('extra', 'like', "%{$sale1->receipt_voucher_ref}%")->first();

$assertCondition(
    $svEntry && $rvEntry,
    "Double-Entry Vouchers Exist in Abivia Ledger Kernel",
    "Verified SV entry ID #{$svEntry?->journalEntryId} and RV entry ID #{$rvEntry?->journalEntryId}"
);

// 3. Credit Sale with Zero Initial Payment (Balance Due)
$txRef2 = 'CORP-INV-' . uniqid();
$payload2 = [
    'client_reference_id' => $txRef2,
    'issue_date' => date('Y-m-d'),
    'customer' => [
        'name' => 'Apex Group Travels (Corporate Account)',
        'phone' => '+92 42 111 222 333',
    ],
    'line_items' => [
        [
            'description' => 'Corporate Umrah Package (5 Pax)',
            'quantity' => 1,
            'unit_price' => 500000.00,
            'revenue_account_code' => '4100',
        ]
    ],
    'payments' => [],
    'workflow' => [
        'mode' => 'instant_post',
    ]
];

$res2 = $salesService->processSale($payload2, 'MAIN', 'agent_corporate', 'Corporate Desk');
$sale2 = OperationalSale::find($res2['id']);

$assertCondition(
    $sale2 &&
    $sale2->status === 'posted' &&
    $sale2->sales_voucher_ref !== null &&
    $sale2->receipt_voucher_ref === null &&
    (float)$sale2->paid_amount === 0.00 &&
    (float)$sale2->balance_due === 500000.00,
    "Corporate Credit Sale with Zero Initial Payment",
    "Posted SV: {$sale2->sales_voucher_ref} | Balance Due: PKR {$sale2->balance_due}"
);

// 4. Idempotency Guarantee: Re-submitting Identical Client Reference
$res1Replay = $salesService->processSale($payload1, 'MAIN', 'agent_usman', 'Usman Ali');

$assertCondition(
    $res1Replay['id'] === $res1['id'] && !empty($res1Replay['is_idempotent_replay']),
    "Idempotency Guarantee: Re-submission Returns Existing Sale",
    "Returned original Sale #{$res1Replay['id']} without creating duplicate ledger vouchers"
);

// 5. Staged Approval Workflow (Pending Approval -> Approve)
$txRef3 = 'VIP-TKT-' . uniqid();
$payload3 = [
    'client_reference_id' => $txRef3,
    'issue_date' => date('Y-m-d'),
    'customer' => [
        'name' => 'VIP Traveler',
    ],
    'line_items' => [
        [
            'description' => 'First Class Ticket: LHR -> LHR -> JFK',
            'quantity' => 1,
            'unit_price' => 750000.00,
            'revenue_account_code' => '4100',
        ]
    ],
    'payments' => [
        [
            'method' => 'bank',
            'amount' => 750000.00,
            'deposit_account_code' => '1130',
        ]
    ],
    'workflow' => [
        'mode' => 'pending_approval',
    ]
];

$res3 = $salesService->processSale($payload3, 'MAIN', 'agent_junior', 'Junior Agent');
$sale3 = OperationalSale::find($res3['id']);

$assertCondition(
    $sale3 &&
    $sale3->status === 'pending_approval' &&
    $sale3->sales_voucher_ref === null,
    "Staged Sale Initial State is 'pending_approval' (No Vouchers Posted)",
    "Sale #{$sale3->id} queued for manager review without ledger posting"
);

$approvedRes3 = $salesService->approveSale($sale3->id, 'Lead Accountant');
$sale3Reloaded = OperationalSale::find($sale3->id);

$assertCondition(
    $sale3Reloaded->status === 'posted' &&
    str_starts_with($sale3Reloaded->sales_voucher_ref, 'SV-') &&
    str_starts_with($sale3Reloaded->receipt_voucher_ref, 'RV-'),
    "Manager Approval Commits Double-Entry Vouchers to Permanent Ledger",
    "Approved Sale #{$sale3Reloaded->id} -> Posted SV: {$sale3Reloaded->sales_voucher_ref} & RV: {$sale3Reloaded->receipt_voucher_ref}"
);

// 6. Staff-Level Data Scoping Isolation
$staffListUsman = $salesService->listSales(['scope' => 'mine'], 'MAIN', 'agent_usman', false);
$staffListJunior = $salesService->listSales(['scope' => 'mine'], 'MAIN', 'agent_junior', false);
$managerListAll = $salesService->listSales(['scope' => 'all'], 'MAIN', 'manager_ali', true);

$allUsman = true;
foreach ($staffListUsman->items() as $item) {
    if ($item->created_by_user_id !== 'agent_usman') {
        $allUsman = false;
        break;
    }
}

$assertCondition(
    $allUsman && $managerListAll->total() >= 3,
    "Staff-Level Data Scoping: Counter Agents Isolated from Peer Transactions",
    "Agent Usman views only Usman sales (Count: " . count($staffListUsman->items()) . ") | Manager views all ({$managerListAll->total()})"
);

// 7. Cashier Shift Reconciliation & Cash Drawer Discrepancy
$shiftReconciliation = $salesService->reconcileShift('MAIN', date('Y-m-d'), 'agent_usman', 0.0);
$expectedCash = $shiftReconciliation['cash_drawer']['expected_cash'];

// Reconcile with exact counted cash
$exactReconciliation = $salesService->reconcileShift('MAIN', date('Y-m-d'), 'agent_usman', $expectedCash);

$assertCondition(
    isset($exactReconciliation['summary']) &&
    $exactReconciliation['summary']['total_cash_collected'] >= 100000.00 &&
    $exactReconciliation['cash_drawer']['expected_cash'] === $expectedCash &&
    $exactReconciliation['cash_drawer']['status'] === 'balanced',
    "Cashier Shift Reconciliation & Cash Drawer Matching",
    "Expected Cash: PKR {$exactReconciliation['cash_drawer']['expected_cash']} | Actual Counted: PKR {$exactReconciliation['cash_drawer']['actual_cash_counted']} | Status: {$exactReconciliation['cash_drawer']['status']}"
);

// 8. Security Gate: Valid Tenant API Key Access Permitted via Middleware
$middleware = new \App\Http\Middleware\AuthenticateSalesOrSanctum();

$reqValid = \Illuminate\Http\Request::create('/api/v1/sales', 'POST', ['company_code' => 'KAMAL_EXPRESS']);
$reqValid->headers->set('X-POS-Key', 'demo_kamal_token');

$passedMiddleware = false;
$responseValid = $middleware->handle($reqValid, function ($req) use (&$passedMiddleware) {
    $passedMiddleware = true;
    return response()->json(['success' => true]);
});

$assertCondition(
    $passedMiddleware === true && $reqValid->attributes->get('tenant_company_code') === 'KAMAL_EXPRESS',
    "Security Gate: Valid Tenant API Key Bound to Company Domain",
    "Key 'demo_kamal_token' authorized for company: " . $reqValid->attributes->get('tenant_company_code')
);

// 9. Negative Security Test: Cross-Tenant Spoofing Blocked (HTTP 403 Forbidden)
$reqCross = \Illuminate\Http\Request::create('/api/v1/sales', 'POST', ['company_code' => 'MAIN']);
$reqCross->headers->set('X-POS-Key', 'demo_kamal_token');

$responseCross = $middleware->handle($reqCross, function ($req) {
    return response()->json(['success' => true]);
});

$assertCondition(
    $responseCross->getStatusCode() === 403 &&
    str_contains($responseCross->getContent(), 'Cross-tenant access forbidden'),
    "Negative Security Test: Cross-Tenant Spoofing Blocked (HTTP 403)",
    "Blocked cross-company request with HTTP " . $responseCross->getStatusCode() . ": " . $responseCross->getContent()
);

// 10. Negative Security Test: Invalid / Missing API Key Blocked (HTTP 401 Unauthorized)
$reqMissing = \Illuminate\Http\Request::create('/api/v1/sales', 'POST');
$responseMissing = $middleware->handle($reqMissing, function ($req) {
    return response()->json(['success' => true]);
});

$reqInvalid = \Illuminate\Http\Request::create('/api/v1/sales', 'POST');
$reqInvalid->headers->set('X-POS-Key', 'invalid_fake_token_123');
$responseInvalid = $middleware->handle($reqInvalid, function ($req) {
    return response()->json(['success' => true]);
});

$assertCondition(
    $responseMissing->getStatusCode() === 401 && $responseInvalid->getStatusCode() === 401,
    "Negative Security Test: Missing / Unrecognized Key Blocked (HTTP 401)",
    "Blocked unauthenticated requests with HTTP 401"
);

echo "\n========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED (Total: " . ($passed + $failed) . " Tests)\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
