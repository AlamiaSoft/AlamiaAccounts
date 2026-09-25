<?php

/**
 * Alamia Accounts - Copilot Intent & Entity Resolution Verification Suite
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
echo " ALAMIA ACCOUNTS - COPILOT INTENT & ENTITY RESOLUTION TEST SUITE\n";
echo "========================================================================\n\n";

$tests = [
    [
        'title' => 'Account Balance Query (Natural Language)',
        'query' => 'What is the balance of Meezan Bank?',
        'expected_intent' => 'entity_account_brief',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1130' && ($res['data']['balance'] ?? 0) > 0,
    ],
    [
        'title' => 'Account Balance Query (Direct Balance)',
        'query' => 'Balance of Meezan Bank',
        'expected_intent' => 'entity_account_brief',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1130',
    ],
    [
        'title' => 'Account Lookup by 4-digit Code',
        'query' => 'Account 1130',
        'expected_intent' => 'entity_account_brief',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1130',
    ],
    [
        'title' => 'Account Lookup by Name (Cash in Hand)',
        'query' => 'Cash in Hand',
        'expected_intent' => 'entity_account_brief',
        'expected_card' => 'account_brief',
        'validate' => fn($res) => ($res['data']['code'] ?? '') === '1110',
    ],
    [
        'title' => 'Voucher Inquiry by Reference',
        'query' => 'Tell me about voucher OB-2026-001',
        'expected_intent' => 'entity_voucher_brief',
        'expected_card' => 'voucher_brief',
        'validate' => fn($res) => ($res['data']['reference'] ?? '') === 'OB-2026-001' && ($res['data']['is_balanced'] ?? false) === true,
    ],
    [
        'title' => 'Voucher Drafting with Amount & Accounts',
        'query' => 'Paid Rs. 25,000 for office supplies via Meezan Bank',
        'expected_intent' => 'draft_voucher',
        'expected_card' => 'voucher_draft',
        'validate' => fn($res) => !empty($res['data']['voucher']['details']),
    ],
    [
        'title' => 'Unknown Party Contact (Zero False Account Matches)',
        'query' => 'Ali Raza',
        'expected_intent' => 'entity_not_found',
        'expected_card' => 'not_found',
        'validate' => fn($res) => ($res['card_type'] ?? '') === 'not_found',
    ],
];

$passed = 0;
$failed = 0;

foreach ($tests as $idx => $t) {
    $num = $idx + 1;
    echo "Test {$num}: {$t['title']}\n";
    echo "  Prompt: \"{$t['query']}\"\n";

    $res = $copilot->handleChat($t['query'], 'ALAMIASOFT', []);
    $card = $res['card_type'] ?? 'N/A';
    $intent = $res['intent'] ?? 'N/A';
    $isValid = ($card === $t['expected_card']) && ($t['validate']($res));

    if ($isValid) {
        echo "  [PASS] Card: {$card} | Intent: {$intent}\n";
        $passed++;
    } else {
        echo "  [FAIL] Expected Card: {$t['expected_card']} | Got Card: {$card} | Intent: {$intent}\n";
        echo "  Response: " . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $failed++;
    }
    echo "------------------------------------------------------------------------\n";
}

// Multi-turn Conversational Correction Test
echo "Test 8: Multi-Turn Conversational Entity Resolution & Correction\n";
$history = [
    ['sender' => 'user', 'text' => 'Ali Raza', 'cardType' => null],
    ['sender' => 'taliya', 'text' => "I couldn't find any vouchers matching Ali Raza", 'cardType' => 'not_found'],
];
$turn2Query = "no; there was a transaction with Mr. Ali Raza of Izoc Ltd. i need to see its voucher";
echo "  Prompt: \"{$turn2Query}\"\n";

$res2 = $copilot->handleChat($turn2Query, 'ALAMIASOFT', ['history' => $history]);
$card2 = $res2['card_type'] ?? 'N/A';
$ref2 = $res2['data']['reference'] ?? '';

if ($card2 === 'voucher_brief' && !empty($ref2)) {
    echo "  [PASS] Resolved Voucher: {$ref2} | Card: {$card2}\n";
    $passed++;
} else {
    echo "  [FAIL] Expected voucher_brief | Got: {$card2}\n";
    echo "  Response: " . json_encode($res2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    $failed++;
}
echo "========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n\n";

exit($failed === 0 ? 0 : 1);
