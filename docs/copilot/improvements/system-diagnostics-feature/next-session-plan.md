# System Diagnostics & Self-Learning Hardening — Task Sheet

**Target Document**: [`docs/copilot/improvements/system-diagnostics-feature/review.md`](file:///e:/Alamia/AlamiaAccounts/docs/copilot/improvements/system-diagnostics-feature/review.md)  
**Priority**: Highest (P0 Sprint)  
**Status**: ✅ Workstreams 1–4 Implemented & Certified | Workstreams 5–6 Planned

---

## 1. Workstream Breakdown & Task List

### Workstream 1: Deterministic Deep-Link Noise Pollution Elimination (Addendum P0) ✅
- [x] **TASK-DIAG-01: Tag Entry Point Classifier Mode**
  - When a query is initiated via pre-resolved UI links (Global Search "Ask Copilot", Card Buttons, Quick Prompts), detected via `context.type` in `handleChat()` and tagged with `entry_point: 'deep_link'` in diagnostic trace.
  - Direct actions (`post_voucher`, etc.) also tagged with `entry_point: 'deep_link'`.
  - New `entry_point` column added to `copilot_diagnostic_logs` table.
- [x] **TASK-DIAG-02: Filter Noise from Review Queues**
  - `getStats()` excludes `deep_link` and `pre_resolved` entries from `unreviewed_count` KPI.
  - `listTraces()` supports `entry_point` filter parameter.
  - Frontend anomaly filter uses `anomaly_flag=any` to query backend directly.
- [x] **TASK-DIAG-03: Suppress Promotion on Deep Links**
  - "Promote to Knowledge Base" button hidden in trace table rows and Inspector feedback tab for `entry_point === 'deep_link'` and `classifier_mode === 'direct_action'`.
  - Backend `promoteToKnowledge()` throws `InvalidArgumentException` if trace has deep_link entry_point.
- [x] **TASK-DIAG-04: Test Deep-Link Tagging & Isolation**
  - Verified via 79/79 intent tests + 7/7 diagnostics tests + clean Next.js build.

---

### Workstream 2: Knowledgebase Promotion Governance & Safety Gates (Review #1 P0) ✅
- [x] **TASK-GOV-01: RBAC & Reviewer Gating**
  - `promoted_by` field captures the authenticated user's name at promotion time.
  - Promotion audit trail: `promoted_by`, `promoted_at`, `change_reason`, `version` columns added to `copilot_knowledge_entries`.
- [x] **TASK-GOV-02: GAAP/IFRS Invariant Contradiction Validator**
  - Before activating a promoted guidance rule, `promoteToKnowledge()` validates trigger content against 3 dangerous regex patterns:
    1. Advising physical voucher/journal/ledger deletion
    2. Advising posting to category/folder/group accounts
    3. Advising bypassing maker-checker/dual-authorization/audit
  - Throws `InvalidArgumentException` with clear explanation if invariant violated.
- [x] **TASK-GOV-03: Rule Versioning, Staging, & Audit Trail**
  - Promotion audit logging: `promoted_by`, `promoted_at` (timestamp), `change_reason`, `version` (starts at 1).
  - Migration: `2026_09_26_000002_add_governance_and_anomaly_to_copilot_tables.php`.

---

### Workstream 3: Trigger-Keyword Collision & Shadowing Prevention (Review #2 P0) ✅
- [x] **TASK-COLL-01: Specificity & Token Floor**
  - `GuidanceKnowledgeService::getGuidance()` skips any trigger keyword with < 2 tokens during matching (prevents single-word triggers like "bank", "voucher" from shadowing primary capabilities).
  - `CopilotDiagnosticsService::promoteToKnowledge()` validates triggers before creating KB entry.
  - `CopilotController::knowledgeStore()` validates triggers on direct creation via API.
- [x] **TASK-COLL-02: Shadowing Collision Check**
  - Token floor enforcement prevents "bank" from shadowing "What is the balance of Meezan Bank?" (verified in 79/79 test suite — Test 73 Anti-Drift Parity).

---

### Workstream 4: Automated Anomaly Signals & Zero-Totals Flagging (Review #3 P1) ✅
- [x] **TASK-ANOM-01: Anomaly Flagging in Telemetry**
  - `CopilotService::detectAnomalyFlag()` auto-detects:
    - `suspicious_zero_report`: Financial reports with $0/$0 totals on tenants with known activity
    - `low_confidence`: Classifier confidence < 0.50
    - `high_latency`: Execution time > 3000ms
  - Anomaly flag stored in new `anomaly_flag` column on `copilot_diagnostic_logs`.
- [x] **TASK-ANOM-02: Diagnostics UI Anomaly Quick-Filter**
  - "⚠️ Anomaly Flagged" filter chip added to status dropdown in Diagnostics UI.
  - Red anomaly badge displayed inline on flagged trace rows.
  - `anomaly_count` added to dashboard stats.

---

### Workstream 5: Multi-Tenant Data Exposure, RBAC, & Retention (Review #4 P1) 🔜
- [ ] **TASK-SEC-01: Tenant-Scoped Telemetry Exports**
  - Ensure `GET /api/copilot/diagnostics/export` strictly enforces active tenant scoping unless called by a verified super-admin.
- [ ] **TASK-SEC-02: Retention & Auto-Pruning Policy**
  - Implement a configurable retention window (e.g. 30/60/90 days) with scheduled pruning command.

---

### Workstream 6: Transcript Replay Hardening Test Suite (Review #5 P0) 🔜
- [ ] **TASK-TEST-01: Multi-Turn Conversation Replay Regression**
  - Seed and replay real conversation transcripts:
    1. Dog Pvt Ltd / last century temporal safety refusal.
    2. Date year 2026 vs Rs. 50,000 amount adversarial extraction.
    3. P&L zero-totals on active tenant anomaly flag verification.
    4. Stale context decay (Meezan Bank inquiry after IZOC voucher).
  - Verify all appear correctly in telemetry with appropriate classifier modes, latencies, and anomaly tags.

---

## 2. Certification Results

| Suite | Result | Timestamp |
|:---|:---|:---|
| `verify_copilot_intents.php` | **79/79 PASS** | 2026-09-26 20:38 |
| `verify_diagnostics_and_self_learning.php` | **7/7 PASS** | 2026-09-26 20:38 |
| `npm run build` (Next.js) | **0 errors** | 2026-09-26 20:38 |

---

## 3. Files Modified in This Sprint

| File | Changes |
|:---|:---|
| `packages/.../migrations/2026_09_26_000002_...` | New migration: `anomaly_flag`, `entry_point` on logs; `promoted_by/at`, `change_reason`, `version` on KB |
| `packages/.../Models/CopilotDiagnosticLog.php` | Added `anomaly_flag`, `entry_point` to `$fillable` |
| `packages/.../Models/CopilotKnowledgeEntry.php` | Added `promoted_by`, `promoted_at`, `change_reason`, `version` to `$fillable` and `$casts` |
| `app/Copilot/CopilotDiagnosticsService.php` | `recordTrace()`: entry_point/anomaly_flag; `getStats()`: deep-link exclusion + anomaly_count; `listTraces()`: anomaly/entry_point filters; `promoteToKnowledge()`: 3 governance gates |
| `app/Copilot/CopilotService.php` | `handleChat()`: entry_point detection; `logDiagnosticTrace()`: new parameter + anomaly_flag; `detectAnomalyFlag()`: new method |
| `app/Copilot/GuidanceKnowledgeService.php` | 2-token specificity floor on dynamic trigger matching |
| `app/Http/Controllers/Api/CopilotController.php` | `diagnostics()`: anomaly_flag/entry_point filters; `knowledgeStore()`: trigger validation |
| `components/system-diagnostics.tsx` | Interfaces updated; anomaly badges; deep-link badges; conditional promote buttons; anomaly filter chip |
