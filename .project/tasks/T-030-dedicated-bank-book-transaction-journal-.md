---
id: "T-030"
title: "Dedicated Bank Book Transaction Journal with Running Balances and Voucher Drill-Down"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "frontend"
depends_on:
  - "T-032"
  - "T-034"
blocks:
  - "T-035"
relevant_files:
  - "AlamiaAccounts-Frontend/components/bank-book.tsx"
  - "AlamiaAccounts-Frontend/components/sidebar.tsx"
  - "AlamiaAccounts-Frontend/app/page.tsx"
  - "AlamiaAccounts-Frontend/hooks/use-bank-book.ts"
verification: "npm run build"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:29:04.714Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:30:41.952Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Implemented BankBook component with multi-bank filter, running balances, Tally keyboard navigation, CSV export, and registered ?page=bankbook in sidebar and app routing."
---

## Objective
Create a dedicated **Bank Book** transaction journal module (alongside Cashbook and Daybook) that filters specifically for banking accounts and transactions, tracks deposits, withdrawals, contra bank transfers, running bank balances, and provides single-click / keyboard drilldown to view and edit vouchers.

## Architectural & Accounting Invariants
1. **Scope & Account Identification**:
   - Filter transactions belonging to bank accounts (root `1120 Bank Accounts`, configured bank ledgers, or accounts configured as bank type).
   - Multi-bank account selector tab/dropdown (e.g., Meezan Bank, HBL, Standard Chartered, All Banks).
2. **Transaction Details**:
   - Inflows (Receipts / Deposits), Outflows (Payments / Withdrawals), Cheque / Ref numbers, Contra transfers between bank-to-bank and cash-to-bank.
   - Opening bank balance, running bank balance, closing bank balance.
3. **Drill-Down & Editing**:
   - Clicking or pressing `Enter` on any bank transaction opens its full Voucher detail / edit form.

## Acceptance Criteria
- [ ] Dedicated sidebar route: `Transactions -> Bank Book` (`?page=bankbook`).
- [ ] Account selector for specific bank account or aggregate view across all bank accounts.
- [ ] Inflow / Outflow / Running balance columns with accurate opening balance.
- [ ] Keyboard navigation (`Enter` to drilldown/edit, `Esc` to return).
- [ ] Export to Excel/PDF and print template integration.
