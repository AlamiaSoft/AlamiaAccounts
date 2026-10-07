---
topic: account_creation
domain: coa
title: Adding Accounts in Chart of Accounts
trigger_keywords:
  - add new account
  - add bank account
  - create bank account
  - create cash account
  - setup ledger account
  - chart of accounts
  - how to add account
  - how to create account
  - add expense account
  - new ledger account
actions:
  - label: "📖 Open Chart of Accounts"
    action: "navigate_page"
    payload:
      page: "coa"
  - label: "📊 View Trial Balance"
    action: "draft_prompt"
    payload:
      prompt: "Show Trial Balance summary"
---

Alamia Accounts uses a structured 4-digit hierarchical Chart of Accounts:

### Operational Steps:
1. Navigate to **Chart of Accounts** (`COA`).
2. Select the appropriate Category Folder (e.g., `1120 Bank Accounts` or `6100 Operating Expenses`).
3. Click **Add Account**, enter the unique 4-digit code (e.g., `1135`), name, and currency.
4. Save to activate the account for ledger postings.

> [!NOTE]
> Transactions can only be posted to leaf accounts (not category folders).
