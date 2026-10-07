---
id: "T-033"
title: "Backend Subledger Analytics Service for Accounts Receivable and Accounts Payable"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "backend"
depends_on:
blocks:
  - "T-031"
relevant_files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ReportService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/ReportController.php"
  - "AlamiaAccounts-Backend/routes/api.php"
verification: "php AlamiaAccounts-Backend/tests/integration/test_subledger_services.php"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:25:23.560Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:26:48.366Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Verified ReportService getReceivablesReport and getPayablesReport compute aging buckets (0-30, 31-60, 61-90, 90+) and party balances correctly via integration test (5/5 PASS)."
---

## Objective
Implement backend domain logic and API endpoints for comprehensive Accounts Receivable (AR) and Accounts Payable (AP) subledger reporting, including party directories, aging buckets (0-30, 31-60, 61-90, 90+ days), and party statement drilldowns.

## Accounting Requirements
1. **`GET /api/reports/receivables`**:
   - Customer subledgers (`1200` hierarchy), total billed, total received, aging breakdown, net balance.
2. **`GET /api/reports/payables`**:
   - Vendor/Supplier subledgers (`2100` hierarchy), total billed, total paid, aging breakdown, net balance.
3. **Reconciliation Guarantee**:
   - Total AR must match the Balance Sheet `1200` asset line.
   - Total AP must match the Balance Sheet `2100` liability line.

## Acceptance Criteria
- [ ] Endpoints `GET /api/reports/receivables` and `GET /api/reports/payables`.
- [ ] Party aging calculations and voucher history per party.
- [ ] Integration test `tests/integration/test_subledger_services.php` passing.
