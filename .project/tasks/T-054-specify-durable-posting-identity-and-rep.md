---
id: "T-054"
title: "Specify durable posting identity and replay contract"
status: "in_progress"
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
verification: "Repo Root: node scripts/verify-architecture.js; Backend Dir (AlamiaAccounts-Backend): php artisan test"
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
completed_at: ""
completed_git_commit: ""
verification_evidence: "ADR-008 published at docs/adr/ADR-008-durable-posting-identity-and-replay-contract.md; PR #44 under active review; 7/7 architectural suites and 50/50 backend tests passing"
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
  - Authoritative canonical 5-tuple: `UNIQUE(domain_uuid, source_module, source_transaction_id, operation, version)` preventing duplicate ledger entries under divergent client keys.
  - Client lookup uniqueness: `UNIQUE(domain_uuid, idempotency_key)`.
  - Decimal-safe normalization: reject binary `(float)` casting; enforce fixed-point string normalization via `bcadd($val, '0', 4)` / string padding.
  - Non-monetary identifiers (`account_code`, `reference`, `source_transaction_id`) preserved as strings with leading zeros.
  - Authoritative accounting timezone resolution via `DomainContext::getTimezone($companyCode)` for ISO datetime strings.
  - Line item canonical projection with stable tie-breaking and input sequence preservation (`line_index ASC`).
  - Cross-company isolation examples (Company `MAIN` vs `SUB_TRAVEL` with identical keys).
- [x] **Replay/conflict/concurrency/timeout/failure behavior is specified**:
  - Sequence diagram and state machine for pre-transaction reservation (`status: in_flight`).
  - Ownership fencing protocol using cryptographic `lease_token` (UUIDv4), monotonic `lease_generation`, and `lease_expires_at` (60s TTL).
  - Atomic conditional takeover query preventing concurrent dual-master execution on stale leases.
  - Fenced commit inside `DB::transaction(...)`: worker update must match `lease_token`; if 0 rows updated, throws `IdempotencyLeaseFencingException` and rolls back ledger rows completely.
  - Concurrent-loser protocol: Case A (200 cached replay / 422 mismatch), Case B (409 Conflict with `Retry-After: 2`), Case C (stale takeover via atomic fencing update), Case D (failed attempt re-reservation).
  - Interruption recovery: reactive retry stale lock takeover + periodic background sweeper (`ReconcileStaleInFlightPostingsJob`).
- [x] **Proposed persistence constraints and transaction boundary are identified**:
  - Unified ACID transaction on single database connection `DB::connection('default')` committing `journal_entries`, `domain_journal_entries (reference)`, `posting_idempotency_records (status: committed)` with lease fencing, and `accounting_audit_trails (CREATE_VOUCHER)`.
  - Autonomous durable failure boundary in independent catch block with fenced status update, `unposted_integration_entries`, and failure audit.
  - Exact schema additions and migration rollout plan for `domain_journal_entries.reference` (handling legacy NULLs with deterministic backfill before enforcing unique constraint).
- [x] **Design is small enough to implement through the subsequent daily tasks without hidden unresolved decisions**:
  - Sequenced across Tasks #37 (Day 2) through #43 (Day 7) with clear boundaries and zero scope creep.

## Verification Evidence
1. **Architectural Conformance**:
   - Working Directory: `e:\Alamia\AlamiaAccounts` (Repo Root)
   - Command: `node scripts/verify-architecture.js`
   - Result: 7/7 suites passed (100% PASS), zero boundary violations.
2. **Backend Regression Test Suite**:
   - Working Directory: `e:\Alamia\AlamiaAccounts\AlamiaAccounts-Backend`
   - Command: `php artisan test`
   - Result: 50/50 tests passed (165 assertions), zero regressions.
3. **Pure Specification Gate**:
   - Zero schema migrations executed in Day 1.
   - Zero production posting behavior changed in Day 1.

## Learning Capture
- **Finding 1: Dual Uniqueness for Idempotency vs. Canonical Business Identity**:
  - Enforcing uniqueness only on client-supplied keys allows duplicate transactions if the caller varies the key for the same underlying transaction. Both `UNIQUE(domain_uuid, idempotency_key)` and `UNIQUE(domain_uuid, source_module, source_transaction_id, operation, version)` are required.
- **Finding 2: Lease Fencing for Concurrency**:
  - Simple elapsed-time checks without cryptographic fencing tokens allow slow workers to commit duplicate rows after being preempted. Fencing inside the ledger transaction is required.
- **Finding 3: Decimal-Safe String Normalization**:
  - Binary `(float)` conversions in PHP risk precision loss on large monetary values. Fixed-point strings must be produced via `bcmath` or string decomposition.
- **Finding 4: Scope Isolation**:
  - Tooling and infrastructure additions must not be bundled into domain documentation/ADR PRs.
