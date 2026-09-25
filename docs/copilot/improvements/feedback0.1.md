| Area                                  | Current issue                                                                                                                                                            | Recommended fix                                                                                                                                                       | Priority |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| **Search architecture**               | `searchVouchers()` loads **all vouchers** then filters in PHP. This will degrade badly as data grows.                                                                    | Move search into DB/query layer. Search voucher headers + journal lines with indexed fields; only hydrate matching vouchers.                                          | **P0**   |
| **Party search**                      | `searchTransactionsByParty()` searches only description/reference/memo/account name. It does **not actually resolve a Party/Contact/Organization entity**.               | Add explicit party/contact/org resolution first, then search transactions using their IDs/codes. Text matching should be fallback, not primary mechanism.             | **P0**   |
| **Core failure in Ali Raza example**  | `"Ali Raza"` can accidentally match an accounting account/name such as Inventory through downstream search/routing.                                                      | Once classifier returns `party`/`organization` + `FIND_TRANSACTION`, **never route to `searchAccounts()`**. Route deterministically to `searchTransactionsByParty()`. | **P0**   |
| **Party + organization relationship** | Current search treats party and organization independently. It can return a transaction matching Ali Raza **or** IZOC rather than the relationship **Ali Raza of IZOC**. | Resolve both entities and require the transaction to match the party/org relationship where available. Otherwise score matches and expose why matched.                | **P0**   |
| **Voucher target**                    | `FIND_TRANSACTION` correctly identifies the user's semantic goal, but there is no explicit transaction → voucher resolution layer.                                       | Add `findTransactionCandidates()` → rank candidates → `getVoucher($id)` / voucher detail tool.                                                                        | **P0**   |
| **Conversation correction**           | Classifier has `is_correction`, but SearchService doesn't use conversational correction context.                                                                         | On correction, preserve previous entities and merge/replace only what the user corrected. E.g. `Ali Raza` + new `IZOC Ltd` + target `voucher`.                        | **P0**   |
| **Classifier context**                | Only last 3 messages, each truncated to 100 chars. This can lose critical context.                                                                                       | Pass structured prior intent/entities separately from raw history. Raw history should be supplementary.                                                               | **P0**   |
| **Classifier schema**                 | `entity` is derived with `party ?? organization ?? reference...`; because empty strings aren't null, fallback selection can be wrong.                                    | Explicitly choose first **non-empty** entity. Better: remove generic `entity` and use typed fields.                                                                   | **P1**   |
| **Intent granularity**                | `FIND_TRANSACTION` covers transaction, voucher, invoice, payment, receipt.                                                                                               | Keep it broad at intent level, but add `target_object` / `transaction_type` / `action` fields. E.g. `find + voucher + payment`.                                       | **P1**   |
| **Intent → tool routing**             | Architecture appears vulnerable to letting search results determine the next semantic action.                                                                            | Make routing deterministic: `FIND_TRANSACTION → transaction search`; `INQUIRE_ACCOUNT → account search`; `INQUIRE_VOUCHER → voucher search`.                          | **P0**   |
| **Search ranking**                    | Current matching is essentially boolean `str_contains()`. First 50 wins.                                                                                                 | Implement relevance scoring: exact party > exact org > exact phrase > token match > memo/description > account name. Return score/reason.                             | **P0**   |
| **Party token matching**              | `"Ali Raza"` requires all tokens but allows them anywhere, creating false positives.                                                                                     | Prefer normalized exact/full-name matching; then token matching with proximity/field weighting.                                                                       | **P1**   |
| **Organization normalization**        | `preg_replace()` removes `Ltd`, `Inc`, etc. but can create ambiguous names. `"ABC Ltd"` → `"ABC"`.                                                                       | Normalize legal suffixes for comparison but retain original name; use entity resolution rather than substring matching.                                               | **P1**   |
| **Person normalization**              | Removes Mr/Mrs/etc., good idea, but doesn't normalize punctuation/spacing/diacritics.                                                                                    | Centralize `normalizePersonName()` and `normalizeOrganizationName()` utilities.                                                                                       | **P1**   |
| **Entity resolution**                 | No fuzzy/entity lookup appears to exist.                                                                                                                                 | Add resolver: exact → normalized → aliases → fuzzy candidates. LLM should extract `"Ali Raza"`; application should determine which Ali Raza.                          | **P0**   |
| **Ambiguous matches**                 | Search can silently return unrelated records.                                                                                                                            | Return `matches`, `confidence`, and `match_reasons`; if multiple plausible parties exist, ask the user to choose.                                                     | **P0**   |
| **Search result contract**            | Methods return raw arrays with different shapes.                                                                                                                         | Define typed/consistent result DTOs: `entity`, `match_type`, `score`, `reason`, `id`, `label`, `action`.                                                              | **P1**   |
| **Voucher search fields**             | Voucher search checks many fields, but no normalization beyond lowercase.                                                                                                | Normalize whitespace/punctuation and use database/full-text/indexed search where appropriate.                                                                         | **P1**   |
| **Ledger search**                     | `searchLedgerEntries()` first finds vouchers, then expands **all lines** from those vouchers.                                                                            | If user asks for ledger entries, query journal lines directly. Don't use voucher search as an intermediate hack.                                                      | **P1**   |
| **Global search**                     | Executes voucher search + account search + ledger search; ledger search itself executes voucher search again.                                                            | Avoid duplicated work. Build a unified search/index or independently query each entity with bounded results.                                                          | **P1**   |
| **Global search semantics**           | `"Ali Raza"` produces account results even when the user clearly asks about a person/transaction.                                                                        | `globalSearch()` should be generic only. Copilot intent should select domain-specific search instead.                                                                 | **P0**   |
| **50-result limit**                   | `take(50)` occurs after potentially scanning everything.                                                                                                                 | DB-level `LIMIT`; rank before limiting.                                                                                                                               | **P1**   |
| **Date search**                       | Date matching is plain substring matching.                                                                                                                               | Parse natural dates/ranges (`last month`, `in March`, `25 Sep`) into structured filters.                                                                              | **P2**   |
| **Reference search**                  | Good basic support for voucher references.                                                                                                                               | Normalize reference format and prioritize exact reference matches.                                                                                                    | **P1**   |
| **Classifier JSON**                   | Good constrained schema and `temperature=0`; `format=json` is appropriate.                                                                                               | Add explicit examples for ambiguous/corrective queries and require **typed extraction, not database guesses**.                                                        | **P1**   |
| **Classifier prompt**                 | It says `"show voucher for Ali"` = `FIND_TRANSACTION`, which is good.                                                                                                    | Add examples: `"What did we post to Ali?"`, `"Find Ali's payment"`, `"Ali from IZOC"` and `"No, I mean the transaction..."`.                                          | **P1**   |
| **Classifier confidence**             | Model-generated confidence isn't necessarily meaningful/calibrated.                                                                                                      | Treat confidence as advisory. Validate output structurally and let resolver/search confidence determine actual certainty.                                             | **P1**   |
| **Heuristic correction**              | `$isCorrection` is true for **any prompt starting with `no`**, then immediately forces `FIND_TRANSACTION`.                                                               | Don't let `"no"` determine intent. Use correction only as a conversation-state flag; classify the corrected request normally.                                         | **P0**   |
| **Heuristic extraction**              | Regex extraction of party/org is fragile for natural language.                                                                                                           | Keep heuristic fallback minimal; use deterministic patterns for references/codes, but don't rely heavily on regex for names.                                          | **P1**   |
| **Heuristic ordering**                | `FIND_TRANSACTION` is checked before voucher-reference, reports, drafting, etc.                                                                                          | Order by highly deterministic signals first: reference/code → report → draft → transaction → account → general.                                                       | **P1**   |
| **Draft voucher conflict**            | `"Paid 25,000 to Ali"` could mean draft a voucher, but transaction search checks `"payment to"` first.                                                                   | Drafting should take precedence when the user describes a **new financial action + amount**.                                                                          | **P0**   |
| **Dependency injection**              | Nullable constructor dependencies + `app()` fallback is workable but unnecessarily ambiguous.                                                                            | Prefer required constructor injection unless there is a strong reason for optional dependencies.                                                                      | **P2**   |
| **Unused imports**                    | `LedgerAccount`, `LedgerDomain`, `DomainLedgerAccount`, `DomainJournalEntry`, `DB` appear unused.                                                                        | Remove unused imports.                                                                                                                                                | **P2**   |
| **Domain context**                    | Each method mutates global-ish `DomainContext`.                                                                                                                          | Ensure context is request-scoped and cannot leak between operations/jobs. Prefer explicit domain scoping where practical.                                             | **P1**   |

### Recommended target flow

| Step | Component              | Responsibility                                                                |
| ---- | ---------------------- | ----------------------------------------------------------------------------- |
| 1    | **Qwen classifier**    | Extract `intent + party + organization + reference + account + target_object` |
| 2    | **Conversation state** | Merge correction/context without guessing                                     |
| 3    | **Intent router**      | Deterministically select domain capability                                    |
| 4    | **Entity resolver**    | `Ali Raza` → actual contact/party ID; `IZOC Ltd` → organization ID            |
| 5    | **Transaction search** | Search transactions using IDs + structured filters                            |
| 6    | **Ranker**             | Exact/relationship/date/reference relevance                                   |
| 7    | **Voucher resolver**   | Transaction ID → source voucher                                               |
| 8    | **Copilot**            | Explain/show result and offer next action                                     |

### Most important architectural change

```text
CURRENT

User
 ↓
Qwen
 ↓
generic SearchService
 ↓
accounts / vouchers / ledger
 ↓
possibly wrong entity
```

```text
TARGET

User
 ↓
Qwen
 ↓
{ intent: FIND_TRANSACTION,
  party: "Ali Raza",
  organization: "IZOC Ltd",
  target_object: "voucher" }
 ↓
Intent Router
 ↓
Party/Org Resolver
 ↓
Transaction Resolver
 ↓
Voucher
```

**The biggest fix is not better prompting or a larger model.** It's making the application layer understand that **“Ali Raza of IZOC Ltd” is an entity relationship and “show its voucher” is an action on the resulting transaction**.
