---
id: "T-035"
title: "Automated E2E Test Suite for Tally Workstation, Bank Book, and AR/AP Subledgers"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "reviewer"
depends_on:
  - "T-029"
  - "T-030"
  - "T-031"
blocks:
relevant_files:
  - "tests/e2e/11-tally-workstation-and-subledgers.test.js"
  - "tests/e2e/run-all.js"
verification: "node tests/e2e/11-tally-workstation-and-subledgers.test.js"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:32:16.708Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:34:31.395Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Built and executed automated Playwright E2E suite tests/e2e/11-tally-workstation-and-subledgers.test.js certifying Bank Book, AR/AP Subledgers with aging brackets, and Tally keyboard navigation (100% PASS)."
---

## Objective
Build a comprehensive Playwright E2E regression test suite verifying the complete interactive workstation workflow:
1. Keyboard navigation (Arrow keys + `Enter`) in General Ledger, opening voucher view and return via `Esc`.
2. Bank Book filtering, multi-bank switcher, running balance calculation, and voucher drilldown.
3. AR Subledger customer directory, aging tables, and voucher editing.
4. AP Subledger vendor directory, aging tables, and voucher editing.

## Acceptance Criteria
- [ ] Test file `tests/e2e/11-tally-workstation-and-subledgers.test.js` created and added to `run-all.js`.
- [ ] 100% test pass on full suite (`node tests/e2e/11-tally-workstation-and-subledgers.test.js`).
