---
id: "T-036"
title: "Backend Multi-Vector Balance Sheet Diagnostics Engine (ReportService::diagnoseBalanceSheetImbalance & API)"
status: "done"
priority: "high"
epic: "EP-09-intelligent-balance-sheet-diagnostics-and-copilot"
assigned_to: "backend"
depends_on:
blocks:
  - "T-037"
  - "T-038"
relevant_files:
  - "packages/AlamiaSoft/alamia-accounts/src/Services/ReportService.php"
  - "packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/ReportController.php"
  - "packages/AlamiaSoft/alamia-accounts/routes/api.php"
verification: "php AlamiaAccounts-Backend/tests/integration/test_balance_sheet_diagnostics.php"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T15:54:19.213Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T15:56:22.102Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Created AccountingDiagnosticService with 5 forensic audit vectors, endpoint GET /api/reports/balance-sheet-diagnostics, and verified with integration test (5/5 PASS)."
---

## Objective
Build a specialized multi-vector diagnostics engine `ReportService::diagnoseBalanceSheetImbalance` that automatically audits and pinpoints the exact mathematical and structural root causes when a Balance Sheet is not in equilibrium ($Assets \neq Liabilities + Equity$).

## Anomaly Detection Vectors
1. **Unclassified / Orphan Accounts**:
   - Detects active accounts with non-zero balances whose class/type cannot be determined or resolved to standard balance sheet categories.
2. **Unbalanced Journal Vouchers**:
   - Detects any posted transaction where $\sum(\text{Debits}) \neq \sum(\text{Credits})$.
3. **Cumulative P&L vs Retained Earnings Variance**:
   - Compares cumulative net income up to `asOfDate` against recorded equity retained earnings, identifying post-date transactions or direct Retained Earnings entries.
4. **Opening Balance Position Integrity**:
   - Verifies if initial `OB-` position exists, is balanced, or if suspense balances remain unresolved.
5. **Normal Balance Inversions**:
   - Flags accounts with inverted balances (e.g. Asset with net Credit, Liability with net Debit) that might indicate inverted entries.

## Structured Output Schema
```json
{
  "is_balanced": false,
  "as_of_date": "2026-10-07",
  "currency": "PKR",
  "total_assets": 178000,
  "total_liabilities_and_equity": 258000,
  "discrepancy": 80000,
  "anomalies_count": 2,
  "anomalies": [
    {
      "vector": "UNCLASSIFIED_ACCOUNTS",
      "severity": "critical",
      "title": "Unclassified Accounts Excluded from Balance Sheet",
      "description": "Account 1290 (Other Assets) has Rs. 80,000 balance but lacks classification.",
      "impact_amount": 80000,
      "affected_accounts": ["1290"],
      "recommended_fix": "Assign parent category or classification to Account 1290 in Chart of Accounts."
    }
  ],
  "recommended_actions": [
    {
      "action": "reclassify_account",
      "label": "Update Account 1290 Parent Category",
      "payload": { "account_code": "1290" }
    }
  ]
}
```

## Acceptance Criteria
- [ ] `ReportService::diagnoseBalanceSheetImbalance(asOfDate, currency)` implemented.
- [ ] Route `GET /api/reports/balance-sheet-diagnostics` registered in `routes/api.php`.
- [ ] Integration test passing (`tests/integration/test_balance_sheet_diagnostics.php`).
