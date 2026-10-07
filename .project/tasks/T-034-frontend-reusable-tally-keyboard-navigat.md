---
id: "T-034"
title: "Frontend Reusable Tally Keyboard Navigation Hook for Interactive Financial Tables"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "frontend"
depends_on:
blocks:
  - "T-029"
  - "T-030"
  - "T-031"
relevant_files:
  - "AlamiaAccounts-Frontend/hooks/use-tally-table-navigation.ts"
verification: "npm run build"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:26:53.806Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:27:22.710Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Created and verified useTallyTableNavigation hook supporting Arrow keys, PageUp/Down, Home/End, Enter to select, and Escape to exit with auto-scroll and visual focus state."
---

## Objective
Create a reusable React hook (`useTallyTableNavigation`) that provides keyboard-first interaction across all financial data tables (General Ledger, Bank Book, Cashbook, Daybook, AR/AP Subledgers).

## Features
1. **Arrow Navigation**:
   - `ArrowDown` / `ArrowUp` to change focused row index.
   - Smooth auto-scroll into view when navigating long tables.
2. **Action Triggers**:
   - `Enter` key triggers the active row's `onSelect` / `onEdit` handler.
   - `Space` key triggers selection / expansion.
   - `Escape` key triggers back / close handler.
3. **Visual Highlighting**:
   - Returns active focused index, helper props, and CSS class bindings (`ring-2 ring-primary/60 bg-primary/5`).

## Acceptance Criteria
- [ ] Hook `useTallyTableNavigation({ items, onEnter, onEscape })` created.
- [ ] Exported and tested in isolation.
