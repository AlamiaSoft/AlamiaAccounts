---
topic: period_locking
domain: periods
title: Accounting Periods & Period Lock Management
trigger_keywords:
  - lock accounting period
  - close accounting period
  - reopen accounting period
  - unlock fiscal month
  - accounting period
  - how to close period
  - period lock management
actions:
  - label: "🔒 Accounting Periods"
    action: "navigate_page"
    payload:
      page: "periods"
  - label: "📜 View Audit Trail"
    action: "navigate_page"
    payload:
      page: "audit-trail"
---

Fiscal periods protect historical financial integrity against retroactive postings:

### Operational Steps:
1. Navigate to **Accounting Periods** in the sidebar.
2. Locate the desired fiscal month and click **Close Period** to lock postings.
3. Once closed, ordinary voucher entries dated within that period are blocked.
4. Reopening a closed period requires documenting a business audit reason.

> [!NOTE]
> All lock and reopen events are logged in the permanent accounting audit trail.
