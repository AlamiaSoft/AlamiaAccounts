This is **much better**. The resolver is now finding the relevant account instead of jumping to an unrelated account. But there's still a routing/intent issue.

| Observation                                  | Assessment                             | Recommended fix                                                                     |
| -------------------------------------------- | -------------------------------------- | ----------------------------------------------------------------------------------- |
| Finds `[1130] Meezan Bank - Main Operations` | ✅ Correct entity discovery             | Keep entity resolution                                                              |
| Also returns `Voucher OB-2026-001`           | ⚠️ Wrong result type for this request  | `INQUIRE_ACCOUNT` should prioritize/return the account, not generic voucher matches |
| User asked **“What is the balance?”**        | 🎯 Clear account-balance intent        | Router should execute `getAccountBalance(account_id)` directly                      |
| Two matching records shown together          | ⚠️ Search semantics still too generic  | Distinguish **entity resolution** from **global search**                            |
| Account has `Balance: Rs. 40,000`            | ✅ Required answer is already available | Copilot should answer directly rather than ask user to select                       |
| Opening voucher explains the account         | Useful secondary context               | Offer it *after* answering, e.g. “Opening balance came from OB-2026-001”            |

### Target behavior

Instead of:

```text
I found 2 records matching "Meezan Bank".
Which one would you like to explore?

Voucher OB-2026-001
[1130] Meezan Bank - Main Operations
```

The copilot should produce something like:

```text
[1130] Meezan Bank - Main Operations

Current balance: Rs. 40,000.00

The account was initialized through voucher OB-2026-001
on 1 Jan 2026.
```

### Key architectural distinction

Your search system should now have **two different modes**:

| Mode               | Example                               | Behavior                                             |
| ------------------ | ------------------------------------- | ---------------------------------------------------- |
| `GLOBAL_SEARCH`    | “search Meezan Bank”                  | Return accounts + vouchers + ledger entries          |
| `INQUIRE_ACCOUNT`  | “What is the balance of Meezan Bank?” | Resolve account → execute account-specific operation |
| `INQUIRE_VOUCHER`  | “Show OB-2026-001”                    | Resolve voucher → show voucher                       |
| `FIND_TRANSACTION` | “Show Ali's transaction”              | Resolve party/org → find transaction → voucher       |
| `INQUIRE_REPORT`   | “Show trial balance”                  | Execute report directly                              |

So **don't let `SearchService` decide what the user wants to explore**.

The classifier has already given you:

```json
{
  "intent": "INQUIRE_ACCOUNT",
  "account": "Meezan Bank",
  "target_object": "account"
}
```

The router should therefore effectively do:

```text
INQUIRE_ACCOUNT
    ↓
resolveAccount("Meezan Bank")
    ↓
getAccountBalance(account_id)
    ↓
answer
```

not:

```text
INQUIRE_ACCOUNT
    ↓
global search
    ↓
2 records
    ↓
ask user
```

**This is a good sign, though:** your latest result shows the entity-resolution problem is substantially improving. The next step is making the copilot **action-oriented rather than search-oriented**: once intent is sufficiently clear, execute the appropriate domain operation instead of presenting search results.
