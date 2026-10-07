---
topic: voucher_correction
domain: voucher
title: Voucher Correction & Reversal Workflow
trigger_keywords:
  - how do i fix
  - how to fix
  - how to correct
  - how to reverse
  - fix wrong voucher
  - correct voucher amount
  - change voucher amount
  - modify voucher entry
  - fix wrong transaction
  - reverse posted voucher
  - fix voucher amount
  - wrong voucher amount
  - reversal workflow
actions:
  - label: "📄 Open Daybook"
    action: "navigate_page"
    payload:
      page: "daybook"
  - label: "📖 Chart of Accounts"
    action: "navigate_page"
    payload:
      page: "coa"
---

Under institutional double-entry standards (GAAP/IFRS), posted accounting vouchers are immutable and cannot be edited in place. To correct a posted voucher:

### Operational Steps:
1. **Post Compensating Reversal** (`REV-`): Zeroes out the erroneous entry in the permanent ledger.
2. **Draft Replacement Entry**: Post a new voucher with the correct amount, accounts, and documentation.

> [!NOTE]
> You can reverse any posted voucher directly from the **Daybook** screen or ask Taliya to prepare the draft.
