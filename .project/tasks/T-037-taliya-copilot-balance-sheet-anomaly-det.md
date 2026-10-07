---
id: "T-037"
title: "Taliya Copilot Balance Sheet Anomaly Detection Capability (diagnostics.balance_sheet_imbalance & Explain Actions)"
status: "done"
priority: "high"
epic: "EP-09-intelligent-balance-sheet-diagnostics-and-copilot"
assigned_to: "backend"
depends_on:
  - "T-036"
blocks:
  - "T-038"
relevant_files:
  - "app/Copilot/CopilotService.php"
  - "app/Copilot/IntentClassifierService.php"
  - "app/Copilot/AccountsCopilotBridge.php"
verification: "php AlamiaAccounts-Backend/tests/integration/test_copilot_balance_sheet_diagnostics.php"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:56:33.161Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:59:19.053Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Equipped Taliya AI Copilot with diagnostics.balance_sheet_imbalance intent, forensic synthesis, step-by-step guidance, and action buttons. Verified with integration test (6/6 PASS)."
---

## Objective
Equip Taliya AI Copilot with a specialized capability `diagnostics.balance_sheet_imbalance` that responds to accountant inquiries when the Balance Sheet is out of balance (e.g. *"Why is the balance sheet not balanced?"*, *"What caused the 80,000 difference?"*, *"How do I fix the balance sheet?"*).

## Conversational & Capability Behaviors
1. **Semantic Intent Recognition**:
   - Matches intent `diagnostics.balance_sheet_imbalance` from phrases like:
     - "Why is my balance sheet not balanced?"
     - "What is causing the Rs. 80,000 difference in balance sheet?"
     - "How do I correct the balance sheet imbalance?"
     - "Diagnose balance sheet" / "Analyze balance sheet errors"
2. **Automated Execution & Synthesis**:
   - Calls `ReportService::diagnoseBalanceSheetImbalance`.
   - Synthesizes findings in concise, clear, professional accountant language.
   - Highlights the exact offending accounts, vouchers, or P&L timing differences.
3. **Interactive Card & Action Buttons**:
   - Renders a rich `BalanceSheetDiagnosticsCard` with:
     - Difference badge ($Rs. 80,000$).
     - List of identified root-cause anomalies with severity icons.
     - Action buttons:
       - `[Fix via Journal Entry]`
       - `[Reclassify Account]`
       - `[View Offending Vouchers]`
       - `[Inspect Balance Sheet]`

## Acceptance Criteria
- [ ] Intent classified in `IntentClassifierService`.
- [ ] Handler implemented in `CopilotService::handleBalanceSheetDiagnostics`.
- [ ] Capability registered in `AccountsCopilotBridge`.
- [ ] Integration test passing (`tests/integration/test_copilot_balance_sheet_diagnostics.php`).
