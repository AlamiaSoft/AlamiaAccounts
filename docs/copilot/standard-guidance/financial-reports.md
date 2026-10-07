---
topic: financial_reports
domain: reports
title: Financial Reports & Statements
trigger_keywords:
  - financial reports
  - how to view reports
  - financial statements
  - how to generate reports
  - trial balance report
  - balance sheet report
  - profit and loss report
actions:
  - label: "📊 View Trial Balance"
    action: "draft_prompt"
    payload:
      prompt: "Show Trial Balance summary"
  - label: "📄 View Daybook"
    action: "navigate_page"
    payload:
      page: "daybook"
---

Alamia Accounts generates real-time institutional financial reports:

### Operational Steps:
1. **Trial Balance**: Verifies that total debits strictly equal total credits (`Dr === Cr`).
2. **Profit & Loss (P&L)**: Summarizes operating revenue, cost of sales, and operating expenses.
3. **Balance Sheet**: Displays assets, liabilities, and equity balances.

> [!NOTE]
> All reports are calculated dynamically from posted ledger journals.
