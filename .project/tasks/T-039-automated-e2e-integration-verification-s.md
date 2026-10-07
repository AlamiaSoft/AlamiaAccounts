---
id: "T-039"
title: "Automated E2E & Integration Verification Suite for Balance Sheet Diagnostics and Copilot (Suite 12)"
status: "done"
priority: "high"
epic: "EP-09-intelligent-balance-sheet-diagnostics-and-copilot"
assigned_to: "reviewer"
depends_on:
  - "T-036"
  - "T-037"
  - "T-038"
blocks:
relevant_files:
  - "tests/e2e/12-balance-sheet-diagnostics-and-copilot.test.js"
  - "tests/e2e/run-all.js"
verification: "node tests/e2e/12-balance-sheet-diagnostics-and-copilot.test.js"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T16:03:33.137Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T16:04:33.833Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Automated Playwright E2E Suite 12 (tests/e2e/12-balance-sheet-diagnostics-and-copilot.test.js) executed and passed 100%"
---

## Objective
Build a comprehensive Playwright E2E regression test suite verifying the end-to-end intelligent Balance Sheet diagnostics and Taliya AI Copilot workflow.

## Test Verification Vectors
1. **Balanced State Check**:
   - In a balanced company, diagnostics returns `is_balanced: true` with 0 anomalies.
2. **Artificial Imbalance Simulation & Detection**:
   - Creates an unclassified account / orphan transaction or date variance.
   - Verifies `GET /api/reports/balance-sheet-diagnostics` pinpoints the exact offending account and variance amount.
3. **Taliya Copilot Chat Query Verification**:
   - Queries Taliya via `/copilot/chat` with *"Why is the balance sheet not balanced?"*.
   - Verifies Taliya detects the imbalance, cites the root causes in natural language, and returns the interactive diagnostics card.
4. **Browser UI Flow**:
   - Loads `?page=balance-sheet` on an unbalanced tenant.
   - Clicks "Auto-Diagnose Root Causes" and verifies the anomaly panel renders with exact discrepancy.
   - Clicks "Ask Taliya to Explain & Fix" and confirms the Copilot widget opens with diagnostic briefing.

## Acceptance Criteria
- [ ] Test file `tests/e2e/12-balance-sheet-diagnostics-and-copilot.test.js` created and added to `run-all.js`.
- [ ] 100% test pass on full suite (`node tests/e2e/12-balance-sheet-diagnostics-and-copilot.test.js`).
