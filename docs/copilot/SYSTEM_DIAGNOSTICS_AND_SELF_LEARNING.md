# AI Copilot System Diagnostics, Telemetry Observability & Dynamic Self-Learning Architecture

**Author**: Alamia Engineering Team  
**Scope**: Universal AI Copilot Observability, Developer Evaluation Harness, and Zero-Code Knowledgebase Self-Learning  
**Status**: Production Standard (Certified across 79/79 Intent Suites & 7/7 Diagnostics Suites)

---

## 1. Executive Overview

Production AI assistants in mission-critical domains (such as Double-Entry Accounting, ERP, and Financial Systems) cannot operate as black boxes. When an assistant encounters edge cases, phrasing gaps, or complex corporate policies, developers and domain experts need:

1. **Deterministic Observability**: Immediate, structured visibility into what the user asked, how the classifier routed the turn (LLM vs Parlant vs Heuristic), which arguments and safety guardrails were evaluated, and the exact response payload rendered.
2. **Offline Evaluation & Agent Hardening**: Standardized dataset export (JSON, JSONL, CSV) enabling AI developer agents to benchmark model drift, run regression suites, and fine-tune classifier prompts.
3. **Zero-Code Self-Learning**: The ability to promote real production queries into permanent, database-backed guidance rules that take effect **immediately without code deployment**.

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                         COPILOT INTERACTION LIFECYCLE                            │
└──────────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
                           [User Natural Prompt]
                                      │
                   ┌──────────────────┴──────────────────┐
                   ▼                                     ▼
        [1. Dynamic Knowledgebase]              [2. Dialogue Engine / LLM]
        • Trigger phrase match                  • Intent Classification
        • Topic rule lookup                     • Multi-turn reference resolution
        • Instant guidance response             • Safety policy checks
                   │                                     │
                   └──────────────────┬──────────────────┘
                                      │
                                      ▼
                        [3. Execution & Response Card]
                                      │
                                      ▼
                       [4. Telemetry Capture Engine]
                    • Prompt, Session, Latency (ms)
                    • Classifier mode, Intent, Confidence
                    • Context state before/after turn
                    • Rendered UI payload & Actions
                                      │
                                      ▼
                    [5. Developer Diagnostics Dashboard]
            ┌─────────────────────────┴─────────────────────────┐
            ▼                                                   ▼
  [Developer Annotation & Review]                     [Promote to Knowledge Base]
  • Status: Verified / Needs Fix                      • Trigger phrases
  • Issue documentation                               • Workflow steps & Notes
  • JSONL Dataset Export                              • Deep-link ERP actions
                                                                │
                                                                ▼
                                                   [Instant Database Update]
                                                   (Zero code changes needed!)
```

---

## 2. Core Architecture & Components

### 2.1 Database Schema

#### 1. Telemetry Log Table: `copilot_diagnostic_logs`
Captures full request-response trajectories with execution latencies:

| Column | Type | Purpose |
|:---|:---|:---|
| `id` | `BIGINT UNSIGNED` (PK) | Unique trace identifier |
| `session_id` | `VARCHAR(100)` | Conversational session grouping |
| `company_code` | `VARCHAR(50)` | Multi-tenant domain context (`MAIN`, `ALAMIASOFT`, etc.) |
| `user_name` | `VARCHAR(100)` | Initiating actor |
| `prompt` | `TEXT` | Raw user input string |
| `classifier_mode` | `VARCHAR(50)` | Decision engine: `ollama`, `parlant`, `heuristic`, `direct_action` |
| `classifier_intent` | `VARCHAR(100)` | Primary intent / capability name |
| `classifier_confidence`| `DECIMAL(4,2)` | Confidence score ($0.00$ to $1.00$) |
| `classifier_output` | `JSON` | Structured argument extraction & entity candidates |
| `context_before` | `JSON` | Dialogue history and conversational entities before turn |
| `context_after` | `JSON` | Dialogue state after turn |
| `safety_evaluations` | `JSON` | Safety guardrail results (e.g., Immutability, Temporal checks) |
| `dispatched_action` | `VARCHAR(100)` | Executed backend capability |
| `execution_result` | `JSON` | Capability return payload |
| `final_response` | `JSON` | User-facing markdown text, card type, and interactive actions |
| `duration_ms` | `INT UNSIGNED` | Total round-trip execution latency in milliseconds |
| `status` | `ENUM` | Review status: `unreviewed`, `verified`, `needs_fix`, `promoted_to_kb` |
| `developer_notes` | `TEXT` | Developer annotation and gap explanation |

#### 2. Dynamic Knowledgebase Table: `copilot_knowledge_entries`
Stores custom operational guidance rules evaluated at runtime:

| Column | Type | Purpose |
|:---|:---|:---|
| `id` | `BIGINT UNSIGNED` (PK) | Knowledge rule ID |
| `company_code` | `VARCHAR(50)` (Nullable)| Scoped tenant code (`null` = Universal for all tenants) |
| `topic` | `VARCHAR(100)` | Topic slug (e.g. `petty_cash_policy`, `account_ledger_activity`) |
| `trigger_keywords` | `JSON` | Array of trigger phrases / keywords for runtime matching |
| `domain` | `VARCHAR(50)` | Module classification (`voucher`, `coa`, `reports`, `periods`, `general`) |
| `title` | `VARCHAR(255)` | User-facing guidance heading |
| `summary` | `TEXT` | Operational explanation / policy summary |
| `steps` | `JSON` | Sequential step-by-step instructions |
| `note` | `TEXT` | Compliance or maker-checker authorization note |
| `actions` | `JSON` | Quick-action button definitions (`label`, `action`, `payload`) |
| `source_diagnostic_id`| `BIGINT UNSIGNED` (Nullable)| Foreign key linking rule to the trace that originated it |
| `is_active` | `BOOLEAN` | Active toggle for dynamic rule evaluation |

---

## 3. Dynamic Knowledgebase Self-Learning Engine

### How It Works (`GuidanceKnowledgeService.php`)

When a user submits a prompt, `CopilotService` queries `GuidanceKnowledgeService::matchGuidance()` before running heavy LLM or heuristic dispatchers:

1. **Database Match**: The engine checks active `copilot_knowledge_entries` matching the active tenant or universal scope (`company_code IS NULL`).
2. **Trigger Score Ranking**: Triggers are scored using exact phrase containment and token overlap.
3. **Template Resolution**: If a match is found, the guidance card is compiled with:
   - Topic header & summary
   - Numbered step-by-step workflow
   - Formatted compliance note
   - Interactive deep-link action buttons (`navigate_page`, `draft_prompt`)
4. **Instant Zero-Code Fix**: Once saved in the database, matching queries resolve the updated answer immediately.

---

## 4. API Endpoints Reference

| Endpoint | Method | Purpose |
|:---|:---:|:---|
| `/api/copilot/diagnostics` | `GET` | List traces with pagination, status filters, search, and KPI aggregate stats |
| `/api/copilot/diagnostics/{id}` | `GET` | Retrieve full trace details with before/after state and safety evaluations |
| `/api/copilot/diagnostics/{id}/feedback` | `PATCH` | Update developer review status (`verified`, `needs_fix`) and technical notes |
| `/api/copilot/diagnostics/{id}/promote-to-guidance` | `POST` | Convert a diagnostic trace into a permanent self-learning knowledge rule |
| `/api/copilot/diagnostics/export` | `GET` | Stream dataset in **JSONL**, **JSON**, or **CSV** format for AI eval & fine-tuning |
| `/api/copilot/diagnostics` | `DELETE` | Prune historical telemetry logs by date or company filter |
| `/api/copilot/knowledge` | `GET` | List active self-learned guidance rules across all or specific tenants |
| `/api/copilot/knowledge` | `POST` | Create a new custom guidance rule directly |
| `/api/copilot/knowledge/{id}` | `DELETE` | Remove/deactivate a self-learned knowledge rule |

---

## 5. Blueprint: Replicating this Pattern in Future Copilots

To implement this exact telemetry and self-learning architecture in any other AI assistant (e.g., Inventory Copilot, HR Copilot, CRM Copilot):

### Step 1: Add the Diagnostic & Knowledge Migrations
Create the two database tables (`copilot_diagnostic_logs` and `copilot_knowledge_entries`) following the schema defined in Section 2.

### Step 2: Implement Non-Blocking Telemetry Hook
In the assistant's central chat handler (`CopilotService::handleChat`):
```php
$startTime = microtime(true);
$response = $this->dispatchCapability($prompt, $context);
$this->logDiagnosticTrace($sessionId, $tenant, $prompt, $mode, $intent, $confidence, $args, $beforeState, $afterState, $safety, $action, $response, $startTime);
return $response;
```

> [!IMPORTANT]
> Always wrap `logDiagnosticTrace` in a `try/catch` block so telemetry logging **never interrupts the conversational user experience** if a database lock or write error occurs.

### Step 3: Implement Dataset Export (`JSONL` / `CSV`)
Provide a streaming export endpoint:
```php
$records = CopilotDiagnosticLog::where($filters)->get();
foreach ($records as $r) {
    $lines[] = json_encode([
        'user_prompt' => $r->prompt,
        'classifier' => ['intent' => $r->classifier_intent, 'confidence' => $r->classifier_confidence],
        'final_output' => $r->final_response,
        'evaluation' => ['status' => $r->status, 'notes' => $r->developer_notes],
    ]);
}
return implode("\n", $lines);
```

### Step 4: Build the UI Inspector & Full-Screen Maximize Drawer
Implement the interactive telemetry dashboard with:
- Clickable KPI filter cards (Total, Avg Latency, Verified Quality, Flagged for Fix, In Knowledge Base, Pending Review).
- Trace Table with latency badges and review status pills.
- Full-width resizable Inspector modal with tabs:
  - 📟 **Replay & Output**: Markdown message, card rendered, buttons shown.
  - 🧠 **Classification & Logic**: Intent, confidence %, safety flags, arguments JSON.
  - 🔄 **State & Decay**: Pre-turn and post-turn dialogue memory.
  - ✅ **Developer Review**: Status dropdown, note editor, and one-click "Promote to Knowledge Base" button.

---

## 6. Verification Suite

The diagnostic and self-learning system is verified continuously by automated test suites:

- **Telemetry & Self-Learning Suite**: [`verify_diagnostics_and_self_learning.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/tests/copilot/verify_diagnostics_and_self_learning.php) (**7/7 PASS**).
- **Core Behavioral & Safety Contract Suite**: [`verify_copilot_intents.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/tests/copilot/verify_copilot_intents.php) (**79/79 PASS**).
