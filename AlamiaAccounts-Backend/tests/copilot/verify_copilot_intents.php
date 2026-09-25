<?php

/**
 * Alamia Accounts - Copilot Behavioral Contract & Accounting Semantics Test Suite
 *
 * Comprehensive contract verification covering:
 *  1. Account Resolution & Balances
 *  2. Ambiguous Account Disambiguation
 *  3. Voucher Inquiries & Lowercase References
 *  4. Financial Statement Generation
 *  5. Double-Entry Safe Drafting (Payments & Transfers)
 *  6. Contact & Organization Relationship Search
 *  7. Multi-Turn Conversational Corrections
 *  8. Safety Boundaries & Hallucination Resistance ("Don't Do This")
 *
 * Run from host:
 *   docker exec alamia-accounts-backend php tests/copilot/verify_copilot_intents.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$classifier = app(App\Copilot\IntentClassifierService::class);
$copilot = app(App\Copilot\CopilotService::class);

echo "\n========================================================================\n";
echo " ALAMIA ACCOUNTS - COPILOT BEHAVIORAL CONTRACT TEST SUITE\n";
echo "========================================================================\n\n";

$tests = [
    // ------------------------------------------------------------------------
    // Category 1: Account Resolution & Balances
    // ------------------------------------------------------------------------
    [
        'category' => 'Account Resolution',
        'title' => 'Natural Language Balance Query',
        'query' => 'What is the balance of Meezan Bank?',
        'expected_card' => 'account_brief',
        'validate' => function ($res) {
            $data = $res['data'] ?? [];
            return ($data['code'] ?? '') === '1130' &&
                   stripos($data['name'] ?? '', 'Meezan') !== false &&
                   ($data['balance'] ?? 0) > 0;
        },
    ],
    [
        'category' => 'Account Resolution',
        'title' => 'Direct Phrase Balance Query',
        'query' => 'Balance of Meezan Bank',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1130',
    ],
    [
        'category' => 'Account Resolution',
        'title' => 'Direct 4-digit Account Code Lookup',
        'query' => 'Account 1130',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1130',
    ],
    [
        'category' => 'Account Resolution',
        'title' => 'Direct Account Name Lookup',
        'query' => 'Cash in Hand',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1110',
    ],
    [
        'category' => 'Ambiguity Handling',
        'title' => 'Ambiguous Account Query (Disambiguate, Never Guess Arbitrarily)',
        'query' => 'Bank',
        'expected_card' => 'disambiguation',
        'validate' => function ($res) {
            $options = $res['data']['options'] ?? [];
            return count($options) >= 2;
        },
    ],
    [
        'category' => 'Safety & Boundaries',
        'title' => 'Non-Existent Account Code (Not Found, Zero Hallucinations)',
        'query' => 'Account 9999',
        'expected_card' => 'not_found',
        'validate' => fn($res) => ($res['card_type'] ?? '') === 'not_found',
    ],

    // ------------------------------------------------------------------------
    // Category 2: Voucher Inquiries & Audit Semantics
    // ------------------------------------------------------------------------
    [
        'category' => 'Voucher Inquiries',
        'title' => 'Exact Reference Voucher Inquiry',
        'query' => 'Tell me about voucher OB-2026-001',
        'expected_card' => 'voucher_brief',
        'validate' => function ($res) {
            $data = $res['data'] ?? [];
            return ($data['reference'] ?? '') === 'OB-2026-001' &&
                   ($data['is_balanced'] ?? false) === true &&
                   ($data['total_debit'] ?? 0) > 0;
        },
    ],
    [
        'category' => 'Voucher Inquiries',
        'title' => 'Lowercase Reference Query',
        'query' => 'show sv-2026-112',
        'expected_card' => 'voucher_brief',
        'validate' => fn($res) => stripos($res['data']['reference'] ?? '', 'SV-2026-112') !== false,
    ],
    [
        'category' => 'Safety & Boundaries',
        'title' => 'Non-Existent Voucher Reference (Not Found, Do Not Fabricate)',
        'query' => 'Tell me about voucher JV-9999-999',
        'expected_card' => 'not_found',
        'validate' => fn($res) => ($res['card_type'] ?? '') === 'not_found',
    ],

    // ------------------------------------------------------------------------
    // Category 3: Financial Statements & Reports
    // ------------------------------------------------------------------------
    [
        'category' => 'Financial Reports',
        'title' => 'Trial Balance Inquiry',
        'query' => 'Show Trial Balance summary',
        'expected_card' => 'financial_report',
        'validate' => function ($res) {
            $data = $res['data'] ?? [];
            return ($data['type'] ?? '') === 'trial-balance' &&
                   ($data['total_debit'] ?? 0) > 0 &&
                   ($data['is_balanced'] ?? false) === true;
        },
    ],

    // ------------------------------------------------------------------------
    // Category 4: Double-Entry Safe Drafting
    // ------------------------------------------------------------------------
    [
        'category' => 'Voucher Drafting',
        'title' => 'Payment Voucher Drafting (Dr Expense, Cr Bank)',
        'query' => 'Paid Rs. 25,000 for office supplies via Meezan Bank',
        'expected_card' => 'voucher_draft',
        'validate' => function ($res) {
            $details = $res['data']['voucher']['details'] ?? [];
            return count($details) >= 2 &&
                   (($details[0]['debit'] ?? 0) == 25000 || ($details[1]['debit'] ?? 0) == 25000);
        },
    ],
    [
        'category' => 'Voucher Drafting',
        'title' => 'Inter-Account Transfer Drafting (Dr Cash, Cr Bank)',
        'query' => 'Transfer Rs. 15,000 from Meezan Bank to Cash in Hand',
        'expected_card' => 'voucher_draft',
        'validate' => function ($res) {
            $details = $res['data']['voucher']['details'] ?? [];
            return count($details) >= 2 &&
                   (($details[0]['debit'] ?? 0) == 15000 || ($details[1]['debit'] ?? 0) == 15000);
        },
    ],

    // ------------------------------------------------------------------------
    // Category 5: Contact, Organization & Relationship Transaction Search
    // ------------------------------------------------------------------------
    [
        'category' => 'Party & Organization',
        'title' => 'Unknown Contact Safety (Never map to unrelated account)',
        'query' => 'Ali Raza',
        'expected_card' => 'not_found',
        'validate' => fn($res) => ($res['card_type'] ?? '') === 'not_found',
    ],
    [
        'category' => 'Party & Organization',
        'title' => 'Organization Transaction Search',
        'query' => 'Show transactions with IZOC Ltd',
        'expected_card' => 'voucher_brief',
        'validate' => fn($res) => stripos($res['data']['reference'] ?? '', 'SV-2026-112') !== false,
    ],
];

$passed = 0;
$failed = 0;

foreach ($tests as $idx => $t) {
    $num = $idx + 1;
    echo "Test {$num} [{$t['category']}]: {$t['title']}\n";
    echo "  Prompt: \"{$t['query']}\"\n";

    $res = $copilot->handleChat($t['query'], 'ALAMIASOFT', []);
    $card = $res['card_type'] ?? 'N/A';
    $isValid = ($card === $t['expected_card']) && ($t['validate']($res));

    if ($isValid) {
        echo "  [PASS] Card: {$card}\n";
        $passed++;
    } else {
        echo "  [FAIL] Expected Card: {$t['expected_card']} | Got Card: {$card}\n";
        echo "  Payload: " . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $failed++;
    }
    echo "------------------------------------------------------------------------\n";
}

// ------------------------------------------------------------------------
// Test 15: Multi-Turn Conversational Entity Resolution & Correction
// ------------------------------------------------------------------------
echo "Test 15 [Multi-Turn Conversation]: Conversational Entity Resolution & Correction\n";
$history = [
    ['sender' => 'user', 'text' => 'Ali Raza', 'cardType' => null],
    ['sender' => 'taliya', 'text' => "I couldn't find any transactions matching Ali Raza", 'cardType' => 'not_found'],
];
$turn2Query = "no; there was a transaction with Mr. Ali Raza of Izoc Ltd. i need to see its voucher";
echo "  Prompt: \"{$turn2Query}\"\n";

$res2 = $copilot->handleChat($turn2Query, 'ALAMIASOFT', ['history' => $history]);
$card2 = $res2['card_type'] ?? 'N/A';
$ref2 = $res2['data']['reference'] ?? '';

if ($card2 === 'voucher_brief' && stripos($ref2, 'SV-2026-112') !== false) {
    echo "  [PASS] Resolved Voucher: {$ref2} | Card: {$card2}\n";
    $passed++;
} else {
    echo "  [FAIL] Expected voucher_brief with SV-2026-112 | Got: {$card2}\n";
    echo "  Payload: " . json_encode($res2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    $failed++;
}

echo "========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED (Total: 15 Tests)\n";
echo "========================================================================\n\n";

exit($failed === 0 ? 0 : 1);
