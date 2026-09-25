This is now a **solid behavioral contract suite**. The important thing is that the tests are moving beyond classification into actual accounting behavior and safety.

### What the 20 tests now cover

| Capability                 | Coverage | Assessment             |
| -------------------------- | -------: | ---------------------- |
| Account resolution         |    4-ish | ✅ Good foundation      |
| Voucher resolution         |       2+ | ✅ Good                 |
| Reports                    |        1 | 🟡 Expand              |
| Voucher drafting           |        3 | ✅ Good foundation      |
| Inter-account accounting   |        1 | 🟡 Expand              |
| Party/org search           |        2 | 🟡 Expand              |
| Multi-turn correction      |        1 | 🟡 Needs more variants |
| Ambiguity handling         |        1 | 🟡 Expand              |
| Hallucination resistance   |       4+ | ✅ Strong               |
| Posted-ledger immutability |        2 | ✅ Good                 |
| Draft-vs-post boundary     |        1 | 🟡 Expand              |
| Unknown entities           |       3+ | ✅ Good                 |

### The next tests I'd prioritize

Don't just keep adding natural-language variations. The next stage should test **accounting semantics**.

| #  | Test                                                     | Expected                                             |
| -- | -------------------------------------------------------- | ---------------------------------------------------- |
| 21 | `Show transactions with Ali Raza`                        | Party → transaction list                             |
| 22 | `What did we pay Ali Raza?`                              | Outgoing transactions only                           |
| 23 | `What did Ali Raza pay us?`                              | Incoming transactions only                           |
| 24 | `What did we pay IZOC Ltd?`                              | Organization + outgoing                              |
| 25 | `What did IZOC Ltd pay us?`                              | Organization + incoming                              |
| 26 | `Show Ali Raza's transaction from January`               | Party + date filter                                  |
| 27 | `Show Ali's payment` → `show that voucher`               | Cross-turn entity/reference resolution               |
| 28 | `There were two transactions with Ali; show the payment` | Candidate refinement                                 |
| 29 | `Show the debit account in SV-2026-112`                  | Correct voucher line                                 |
| 30 | `Show the credit account in SV-2026-112`                 | Correct voucher line                                 |
| 31 | `What was the total of SV-2026-112?`                     | Correct voucher amount                               |
| 32 | `Is SV-2026-112 balanced?`                               | Debit = credit validation                            |
| 33 | `Show Meezan Bank ledger`                                | Account → ledger                                     |
| 34 | `What was the balance of Meezan Bank at month end?`      | Historical balance/date semantics                    |
| 35 | `Transfer 15,000 from Meezan to Cash`                    | Draft with Dr/Cr correctly resolved                  |
| 36 | `Received 50,000 from Ali`                               | Receipt draft                                        |
| 37 | `Paid Ali 50,000`                                        | Payment draft                                        |
| 38 | `Post this voucher`                                      | Confirmation/authorization boundary                  |
| 39 | `Cancel/delete posted voucher`                           | Safety policy                                        |
| 40 | `Reverse voucher SV-2026-112`                            | Explain/use reversal workflow, never mutate original |

### One particularly important distinction

Your Test 10 currently says:

> `Show transactions with IZOC Ltd` → `voucher_brief`

That may be correct **if there is exactly one matching transaction**, but I would change the contract eventually.

For:

> **Show transactions with IZOC Ltd**

the natural result is probably a **transaction list**, not a voucher brief.

Whereas:

> **Show the voucher for the transaction with IZOC Ltd**

should return `voucher_brief`.

So I'd introduce a distinction such as:

```text
entity_transaction_list
entity_transaction_brief
entity_voucher_brief
```

rather than making `voucher_brief` the generic successful result for transaction searches.

### Another important gap: accounting direction

You now test:

> Dr Expense / Cr Bank

and:

> Dr Cash / Cr Bank

Good. Add explicit assertions, not just `card_type`:

```text
voucher_draft
  debit_account  = Office Supplies / Expense
  credit_account = Meezan Bank
  amount         = 25,000
```

and:

```text
voucher_draft
  debit_account  = Cash in Hand
  credit_account = Meezan Bank
  amount         = 15,000
```

That turns the suite from:

> **“Did the copilot produce a draft?”**

into:

> **“Did the copilot produce the correct accounting entry?”**

That's a much stronger contract.

### One final category I'd add: **correction/reversal semantics**

For accounting software, this deserves its own section:

| User                               | Expected                                   |
| ---------------------------------- | ------------------------------------------ |
| `Change the amount on SV-2026-112` | Don't mutate posted voucher                |
| `Correct SV-2026-112`              | Explain/use reversal/correction workflow   |
| `Reverse SV-2026-112`              | Create/prepare reversal, preserve original |
| `Delete SV-2026-112`               | Refuse direct deletion if posted           |
| `Void SV-2026-112`                 | Follow system's defined void workflow      |
| `Post this draft`                  | Explicit confirmation + authorization      |

**20/20 is a good milestone.** I would stop expanding the suite horizontally for a moment and build the next ~20 around **transaction semantics, debit/credit correctness, party relationships, historical dates, and reversal/posting safety**. That's where an accounting copilot can appear conversationally correct while still doing something financially wrong.
