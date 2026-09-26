<?php

/**
 * Alamia Accounts - Copilot Diagnostics, Developer Observability, & Knowledgebase Self-Learning Suite
 */

$vendorAutoload = file_exists(__DIR__ . '/../../vendor/autoload.php')
    ? __DIR__ . '/../../vendor/autoload.php'
    : (file_exists(__DIR__ . '/../vendor/autoload.php')
        ? __DIR__ . '/../vendor/autoload.php'
        : __DIR__ . '/../../AlamiaAccounts-Backend/vendor/autoload.php');

require $vendorAutoload;

$bootstrapApp = file_exists(__DIR__ . '/../../bootstrap/app.php')
    ? __DIR__ . '/../../bootstrap/app.php'
    : (file_exists(__DIR__ . '/../bootstrap/app.php')
        ? __DIR__ . '/../bootstrap/app.php'
        : __DIR__ . '/../../AlamiaAccounts-Backend/bootstrap/app.php');

$app = require_once $bootstrapApp;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$diagnostics = app(App\Copilot\CopilotDiagnosticsService::class);
$copilot = app(App\Copilot\CopilotService::class);
$guidance = app(App\Copilot\GuidanceKnowledgeService::class);

echo "\n========================================================================\n";
echo " ALAMIA ACCOUNTS - COPILOT DIAGNOSTICS & SELF-LEARNING TEST SUITE\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

$assertCondition = function (bool $cond, string $title, string $details = '') use (&$passed, &$failed) {
    echo "------------------------------------------------------------------------\n";
    echo "Test: {$title}\n";
    if ($cond) {
        echo "  [PASS] {$details}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$details}\n";
        $failed++;
    }
};

// 1. Telemetry Capture on handleChat()
$initialCount = \AlamiaSoft\AlamiaAccounts\Models\CopilotDiagnosticLog::count();
$resp = $copilot->handleChat("Show Trial Balance summary", "MAIN", []);
$afterCount = \AlamiaSoft\AlamiaAccounts\Models\CopilotDiagnosticLog::count();

$assertCondition(
    $afterCount === $initialCount + 1,
    "Automatic Telemetry Capture on handleChat()",
    "Trace logged to database (Previous: {$initialCount}, Current: {$afterCount})"
);

// 2. Telemetry Details Inspection
$latest = \AlamiaSoft\AlamiaAccounts\Models\CopilotDiagnosticLog::latest('id')->first();
$assertCondition(
    $latest && $latest->prompt === "Show Trial Balance summary" && $latest->dispatched_action === "report.trial_balance" && $latest->duration_ms >= 0,
    "Diagnostic Record Structure & Payload Integrity",
    "Prompt: '{$latest->prompt}' | Action: '{$latest->dispatched_action}' | Latency: {$latest->duration_ms}ms"
);

// 3. Developer Review & Feedback Mutation
$updated = $diagnostics->updateFeedback($latest->id, 'verified', 'Verified Trial Balance generation');
$assertCondition(
    $updated->status === 'verified' && $updated->developer_notes === 'Verified Trial Balance generation',
    "Developer Feedback & Audit Annotation",
    "Status: {$updated->status} | Notes: '{$updated->developer_notes}'"
);

// 4. JSONL Export for Developer Agents
$jsonl = $diagnostics->exportTraces([], 'jsonl');
$lines = array_filter(explode("\n", trim($jsonl)));
$firstLine = json_decode($lines[0] ?? '{}', true);

$assertCondition(
    count($lines) > 0 && isset($firstLine['user_prompt']) && isset($firstLine['classifier']) && isset($firstLine['final_output']),
    "JSONL Telemetry Export Format for Agent Training & Evals",
    "Exported " . count($lines) . " traces formatted with prompt, classifier, and final_output"
);

// 5. Self-Learning Knowledgebase Promotion
$kbEntry = $diagnostics->promoteToKnowledge($latest->id, [
    'company_code' => 'MAIN',
    'topic' => 'petty_cash_policy',
    'trigger_keywords' => ['petty cash', 'how to claim petty cash', 'petty cash reimbursement'],
    'domain' => 'voucher',
    'title' => 'Petty Cash Claim & Reimbursement Workflow',
    'summary' => 'Staff expense reimbursements under Rs. 10,000 are processed via Petty Cash Voucher.',
    'steps' => [
        '1. Attach physical receipt or vendor invoice.',
        '2. Obtain Department Lead sign-off.',
        '3. Cashier disburses from Cash in Hand (1110).'
    ],
    'note' => 'Claims over Rs. 10,000 require Bank Transfer Payment Voucher.',
    'actions' => [
        ['label' => '💵 Petty Cash Book', 'action' => 'navigate_page', 'payload' => ['page' => 'cashbook']],
    ]
]);

$assertCondition(
    $kbEntry && $kbEntry->id > 0 && $kbEntry->source_diagnostic_id === $latest->id,
    "Promote Diagnostic Trace to Self-Learning Knowledgebase",
    "Created KB Entry #{$kbEntry->id} linked to Diagnostic Trace #{$latest->id}"
);

// 6. Dynamic Knowledgebase Resolution (Instant Learning Verification)
$learnedGuidance = $guidance->getGuidance("how to claim petty cash", ['company_code' => 'MAIN']);

$assertCondition(
    $learnedGuidance !== null &&
    ($learnedGuidance['topic'] ?? '') === 'petty_cash_policy' &&
    str_contains($learnedGuidance['summary'] ?? '', 'Petty Cash Voucher') &&
    !empty($learnedGuidance['is_custom_kb']),
    "Instant Self-Learning: Copilot Resolves Promoted Knowledgebase Query",
    "Query resolved from dynamic knowledge base (Topic: {$learnedGuidance['topic']})"
);

// 7. Stats & Aggregates
$stats = $diagnostics->getStats('MAIN');
$assertCondition(
    isset($stats['total_traces']) && $stats['total_traces'] > 0 && isset($stats['active_knowledge_entries']) && $stats['active_knowledge_entries'] > 0,
    "Telemetry Aggregations & Dashboard Stats",
    "Total Traces: {$stats['total_traces']} | Active KB Entries: {$stats['active_knowledge_entries']}"
);

echo "\n========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED (Total: " . ($passed + $failed) . " Tests)\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
