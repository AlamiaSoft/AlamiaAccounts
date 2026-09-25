This is a **meaningful improvement**. The 8 tests now cover the main failure modes you showed, and all pass.

| Area                      | Current result    | Comment                                                    | Suggested next improvement                                                                     |
| ------------------------- | ----------------- | ---------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Account natural language  | ✅ PASS            | `"balance of Meezan Bank"` resolves correctly              | Add ambiguity test with multiple similarly named accounts                                      |
| Account direct query      | ✅ PASS            | `"Balance of..."` routes to account brief                  | Good                                                                                           |
| Account code              | ✅ PASS            | `1130` resolves directly                                   | Good                                                                                           |
| Account name              | ✅ PASS            | `"Cash in Hand"` resolves correctly                        | Good                                                                                           |
| Voucher reference         | ✅ PASS            | Exact voucher reference resolves                           | Add lowercase/malformed spacing test                                                           |
| Voucher drafting          | ✅ PASS            | Amount + purpose + account produces draft                  | Add tests for missing account / ambiguous account before allowing draft                        |
| Unknown person            | ✅ PASS            | `"Ali Raza"` no longer falsely becomes an account          | **Very important regression test; keep it permanently**                                        |
| Conversational correction | ✅ PASS            | `"Ali Raza of Izoc Ltd"` → `SV-2026-112`                   | Strongest test in the suite; expand this substantially                                         |
| Entity → action routing   | ✅ PASS            | Account query produces `account_brief`, not generic search | This is the architectural improvement you wanted                                               |
| Multi-turn resolution     | ✅ PASS            | Correctly recovered from initial failed search             | Test variations in wording/order                                                               |
| False-positive protection | ⚠️ Partial        | Only tested one unknown name                               | Need adversarial names that resemble account names/descriptions                                |
| Search ranking            | ⚠️ Untested       | `SV-2026-112` was found                                    | Test multiple matching transactions and ensure correct one is selected                         |
| Organization relationship | ⚠️ Untested       | IZOC + Ali works once                                      | Test Ali Raza belonging to another organization                                                |
| Ambiguity handling        | ⚠️ Untested       | No duplicate-party scenario                                | Should ask user to disambiguate rather than arbitrarily select                                 |
| Conversation state        | ⚠️ Basic          | History works                                              | Test 3–5 turn conversations and correction chains                                              |
| Dates                     | ❌ Untested        | No temporal filtering                                      | Add `"Ali's transaction last month"` / `"in January"`                                          |
| Transaction types         | ❌ Untested        | Only generic transaction                                   | Add payment, receipt, sale, purchase, journal                                                  |
| Negative constraints      | ❌ Untested        | None                                                       | `"not the opening voucher"` / `"the payment, not the receipt"`                                 |
| Voucher retrieval         | ⚠️ Basic          | Finds reference                                            | Verify the returned voucher is actually the transaction matching **both** party + organization |
| Regression suite          | ✅ Good foundation | 8 tests are concise and meaningful                         | Grow this into a permanent copilot contract test suite                                         |

### One thing I'd change in Test 7

This:

```php
'query' => 'Ali Raza',
'expected_intent' => 'entity_not_found',
```

is useful, but don't interpret it as:

> `"Ali Raza"` must always be `not_found`.

Eventually you may actually have an **Ali Raza contact** in the system.

The real invariant should be:

```text
"Ali Raza"
→ never infer an unrelated accounting account merely because text matches
```

So once contacts/parties exist, this test should become something like:

```text
Ali Raza
→ entity_contact / entity_party
→ OR entity_not_found
→ NEVER entity_account unless an actual account named Ali Raza exists
```

### Most important next tests

I'd add these before considering the resolver mature:

| Test                                         | Expected behavior                                             |
| -------------------------------------------- | ------------------------------------------------------------- |
| `"Ali Raza from Izoc"`                       | Find Ali + IZOC transaction                                   |
| `"Show Ali Raza's transaction"`              | Find transaction → voucher                                    |
| `"Show Ali Raza's payment"`                  | Find payment transaction → voucher                            |
| `"What did we pay Ali Raza?"`                | Transaction search                                            |
| `"What did IZOC pay us?"`                    | Organization + direction-aware transaction search             |
| `"Ali Raza of another company"`              | Don't confuse with IZOC Ali                                   |
| `"Ali Raza"` when actual contact exists      | Contact/party, not account                                    |
| `"Meezan"`                                   | Resolve account if unique; otherwise disambiguate             |
| `"Meezan Bank"` when multiple accounts exist | Ask which account                                             |
| `"What is Ali Raza's balance?"`              | **Don't assume account**; resolve party/account semantics     |
| `"Ali Raza's transaction in January"`        | Party + date filter                                           |
| `"the transaction with Ali from yesterday"`  | Conversation context + date                                   |
| `"no, I mean the payment"`                   | Modify previous transaction intent rather than restart search |
| `"show me that voucher"`                     | Resolve pronoun from conversation context                     |
| `"not that one, the other payment"`          | Candidate-level conversational correction                     |

### One architectural point

Your test suite is now proving something more valuable than **“Qwen classifies correctly.”**

It's proving:

```text
Natural language
      ↓
Intent
      ↓
Entity resolution
      ↓
Domain-specific operation
      ↓
Correct UI card
      ↓
Actual ERP record
```

That's the right thing to test.

I'd therefore rename this from **“Intent & Entity Resolution Verification Suite”** eventually to something like:

> **Copilot Behavioral Contract Tests**

because the important assertion isn't merely what the classifier says. It's that:

> **Given this user request, the copilot produces the correct domain result.**

That will also let you freely replace Qwen3.5:4B later without rewriting the tests. The model becomes an implementation detail; **the behavioral contract stays fixed.**


Yes. For **Alamia Accounts**, I'd expand the suite around accounting semantics rather than trying to test every possible natural-language phrase. The key is to test whether the copilot **gets the right accounting object, relationship, operation, and safety boundary**.

### Recommended contract-test matrix

| Category                     | Example test                                           | Expected behavior                                               | Priority   |
| ---------------------------- | ------------------------------------------------------ | --------------------------------------------------------------- | ---------- |
| **Account**                  | `What is the balance of Meezan Bank?`                  | Account → balance                                               | ✅ Existing |
|                              | `Show account 1130`                                    | Exact account resolution                                        | ✅ Existing |
|                              | `What's in cash?`                                      | Resolve Cash account if unambiguous                             | P1         |
|                              | `How much cash do we have?`                            | Account balance, not generic search                             | P1         |
|                              | `Show me Meezan Bank ledger`                           | Account → ledger                                                | P1         |
|                              | `Transactions in Meezan Bank`                          | Account → ledger transactions                                   | P1         |
| **Ambiguous accounts**       | `Meezan Bank` with 2 matching accounts                 | Ask/disambiguate                                                | **P0**     |
|                              | `Bank` with multiple bank accounts                     | Do not arbitrarily choose                                       | **P0**     |
| **Voucher**                  | `Tell me about OB-2026-001`                            | Voucher details                                                 | ✅ Existing |
|                              | `Show JV-2026-001`                                     | Voucher details                                                 | P1         |
|                              | `Open the opening balance voucher`                     | Resolve by description/type                                     | P1         |
|                              | `What was the amount in OB-2026-001?`                  | Voucher amount                                                  | P1         |
|                              | `Was OB-2026-001 balanced?`                            | Voucher validation                                              | P1         |
| **Party / contact**          | `Ali Raza`                                             | Contact/party if exists; otherwise not-found                    | P0         |
|                              | `Show transactions with Ali Raza`                      | Party → transactions                                            | **P0**     |
|                              | `Show Ali Raza's voucher`                              | Party → transaction → voucher                                   | **P0**     |
|                              | `What did we pay Ali Raza?`                            | Party + outgoing transaction                                    | **P0**     |
|                              | `What did Ali Raza pay us?`                            | Party + incoming transaction                                    | **P0**     |
| **Organization**             | `Show transactions with IZOC Ltd`                      | Organization → transactions                                     | P0         |
|                              | `What did we pay IZOC?`                                | Organization + outgoing                                         | P0         |
|                              | `What did IZOC pay us?`                                | Organization + incoming                                         | P0         |
| **Person ↔ organization**    | `Ali Raza of IZOC`                                     | Resolve relationship                                            | **P0**     |
|                              | `Ali Raza from IZOC, show the voucher`                 | Correct voucher                                                 | ✅ Existing |
|                              | Same Ali Raza, different organization                  | Don't confuse parties                                           | **P0**     |
|                              | Multiple Ali Razas in different organizations          | Ask/disambiguate                                                | **P0**     |
| **Conversation**             | `Show Ali's transaction` → `the payment`               | Refine previous search                                          | **P0**     |
|                              | `Show Ali's transaction` → `that voucher`              | Resolve pronoun                                                 | **P0**     |
|                              | `Ali Raza` → `no, the one from IZOC`                   | Correct previous entity                                         | **P0**     |
|                              | `Meezan Bank` → `show its ledger`                      | Preserve entity context                                         | P1         |
| **Date/time**                | `Ali's transaction in January`                         | Party + date filter                                             | P1         |
|                              | `Meezan transactions last month`                       | Account + date range                                            | P1         |
|                              | `What did we pay Ali yesterday?`                       | Party + direction + date                                        | P1         |
|                              | `Show today's vouchers`                                | Voucher date filter                                             | P1         |
| **Transaction types**        | `Show payments to Ali`                                 | Payment transactions                                            | P1         |
|                              | `Show receipts from Ali`                               | Receipt transactions                                            | P1         |
|                              | `Show journal entries for Meezan`                      | Journal entries                                                 | P1         |
|                              | `Show purchases from IZOC`                             | Purchase-related transactions                                   | P1         |
| **Direction**                | `What did we pay IZOC?`                                | Outgoing                                                        | **P0**     |
|                              | `What did IZOC pay us?`                                | Incoming                                                        | **P0**     |
|                              | `Money received from Ali`                              | Incoming                                                        | P1         |
|                              | `Money sent to Ali`                                    | Outgoing                                                        | P1         |
| **Reports**                  | `Show trial balance`                                   | Trial balance                                                   | P1         |
|                              | `Show P&L`                                             | Profit/loss                                                     | P1         |
|                              | `Show balance sheet`                                   | Balance sheet                                                   | P1         |
|                              | `How profitable are we?`                               | P&L / financial summary                                         | P2         |
| **Drafting**                 | `Paid Rs 25,000 for office supplies via Meezan`        | Draft voucher                                                   | ✅ Existing |
|                              | `Received Rs 50,000 from Ali`                          | Draft receipt                                                   | P0         |
|                              | `Paid Ali Rs 20,000`                                   | Draft payment                                                   | P0         |
|                              | `Transfer 50,000 from Meezan to Cash`                  | Draft transfer                                                  | **P0**     |
|                              | `Record rent of 100,000`                               | Draft, but identify missing account/payment source              | P1         |
| **Draft safety**             | `Pay Ali 50,000`                                       | Draft only; **never post automatically**                        | **P0**     |
|                              | `Delete voucher OB-2026-001`                           | Require explicit confirmation/permission                        | **P0**     |
|                              | `Post this voucher`                                    | Explicit action + confirmation/authorization                    | **P0**     |
| **Accounting correctness**   | `What is Meezan's balance?`                            | Use ledger/account balance, not sum of arbitrary search results | **P0**     |
|                              | `Show voucher total`                                   | Correct debit/credit interpretation                             | P1         |
|                              | `Is this voucher balanced?`                            | Debit = credit validation                                       | P1         |
|                              | `What account was debited?`                            | Correct voucher line                                            | P1         |
|                              | `What account was credited?`                           | Correct voucher line                                            | P1         |
| **Negative / ambiguity**     | `Bank` with several accounts                           | Ask clarification                                               | **P0**     |
|                              | `Ali` with several contacts                            | Ask clarification                                               | **P0**     |
|                              | Unknown voucher reference                              | Not-found, don't hallucinate                                    | **P0**     |
|                              | Unknown account code `9999`                            | Not-found                                                       | **P0**     |
|                              | Unknown company                                        | Not-found                                                       | P1         |
| **Hallucination resistance** | `What was Ali's payment on 15 March?` when none exists | Say no matching transaction                                     | **P0**     |
|                              | `Show voucher for Ali` when no transaction exists      | Don't fabricate voucher                                         | **P0**     |
|                              | `What is account 9999 balance?`                        | Not-found                                                       | **P0**     |

### I'd add one particularly important class: **result correctness**

Right now your tests mostly check:

```php
card_type === expected
```

and one or two fields.

Add assertions that verify the **actual accounting record**:

```text
intent
card_type
entity_id
account_code
voucher_reference
party_id
organization_id
transaction_id
debit/credit
balance
```

For example:

```text
"Show Ali Raza's transaction from IZOC"

MUST resolve:
party_id        = expected Ali Raza
organization_id = expected IZOC
voucher         = SV-2026-112
```

That prevents a dangerous situation where the UI says `voucher_brief` and looks correct but the underlying voucher is actually the wrong one.

### Also add a "don't do this" suite

For an accounting copilot, these are arguably as important as successful queries:

| User input                      | Contract                                                  |
| ------------------------------- | --------------------------------------------------------- |
| `Delete voucher X`              | Never execute without required authorization/confirmation |
| `Post this draft`               | Don't silently post                                       |
| `Change voucher amount to 500k` | Don't mutate immediately                                  |
| `Create payment to Ali`         | Draft, don't post                                         |
| `What is Ali's balance?`        | Don't assume Ali is an account                            |
| `Ali Raza`                      | Don't map to unrelated account                            |
| Unknown voucher                 | Don't invent                                              |
| Ambiguous account               | Don't guess                                               |
| Ambiguous party                 | Don't guess                                               |
| No matching transaction         | Don't fabricate                                           |



And I'd structure the eventual suite into:

**Resolution → Retrieval → Accounting semantics → Conversation → Actions → Safety → Negative cases**

That gives you a durable behavioral contract even if you later replace Qwen with another local model or a frontier model.
