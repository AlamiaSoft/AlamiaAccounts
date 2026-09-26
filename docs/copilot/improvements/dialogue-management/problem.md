# Problem Specification: Multi-Turn Dialogue Management, Entity Resolution & Conversational Routing for Alamia AI Copilot

---

## 1. Executive Summary & Objective

**Project**: Alamia Accounts — Multi-Tenant Enterprise Double-Entry Accounting System  
**Component**: AI Copilot (**Taliya**) & Semantic Capability Engine  
**Objective**: Evaluate and directly integrate a production-grade, open-source conversational dialogue management, multi-turn memory, and semantic routing framework.

### The Problem in Brief
Traditional single-turn intent classification and hand-crafted in-house heuristics (regex, string manipulation, naive turn concatenation) fail in real-world accounting dialogues involving:
- Pronoun / demonstrative resolution (anaphora & deixis: *"who is he?"*, *"that payment"*, *"its narration"*).
- Multi-turn conversational repair and disambiguation (*"no, not that one; there was another payment to Mr. Ali of IZOC Ltd"*).
- Entity alias normalization across ledger footprints (*"IZOC"* $\leftrightarrow$ *"Izoc Ltd"* $\leftrightarrow$ *"IZOC Pvt Ltd"*).
- Stateful dialogue routing across conversation rounds without losing tenant or accounting safety context.

**Directive**: We will **NOT** write ad-hoc conversational state machines or regex rules in-house. We need to identify, benchmark, and integrate an established, production-grade conversational routing/memory framework into our application stack.

---

## 2. System Architecture & Tech Stack

```
   ┌─────────────────────────────────────────────────────────────┐
   │                     Next.js / React UI                      │
   │      (Interactive Copilot Chat Drawer, Structured Cards)    │
   └──────────────────────────────┬──────────────────────────────┘
                                  │ HTTP / JSON API (X-Company-Code)
                                  ▼
   ┌─────────────────────────────────────────────────────────────┐
   │               Laravel 11 Backend (PHP 8.2)                  │
   │               Docker: alamia-accounts-backend               │
   │   ┌─────────────────────────────────────────────────────┐   │
   │   │  CopilotService (Capability Dispatcher & Executor)  │   │
   │   └──────────────────────────┬──────────────────────────┘   │
   └──────────────────────────────┼──────────────────────────────┘
                                  │
                                  ▼
   ┌─────────────────────────────────────────────────────────────┐
   │   TARGET: Production Dialogue / Router Framework            │
   │   (Python Sidecar / Embedded Service / Microservice)        │
   │   - Multi-Turn Dialogue Memory & Slot Tracking              │
   │   - Contextual Query Rewriting & Entity Resolution          │
   │   - Semantic Capability Routing (Trained / Round-Aware)     │
   │   - Interface: Local Ollama (qwen3.5:4b) / OpenAI API       │
   └─────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
   ┌─────────────────────────────────────────────────────────────┐
   │        Alamia Accounts PostgreSQL Double-Entry Core         │
   │        (Ledger, Vouchers, COA, Immutability Policies)       │
   └─────────────────────────────────────────────────────────────┘
```

### Current Tech Stack
- **Backend**: Laravel 11 / PHP 8.2 running in Docker container (`alamia-accounts-backend`).
- **Database**: PostgreSQL with tenant-partitioned ledger tables (`domain_journal_entries`, `domain_ledger_accounts`).
- **Inference Server**: Local Ollama (`qwen3.5:4b` default) or OpenAI-compatible endpoint (`AI_ENDPOINT`, `AI_MODEL`, `AI_API_KEY`).
- **Frontend**: Next.js 14, React, Tailwind CSS, shadcn/ui.

---

## 3. Strict Double-Entry & Institutional Invariants (Non-Negotiable)

Any integrated framework or routing layer must operate under the following absolute accounting invariants:

1. **Mathematical Balance Invariant**:
   - Every journal entry must strictly satisfy: $\sum \text{Debits} = \sum \text{Credits}$.
2. **Posting vs. Category Accounts**:
   - Category accounts (`category: true`, e.g., `1000 Assets`, `1120 Bank Accounts`) are grouping folders only. Transactions can **only** be posted to leaf accounts (`category: false`, e.g., `1130 Meezan Bank`).
3. **Multi-Tenant Isolation**:
   - Every lookup, entity search, and voucher operation must be strictly partitioned by tenant code (`X-Company-Code` / `domainUuid`). Zero cross-tenant data leakage.
4. **Historical Ledger Immutability (GAAP / IFRS)**:
   - Posted accounting records (vouchers, line items, amounts, narrations) are **never** updated or deleted in place.
   - Corrections require explicit compensating reversal workflows (`POST /api/vouchers/{ref}/reverse` generating `REV-` vouchers).
   - Destructive prompts (*"delete all accounts"*, *"change voucher amount to 500k"*, *"delete narration from OB-2026-001"*) must trigger immediate, explicit safety policy cards rather than executing or hallucinating mutations.
5. **No Blind Projections / Hallucinations**:
   - Unknown persons/contacts must never be arbitrarily mapped to unrelated chart of accounts (e.g. Cash or Inventory).
   - Unknown vouchers must return a verified `not_found` card, never fabricated details.

---

## 4. Detailed Failure Modes & The "Whack-a-Mole" Complexity Trap

During previous iterations using single-turn classifiers and in-house rule fallbacks, the system encountered repeated structural breakdowns:

### Failure Mode 1: Anaphora & Deixis (Pronoun Breakdown)
- **Dialogue Chain**:
  - *Turn 1 (User)*: `"Who is IZOC?"` $\to$ *Copilot*: Shows IZOC entity card.
  - *Turn 2 (User)*: `"Who is he?"` or `"What did we pay him?"`
- **Breakdown**: Single-turn intent models classify `"Who is he?"` in isolation without context, leading to unknown entity errors or defaulting to generic search.
- **Requirement**: The system must track active subjects across turns and perform **contextual query rewriting** (e.g., rewriting *"Who is he?"* $\to$ *"Who is the person associated with IZOC?"* or resolving *"him"* to the active party).

### Failure Mode 2: Conversational Repair & Disambiguation
- **Dialogue Chain**:
  - *Turn 1 (User)*: `"Show me what we have with Ali"` $\to$ *Copilot*: Finds no direct contact.
  - *Turn 2 (User)*: `"no; there was a transaction with Mr. Ali Raza of Izoc Ltd. i need to see its voucher"`
- **Breakdown**: Heuristic parsers struggle when a user combines negation (*"no"*) + qualification (*"Mr. Ali Raza of Izoc Ltd"*) + target entity action (*"i need to see its voucher"*). Substrings match competing intents (e.g. general transaction vs. specific voucher lookup).
- **Requirement**: True dialogue state modeling that recognizes conversational corrections, updates slot values, and determines the dominant target capability.

### Failure Mode 3: Dispersed Entity Footprints & Aliases
- In an accounting database, entity names appear in multiple formats and locations:
  - As formal clients: `"IZOC Pvt Ltd"`
  - In voucher narration strings: `"Web Development project for IZOC"`
  - In contact names: `"Ali Raza"`
- **Requirement**: An entity resolution layer with alias normalization and accounting ledger footprint inspection.

### Failure Mode 4: The Fragility of In-House Regex/Heuristic Engines
- Adding regex rules to catch `"what we have with [X]"` or `"who is this [X]"` inevitably collides with word boundaries, prepositions, or overlapping intents (*"What payment did Ali make through Meezan Bank?"* was incorrectly hijacked by the keyword *"Bank"* into an account balance inquiry).
- Fixing one edge case repeatedly regressed three others.

---

## 5. Frozen Semantic Capability Contract

The target dialogue management / routing solution must parse dialogue context into the following bounded schema:

### Semantic Output Schema
```json
{
  "capability": "account.balance | account.lookup | party.lookup | transaction.search | voucher.lookup | voucher.draft | voucher.reverse | report.trial_balance | report.profit_loss | report.balance_sheet | alerts.list | general.help | general.greeting | safety_policy | unknown",
  "arguments": {
    "account": "1130 | Meezan Bank | string | null",
    "party": "Ali Raza | string | null",
    "organization": "IZOC | IZOC Ltd | string | null",
    "reference": "OB-2026-001 | SV-2026-112 | string | null",
    "amount": 25000.0,
    "direction": "incoming | outgoing | any | null",
    "date_expression": "15 March | last month | null"
  },
  "requested_information": ["balance", "statement", "narration", "lines", "entity_profile", "status"],
  "safety_flag": "delete_all_accounts | delete_posted_voucher | mutate_posted_narration | mutate_posted_amount | execute_destructive_action | null"
}
```

### Registered Domain Capabilities
| Capability | Target Scope | Arguments Expected |
| :--- | :--- | :--- |
| `party.lookup` | Person, contact, vendor, or client inquiry | `party`, `organization` |
| `transaction.search` | Historical transaction lookup by party, org, direction | `party`, `organization`, `direction`, `requested_information` |
| `account.balance` | Account balance & COA positioning | `account` (code or name) |
| `account.lookup` | Chart of accounts search & classification | `account` query string |
| `voucher.lookup` | Voucher inspection by exact reference | `reference` |
| `voucher.draft` | Uncommitted double-entry draft preparation | `amount`, `account`, `party` |
| `voucher.reverse` | Compensating reversal (`REV-`) workflow | `reference` |
| `report.trial_balance` | Debit/Credit balance verification | `as_of_date` |
| `report.profit_loss` | Revenue and Expense statement | `from_date`, `to_date` |
| `report.balance_sheet` | Assets, Liabilities & Equity positions | `as_of_date` |
| `alerts.list` | Unresolved situations and anomalies | None |
| `safety_policy` | Immediate rejection of destructive/immutability violations | `safety_flag`, `reference` |

---

## 6. Candidate Frameworks Identified for Research & Benchmark

The research agent should evaluate candidate open-source frameworks against our architecture:

### Candidate 1: `LLMRouter` / `Router-R1` (`ulab-uiuc/LLMRouter`)
- **Key Focus**: Multi-round conversational routing.
- **Strengths**: Specifically designed for multi-turn dialogue routing with trained round-aware representations rather than static single-turn dispatch.
- **Research Question**: Can `LLMRouter` or `Router-R1` be deployed as a local lightweight routing service (e.g. Python FastAPI container or ONNX runtime) interfacing with our Laravel backend?

### Candidate 2: `ai-assistant-framework`
- **Key Focus**: Short-term memory, entity resolution, alias handling, query rewriting, and contextual graph expansion.
- **Strengths**: Explicit memory models addressing anaphoric references (*"she"*, *"that payment"*), aliases, and contextual expansion.
- **Research Question**: How does its entity resolution and short-term memory architecture integrate with relational database footprints (PostgreSQL ledger)?

### Candidate 3: Other Production-Grade Dialogue & Routing Frameworks
- Any other mature, open-source frameworks specifically tailored for:
  - Multi-turn conversational slot filling / task-oriented dialogue.
  - Anaphora resolution and contextual query rewriting.
  - Low-latency local deployment (compatible with Docker / PHP backend / Ollama LLMs).

---

## 7. Evaluation & Benchmark Criteria for Research Agent

When shortlisting and evaluating candidates, assess each on:

| Evaluation Dimension | Description & Target Metric |
| :--- | :--- |
| **1. Multi-Turn Context & Memory** | How reliably does it resolve conversational pronouns (`he`, `she`, `that voucher`, `its narration`) and conversational corrections across $\ge 5$ dialogue rounds? |
| **2. Integration Friction with PHP/Laravel** | Can it run as a lightweight containerized sidecar (e.g. FastAPI / gRPC / JSON-RPC over HTTP) communicating seamlessly with Laravel 11? |
| **3. Inference Efficiency & Local LLM Support** | Does it function efficiently with local small models (e.g., `qwen3.5:4b`, `llama3.2:3b`, or small embedding routers) without mandatory external cloud API dependencies? |
| **4. Guardrail & Policy Determinism** | Does it allow deterministic safety policy overrides (e.g. blocking destructive voucher deletions before routing to arbitrary LLM completions)? |
| **5. Entity & Alias Normalization** | How well does it handle entity alias graphs and fuzzy matches against local database records? |

---

## 8. Existing Benchmark & Contract Test Suite

The repository contains an automated 42-scenario behavioral contract and safety test suite:
- **Location**: [`tests/copilot/verify_copilot_intents.php`](file:///e:/Alamia/AlamiaAccounts/tests/copilot/verify_copilot_intents.php)
- **Execution**: `docker exec alamia-accounts-backend php tests/copilot/verify_copilot_intents.php`
- **Current Baseline**: 42/42 (100%) passing on current schema contract.

Any candidate solution must be capable of fulfilling or exceeding this 42-scenario contract suite while cleanly resolving multi-turn conversational history.
