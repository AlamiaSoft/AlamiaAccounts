---
id: "T-031"
title: "Dedicated Accounts Payable (AP) and Accounts Receivable (AR) Subledger Management Views"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "frontend"
depends_on:
  - "T-033"
  - "T-034"
blocks:
  - "T-035"
relevant_files:
  - "AlamiaAccounts-Frontend/components/subledger-ar.tsx"
  - "AlamiaAccounts-Frontend/components/subledger-ap.tsx"
  - "AlamiaAccounts-Frontend/components/sidebar.tsx"
  - "AlamiaAccounts-Frontend/app/page.tsx"
  - "AlamiaAccounts-Frontend/hooks/use-subledgers.ts"
verification: "npm run build"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:30:49.354Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:32:02.579Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Created and verified SubledgerAR and SubledgerAP components with party directories, aging brackets (0-30, 31-60, 61-90, 90+), Tally keyboard navigation, and voucher drilldown."
---

## Objective
Implement separate, dedicated management and transaction views for **Accounts Payable (AP)** and **Accounts Receivable (AR)** subledgers. Each subledger view must provide party-level summary balances, transaction history per party (bills, invoices, payments, credit/debit notes), and Tally-style drilldown to view and edit underlying vouchers.

## Architectural & Accounting Invariants
1. **Accounts Receivable (AR) View**:
   - Customer/Debtor subledgers (root `1200 Accounts Receivable` and child party accounts).
   - Invoices issued, payments received, aging brackets (0-30, 31-60, 61-90, 90+ days), and outstanding customer balances.
   - Drilldown into Sales Vouchers, Receipt Vouchers, or Custom Ticket/Cargo vouchers.
2. **Accounts Payable (AP) View**:
   - Vendor/Supplier/Airline subledgers (root `2100 Accounts Payable` and child party accounts).
   - Bills received, payments disbursed, vendor aging, and outstanding payable balances.
   - Drilldown into Purchase Vouchers, Payment Vouchers, or Commission settlements.
3. **Interactive Subledger Navigation**:
   - Split view or master-detail layout: list of parties on left/top, detailed chronological ledger of selected party on right/bottom.
   - Keyboard & click navigation (`Enter` to open voucher editor, `Esc` to return).

## Acceptance Criteria
- [ ] Dedicated sidebar routes: `Accounts -> Accounts Receivable` (`?page=subledger-ar`) and `Accounts -> Accounts Payable` (`?page=subledger-ap`).
- [ ] Party list with total billed, total paid, and current net balance.
- [ ] Party ledger detail showing all vouchers with running balances.
- [ ] Keyboard navigation (`Enter` on any line opens the voucher editor/view).
- [ ] Reconciliation badge matching total AR/AP to Balance Sheet figures.
