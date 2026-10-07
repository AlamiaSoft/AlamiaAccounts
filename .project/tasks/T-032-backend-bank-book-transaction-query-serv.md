---
id: "T-032"
title: "Backend Bank Book Transaction Query Service and Multi-Bank API Endpoint"
status: "done"
priority: "high"
epic: "EP-08-financial-workstation-and-subledgers"
assigned_to: "backend"
depends_on:
blocks:
  - "T-030"
relevant_files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ReportService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/ReportController.php"
  - "AlamiaAccounts-Backend/routes/api.php"
verification: "php AlamiaAccounts-Backend/tests/integration/test_bank_book_service.php"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:21:04.942Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:25:12.459Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Certified Bank Book Query Service, multi-bank account discovery, inflows/outflows running balance computation, and API endpoint GET /api/reports/bank-book (4/4 PASS)"
---

## Objective
Implement backend domain logic and API endpoint (`GET /api/reports/bank-book`) to retrieve chronological banking transactions across all or specific bank accounts.

## Accounting Requirements
1. **Multi-Bank Account Discovery**:
   - Query all bank accounts under `1120 Bank Accounts` or accounts configured as bank ledgers.
2. **Accurate Opening & Running Balances**:
   - Compute opening balance as of `fromDate - 1 day`.
   - Calculate chronological running balance taking deposits/receipts as debits and withdrawals/payments as credits.
3. **Contra Transfers**:
   - Properly categorize bank-to-bank and cash-to-bank contra transfers.

## Acceptance Criteria
- [ ] API endpoint `GET /api/reports/bank-book` accepting `account_code`, `from_date`, `to_date`, `currency`.
- [ ] Response includes list of active bank accounts, opening balance, transaction entries, and closing balance.
- [ ] Unit/integration test `tests/integration/test_bank_book_service.php` passing.
