---
topic: opening_balances
domain: opening_balance
title: Compound Balanced Opening Position Setup
trigger_keywords:
  - opening balance setup
  - how to post opening balances
  - compound opening balance
  - initial ledger balances
  - opening balance batch
  - capital equity allocation
actions:
  - label: "⚖️ Opening Balances"
    action: "navigate_page"
    payload:
      page: "opening-balances"
  - label: "📊 Balance Sheet"
    action: "draft_prompt"
    payload:
      prompt: "Show Balance Sheet summary"
---

Opening balances establish starting financial positions for new company domains in Alamia Accounts:

### Operational Steps:
1. Navigate to **Opening Balances** in the sidebar.
2. Enter starting balances for Assets, Liabilities, and Equity accounts.
3. Verify compound double-entry balance ($\sum \text{Debits} = \sum \text{Credits}$).
4. Any net difference must be explicitly allocated to Capital (`5100`) or Retained Earnings (`5200`).
5. Submit batch (`OB-YYYY-001`).

> [!NOTE]
> Duplicate opening balance batches are strictly prohibited per tenant domain.
