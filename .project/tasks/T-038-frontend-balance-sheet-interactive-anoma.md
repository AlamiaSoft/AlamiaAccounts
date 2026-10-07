---
id: "T-038"
title: "Frontend Balance Sheet Interactive Anomaly Detection Panel & 1-Click Copilot Diagnostic Trigger"
status: "done"
priority: "high"
epic: "EP-09-intelligent-balance-sheet-diagnostics-and-copilot"
assigned_to: "frontend"
depends_on:
  - "T-036"
  - "T-037"
blocks:
  - "T-039"
relevant_files:
  - "components/financial-reports.tsx"
  - "components/copilot-widget.tsx"
  - "lib/api/index.ts"
  - "hooks/use-reports.ts"
verification: "npm run build"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:59:31.257Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T16:03:19.905Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Frontend Balance Sheet Interactive Anomaly Detection Panel and 1-Click Copilot Diagnostic Trigger implemented and compiled successfully with next build"
---

## Objective
Update the Balance Sheet view in `financial-reports.tsx` so that when an imbalance is detected ($Assets \neq Liabilities + Equity$), accountants get an interactive "Auto-Diagnose Imbalance" button and a 1-click "Ask Taliya Copilot to Explain & Fix" trigger.

## UI/UX Enhancements
1. **Unbalanced Banner Action Group**:
   - In the red imbalance banner, replace static text with interactive buttons:
     - `[🪄 Auto-Diagnose Root Causes]` (runs diagnostic scan inline)
     - `[🤖 Ask Taliya Copilot to Explain & Fix]` (opens Copilot widget pre-populated with diagnostic context)
2. **Expandable Root-Cause Breakdown Panel**:
   - When "Auto-Diagnose" is clicked, loads `/api/reports/balance-sheet-diagnostics` and displays an accordion / cards breakdown of detected anomalies:
     - Vector tag (e.g. `Unclassified Accounts`, `Unbalanced Voucher`, `P&L Cutoff`).
     - Variance contribution amount (e.g. `Rs. 80,000`).
     - Specific accounts / voucher reference links.
     - 1-click corrective navigation or fix suggestions.
3. **Copilot Deep-Link Event**:
   - Clicking "Ask Taliya" dispatches `window.dispatchEvent(new CustomEvent('copilot:ask', ...))` opening the Copilot widget and automatically initiating the diagnostic conversation.

## Acceptance Criteria
- [ ] Added `reportApi.balanceSheetDiagnostics` and `useBalanceSheetDiagnostics` hook.
- [ ] Interactive "Auto-Diagnose" and "Ask Taliya" buttons rendered in Balance Sheet tab when `!isBalanced`.
- [ ] Expandable anomaly breakdown card rendered with direct navigation links.
- [ ] Clean build with `npm run build`.
