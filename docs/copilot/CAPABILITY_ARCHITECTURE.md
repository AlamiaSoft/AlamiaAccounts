# Copilot Capability Architecture & Semantic Dispatch Pipeline

## Overview
The Alamia Accounts AI Copilot (**Taliya**) operates on a bounded **Semantic Capability Architecture**. Rather than attempting open-ended intent proliferation or fragile regex classification, user natural language is parsed into a **bounded capability request with typed arguments**, which is then dispatched to discrete application domain tools.

---

## 1. Architectural Pipeline

```
                 USER QUERY
                     │
                     ▼
       ┌───────────────────────────┐
       │   Semantic Capability     │
       │         Parser            │
       │    (Ollama Qwen 3.5)      │
       └─────────────┬─────────────┘
                     │
         capability + arguments
                     │
                     ▼
       ┌───────────────────────────┐
       │   Capability Dispatcher   │
       └─────────────┬─────────────┘
                     │
         ┌───────────┴───────────┐
         ▼                       ▼
 ┌───────────────┐       ┌───────────────┐
 │ Entity/Party  │       │ Safety Policy │
 │   Resolver    │       │  Guardrails   │
 └───────┬───────┘       └───────┬───────┘
         │                       │
         └───────────┬───────────┘
                     ▼
       ┌───────────────────────────┐
       │    Domain Capabilities    │
       │ (Ledger, Vouchers, COA)   │
       └─────────────┬─────────────┘
                     │
                     ▼
       ┌───────────────────────────┐
       │  Structured Card Response │
       └───────────────────────────┘
```

---

## 2. Frozen Semantic Request Schema

```json
{
  "capability": "account.balance | account.lookup | party.lookup | transaction.search | voucher.lookup | voucher.draft | voucher.reverse | report.trial_balance | report.profit_loss | report.balance_sheet | alerts.list | general.help | general.greeting | unknown",
  "arguments": {
    "account": "extracted account name or code",
    "party": "extracted person contact name (stripped of deictic prefixes)",
    "organization": "extracted organization / vendor / client name",
    "reference": "extracted voucher reference (e.g. OB-2026-001, SV-2026-112)",
    "amount": 25000.0,
    "direction": "incoming | outgoing | any",
    "date_expression": "extracted natural date phrase (e.g. '15 March', 'last month')"
  },
  "requested_information": ["reason", "created_by", "date", "amount", "account", "balance"],
  "safety_flag": "destructive_account | destructive_voucher | mutate_ledger | mutate_narration | null"
}
```

---

## 3. Registered Domain Capabilities

| Capability | Scope / Purpose | Resolved Arguments | Output Card Type |
| :--- | :--- | :--- | :--- |
| `party.lookup` | Person, contact, vendor, or client inquiry | `party`, `organization` | `entity_brief` / `not_found` |
| `transaction.search` | Historical transaction lookup by party, direction, or reason | `party`, `organization`, `direction`, `requested_information` | `voucher_brief` / `disambiguation` / `not_found` |
| `account.balance` | Account balance & COA positioning | `account` (code or name) | `account_brief` / `disambiguation` / `not_found` |
| `account.lookup` | Chart of accounts search | `account` query string | `account_brief` / `disambiguation` |
| `voucher.lookup` | Voucher inspection by reference | `reference` | `voucher_brief` / `not_found` |
| `voucher.draft` | Uncommitted double-entry draft preparation | `amount`, `account`, `party` | `voucher_draft` |
| `voucher.reverse` | Compensating reversal (`REV-`) workflow | `reference` | `voucher_action` |
| `report.trial_balance` | Debit/Credit balance verification | `as_of_date` | `financial_report` |
| `report.profit_loss` | Revenue and Expense statement | `from_date`, `to_date` | `financial_report` |
| `report.balance_sheet` | Assets, Liabilities & Equity positions | `as_of_date` | `financial_report` |
| `alerts.list` | Unresolved situations and anomalies | None | `situations_list` |
| `general.greeting` | Conversational welcome | None | `greeting` |
| `general.help` | Capability guidance & examples | None | `help` |

---

## 4. Layered Entity Resolution Principles

1. **Classifier Extracts Tokens, Resolver Determines Identity**:
   - The classifier extracts candidate tokens (e.g., `IZOC`, `Ali Raza`).
   - The application entity resolver searches contacts, users, and the transaction ledger to determine whether the entity is a registered person or an active corporate client.
2. **Deictic Reference Stripping**:
   - Expressions such as `"Who is this IZOC???"` or `"Tell me about that client"` normalize the entity token (`IZOC`), stripping leading determiners.
3. **Conversational Pronoun Inheritance**:
   - Deictic pronouns (`he`, `she`, `this`, `that`, `this company`) look up immediately preceding conversation turns to resolve referenced entities.
4. **Accounting Ledger as Entity Evidence**:
   - When entities are not explicitly recorded in a separate contacts table, the resolver inspects voucher narrations, memos, and line item legs to establish their accounting footprint.

---

## 5. Institutional Safety Guardrails (Non-Negotiable)

1. **Ledger Immutability**:
   - Requests to modify posted transaction amounts in place are rejected with policy `LEDGER_ENTRY_IMMUTABILITY`. Adjustments require a compensating voucher (`REV-` or adjusting `JV-`).
2. **Narration Immutability**:
   - Requests to edit or delete posted narrations in place are rejected with policy `VOUCHER_DESCRIPTION_IMMUTABILITY`.
3. **Historical Ledger Preservation**:
   - Requests to delete posted vouchers return policy `HISTORICAL_LEDGER_IMMUTABILITY` and offer reversal workflows.
4. **Chart of Accounts Protection**:
   - Requests to bulk delete or wipe accounts are rejected with policy `CHART_OF_ACCOUNTS_PROTECTION`.
5. **No Blind Account Projections**:
   - When a person or contact name is queried, the copilot never arbitrarily projects the name onto an unrelated ledger account (e.g. Cash or Inventory).

---

## 6. Next Session Roadmap: Production-Grade Framework Integration (`feedback0.1.11.md`)

### Problem Statement: Why Direct Integration of a Production-Grade Solution is Required

1. **The Multi-Turn Dialogue Breakdown in Single-Turn Models**:
   - Traditional intent classifiers evaluate each prompt in isolation. Real accounting conversations, however, form continuous dialogue chains involving:
     - **Anaphoric & Deictic References**: Pronouns (*"he"*, *"she"*, *"they"*, *"it"*) and demonstratives (*"that payment"*, *"the last voucher"*, *"that client"*).
     - **Conversational Corrections**: User repairs (*"no, not that one; there was another payment to Mr. Ali of IZOC"*).
     - **Entity Aliasing**: Dispersed naming variants (*"IZOC"*, *"Izoc Ltd"*, *"IZOC Pvt Ltd"*, *"Ali Raza"*) across ledgers, memos, and contact records.
2. **The "Whack-a-Mole" Complexity Trap of In-House Heuristics**:
   - Attempting to handle multi-turn conversational nuance through custom regexes, substring filters, and ad-hoc history scanners creates an exponential edge-case space. Each new heuristic fix risks regressing previous conversational paths.
3. **Separation of Dialogue Modeling from Accounting Invariants**:
   - The Copilot must strictly uphold double-entry balance, ledger immutability, and tenant boundaries. Decoupling dialogue state modeling (handled by a dedicated framework) from domain capability execution (handled by Alamia's double-entry core) ensures robust conversational fluency without risking ledger safety.

---

### Framework Selection & Architecture: **Parlant (`parlant`)**

Following research evaluation ([`research-and-recommednation.md`](file:///e:/Alamia/AlamiaAccounts/docs/copilot/improvements/dialogue-management/research-and-recommednation.md)), **Parlant** is adopted as the production-grade dialogue management and behavioral control engine:

| Requirement | Parlant Architecture in Alamia Accounts |
| :--- | :--- |
| **Sidecar Deployment** | Runs as a Python standalone service (`p.Server`) on `localhost:8800`, called by Laravel backend over REST/HTTP. |
| **Local LLM Execution** | Native integration via `parlant[ollama]` using `qwen3.5:4b` without external cloud dependencies. |
| **Anaphora / Deixis** | Sessions maintain an ordered **event timeline** (messages, tool calls, results). Contextual pronouns (*"he"*, *"she"*, *"that payment"*, *"its narration"*) are grounded naturally in prior events. |
| **Conversational Repair** | Managed through **Guidelines** (`condition -> action`) and **Journeys** to steer dialog steps on corrections (*"no, not that one..."*) without fragile regexes. |
| **Deterministic Guardrails** | Pre-generation guidelines strictly enforce GAAP/IFRS ledger immutability and block destructive actions (`safety_flag`) before execution. |
| **Capability Tools** | Direct 1:1 mapping of domain capabilities (`party.lookup`, `voucher.draft`, `voucher.reverse`, `report.*`) to Parlant `@p.tool` decorators. |

### Dedicated Entity Resolution Tool Architecture:
- Parlant Glossary manages static domain terms.
- For dynamic accounting entity aliases across tenant ledgers (`"IZOC"` $\leftrightarrow$ `"IZOC Ltd"` $\leftrightarrow$ `"Ali Raza"`), Alamia provides a dedicated `resolve_entity` tool using PostgreSQL `pg_trgm` fuzzy matching against `domain_contacts`, `domain_ledger_accounts`, and `domain_journal_entries`.
- Parlant guidelines invoke `resolve_entity` before executing `party.lookup` or `transaction.search`.

---

### Implementation Action Plan:
1. **Parlant Sidecar Setup**: Configure Docker container running `parlant` with local Ollama (`qwen3.5:4b`).
2. **Define Guidelines & Tools**: Register domain capability tools and safety guidelines matching our frozen schema.
3. **Build `resolve_entity` Backend Endpoint**: Implement PostgreSQL trigram fuzzy search for entity footprints and aliases.
4. **Benchmark Against Contract Suite**: Validate the Parlant integration against the 42-scenario test suite in [`tests/copilot/verify_copilot_intents.php`](file:///e:/Alamia/AlamiaAccounts/tests/copilot/verify_copilot_intents.php).




