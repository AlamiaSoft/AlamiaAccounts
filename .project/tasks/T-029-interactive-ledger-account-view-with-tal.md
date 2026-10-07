---
id: "T-029"
title: "Interactive Ledger/Account View with Tally-Style Voucher Drill-Down and Edit Navigation"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "frontend"
depends_on:
  - "T-034"
blocks:
  - "T-035"
relevant_files:
  - "AlamiaAccounts-Frontend/components/ledger-detail-view.tsx"
  - "AlamiaAccounts-Frontend/components/ledger-view.tsx"
  - "AlamiaAccounts-Frontend/app/page.tsx"
verification: "npm run build"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:27:35.550Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:28:59.319Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Enhanced LedgerView and LedgerDetailView with universal account switcher, date range presets, in-table search, useTallyTableNavigation (Up/Down/Enter/Esc), and seamless voucher drilldown navigation."
---

## Objective
Build an interactive, universal Account & General Ledger drilldown experience for **ANY** ledger account (e.g., individual vendor payable accounts like `ABC Tours`, customer receivables, cash/bank accounts, revenue, expenses, or group folders). Provides Tally-style keyboard & click navigation, where accountants can inspect chronological ledger transactions, view running balances, and hit `Enter` (or click) on any transaction row to immediately open its corresponding Voucher View or Edit screen.

## User Experience & Tally Navigation Invariants
1. **Universal Account Scope**:
   - Supports viewing any account in the Chart of Accounts: leaf posting accounts (e.g. `2110 ABC Tours`, `1110 Cash`), subledger accounts, or parent rollup categories (`2100 Accounts Payable`).
   - Rapid Account Combobox selector with instant code / name autocomplete.
2. **Keyboard-First Navigation (Tally-Style)**:
   - Arrow keys (`Up` / `Down`) to select ledger rows with active row highlighting via `useTallyTableNavigation`.
   - `Enter` key on highlighted row opens the corresponding voucher editor / view.
   - `Esc` key navigates back from voucher view to the previous ledger position.
3. **Context-Aware Routing**:
   - Standard vouchers (PV, RV, JV, CV, SV, PUV) route to their respective standard voucher screens in edit/view mode.
   - Custom vouchers (e.g. Airline Ticket Booking, Cargo Shipment) route to their dedicated custom voucher entry view.
4. **Ledger Integrity**:
   - Accurate opening balance as of `fromDate - 1 day`.
   - Running balance computation in tenant's active currency.
   - Real-time search/filter within ledger transactions.

## Acceptance Criteria
- [ ] Account selector dropdown with rapid search (code / name).
- [ ] Date range filtering with instant recalculation of opening, running, and closing balances.
- [ ] Keyboard navigation (`Up`/`Down` row focus, `Enter` to edit/view, `Esc` to return).
- [ ] Seamless transition to standard and custom voucher editing forms.
- [ ] Automated integration & E2E verification test.
