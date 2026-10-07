<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Copilot\CopilotService;
use App\Copilot\IntentClassifierService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

echo "========================================================================\n";
echo " TEST: COPILOT BALANCE SHEET DIAGNOSTICS CAPABILITY (T-037)\n";
echo "========================================================================\n";

$companyCode = 'KAMAL_EXPRESS';
DomainContext::set($companyCode);

$classifier = app(IntentClassifierService::class);
$copilot = app(CopilotService::class);

// 1. Test Semantic Intent Classification for various phrases
$prompts = [
    "Why is my balance sheet not balanced?",
    "What caused the difference in balance sheet?",
    "Balance sheet is not balanced, how do I fix it?",
    "Diagnose balance sheet errors",
];

foreach ($prompts as $p) {
    $res = $classifier->classify($p, ['company_code' => $companyCode]);
    if ($res['capability'] !== 'diagnostics.balance_sheet_imbalance') {
        echo "❌ FAILED: '{$p}' was classified as '{$res['capability']}', expected 'diagnostics.balance_sheet_imbalance'\n";
        exit(1);
    }
    echo " [PASS] Correctly classified prompt: \"{$p}\" -> diagnostics.balance_sheet_imbalance (Confidence: {$res['confidence']})\n";
}

// 2. Test Copilot Chat Execution
echo "\nTesting Copilot handleChat execution...\n";
$chatResponse = $copilot->handleChat("Why is my balance sheet not balanced?", $companyCode, []);

if (!isset($chatResponse['intent']) || $chatResponse['intent'] !== 'diagnose_balance_sheet') {
    echo "❌ FAILED: Unexpected intent in response: " . ($chatResponse['intent'] ?? 'null') . "\n";
    exit(1);
}

if (!isset($chatResponse['card_type']) || $chatResponse['card_type'] !== 'balance_sheet_diagnostics') {
    echo "❌ FAILED: Card type is not 'balance_sheet_diagnostics'\n";
    exit(1);
}

if (!isset($chatResponse['actions']) || count($chatResponse['actions']) === 0) {
    echo "❌ FAILED: No action buttons returned in diagnostics response\n";
    exit(1);
}

echo " [PASS] Copilot returned response: {$chatResponse['message']}\n";
echo " [PASS] Diagnostics Card Type: {$chatResponse['card_type']}\n";
echo " [PASS] Action Buttons: " . implode(', ', array_column($chatResponse['actions'], 'label')) . "\n";

echo "========================================================================\n";
echo " ALL TESTS PASSED (6/6)\n";
echo "========================================================================\n";
exit(0);
