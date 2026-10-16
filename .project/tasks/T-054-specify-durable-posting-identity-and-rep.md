---
id: "T-054"
title: "Specify durable posting identity and replay contract"
status: "done"
priority: "high"
epic: ""
assigned_to: "agent"
depends_on:
blocks:
relevant_files:
  - "docs/adr/ADR-008-durable-posting-identity-and-replay-contract.md"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ExpenseIntegrationService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/UnpostedIntegrationEntry.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/database/migrations/2026_10_15_000001_create_unposted_integration_entries_table.php"
verification: "node scripts/verify-architecture.js && php artisan test"
created_at: "2026-10-16"
updated_at: "2026-10-16"
decision_refs:
  - "ADR-008"
files:
  - "docs/adr/ADR-008-durable-posting-identity-and-replay-contract.md"
claimed_by: "agent"
started_at: "2026-10-16T11:29:38.265Z"
start_git_commit: "35f51ce"
start_git_branch: "task/issue-36-posting-identity-contract"
completed_at: "2026-10-16T11:45:00.000Z"
completed_git_commit: "HEAD"
verification_evidence: "ADR-008 published at docs/adr/ADR-008-durable-posting-identity-and-replay-contract.md; 7/7 architectural suites and 50/50 backend tests passing"
---

## Objective
Produce a code-grounded, reviewable contract (ADR-008, Proposed) for idempotency identity, payload equality, replay outcomes, concurrency, and recovery before changing persistence or posting behavior (Day 1 of Issue #8 DAG).

## Acceptance Criteria
- [x] **Current flow and gaps are documented from the actual code/schema**:
  - Inspected and cited `ExpenseIntegrationService.php#L64-L72` (unindexed SQL `LIKE` scan on `journal_entries.extra`).
  - Inspected and cited `VoucherService.php#L70-L80` (in-memory voucher reference loop executed before transaction start).
  - Inspected and cited `2025_12_05_053448_create_domain_journal_entries_table.php#L17-L43` (missing `reference` column and missing `UNIQUE(domainUuid, reference)`).
  - Inspected and cited `2026_10_15_000001_create_unposted_integration_entries_table.php#L21` (error-only logging with flawed global unique constraint on `idempotency_key`).
- [x] **Identity scope and payload fingerprint rules are deterministic and company/domain-scoped**:
  - Specified composite tenant uniqueness: `UNIQUE(domain_uuid, idempotency_key)`.
  - Reconciled explicit caller-supplied keys with backend canonical 5-tuple identity `{source_module}:{company_code}:{source_transaction_id}:{operation}:{version}`.
  - Formulated field-aware normalization: 4-decimal fixed point for monetary values, string preservation with leading zeroes for account codes/references, ISO-8601 calendar dates (`YYYY-MM-DD`), uppercase ISO currencies, and line item sorting (`account_code ASC, debit ASC, credit ASC, description ASC`).
  - Provided cross-company isolation examples (Company `MAIN` vs `SUB_TRAVEL` using identical caller key `EXP-2026-10-001`).
- [x] **Replay/conflict/concurrency/timeout/failure behavior is specified**:
  - Defined sequence diagram and state machine for pre-transaction reservation (`status: in_flight`).
  - Specified concurrent-loser protocol: Case A (`committed`: 200 replay if hash matches, 422 if differs), Case B (`in_flight` < 30s: 409 Conflict with `Retry-After: 2`), Case C (`in_flight` >= 30s: stale lock conditional update recovery), Case D (`failed`: controlled retry).
  - Specified interruption recovery if the failure handler itself crashes (TTL expiration + `ReconcileStaleInFlightPostingsJob` sweeper).
- [x] **Proposed persistence constraints and transaction boundary are identified**:
  - Identified `DB::transaction(...)` atomic boundary committing `journal_entries`, `domain_journal_entries (reference)`, `posting_idempotency_records (status: committed)`, and `accounting_audit_trails (CREATE_VOUCHER)`.
  - Identified autonomous durable failure boundary in independent catch block writing `posting_idempotency_records (status: failed)`, `unposted_integration_entries`, and failure audit trail.
  - Specified exact schema additions for Day 2 (`posting_idempotency_records`, `domain_journal_entries.reference`, `operational_sales`).
- [x] **Design is small enough to implement through the subsequent daily tasks without hidden unresolved decisions**:
  - Sequenced across Tasks #37 (Day 2) through #43 (Day 7) with clear boundaries and zero scope creep.

## Verification Evidence
1. **Architectural Conformance**:
   - Command: `node scripts/verify-architecture.js`
   - Result: 7/7 suites passed (100% PASS), zero boundary violations, zero Abivia raw model leaks in controllers.
2. **Backend Regression Test Suite**:
   - Command: `php artisan test`
   - Result: 50/50 tests passed (165 assertions), zero regressions.
3. **Pure Specification Gate**:
   - Zero schema migrations executed.
   - Zero production posting behavior changed in Day 1.

## Learning Capture
- **Finding 1: Multi-Tenant Constraint Scoping**:
  - Global unique constraints on client-supplied idempotency keys violate tenant isolation. All idempotency constraints must be composite with `domain_uuid` or `company_code`. (Confidence: High; Evidence: `2026_10_15_000001` vs tenant isolation invariant).
- **Finding 2: PHP Float Serialization**:
  - Standard `json_encode` in PHP produces variable floating representations. Monetary normalization must strictly use fixed-point `number_format($val, 4, '.', '')` while non-monetary identifiers must remain untouched strings. (Confidence: High).
- **Finding 3: Failure Handler Interruption**:
  - Failure logging that relies solely on application `catch` blocks can leave stranded locks if worker processes crash. Combining short TTLs with conditional atomic updates and an asynchronous reconciliation sweeper provides robust self-healing. (Confidence: High).
- **Governed Skill Candidate**:
  - Evaluated: The patterns are standard institutional accounting implementations; no new generalizable tooling skill candidate required at this time (record: "no reusable learning found for new skill packaging").
