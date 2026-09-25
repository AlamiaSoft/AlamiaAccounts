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

> [!IMPORTANT]
> **Direct Integration Directive**: Rather than reinventing custom ad-hoc routing and memory algorithms from scratch, the system will evaluate and directly integrate a proven, production-grade conversational routing and context resolution framework into the Alamia Accounts architecture.

### Candidate Frameworks for Direct Integration:

| Framework / Solution | Core Strengths | Target Role in Alamia Accounts |
| :--- | :--- | :--- |
| **`LLMRouter` / `Router-R1`** (`ulab-uiuc/LLMRouter`) | Multi-round conversational routing, trained round-aware representations | Multi-turn capability dispatcher and dialogue round manager. |
| **`ai-assistant-framework`** | Explicit short-term memory, alias registries, query rewriting, contextual graph expansion | Contextual reference resolver (`she`, `that payment`, `IZOC Pvt Ltd` $\leftrightarrow$ `Ali Raza`). |

### Next Session Action Plan:
1. **Benchmark & Feasibility Evaluation**: Run direct feasibility evaluations on candidate repositories (`LLMRouter` and `ai-assistant-framework`) against Alamia's domain capability schema and 42-test safety contract.
2. **Direct Integration Architecture**: Deploy the chosen solution as a containerized routing/memory service or native service adapter integrated with [`CopilotService.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/app/Copilot/CopilotService.php).
3. **End-to-End Safety & Invariant Verification**: Wire all resolved intents and entity contexts directly into the double-entry accounting layer while maintaining 100% test pass rate across the contract suite.


