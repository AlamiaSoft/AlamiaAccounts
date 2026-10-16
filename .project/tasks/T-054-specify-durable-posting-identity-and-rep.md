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
verification: ""
created_at: "2026-10-16"
updated_at: "2026-10-16"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-16T11:29:38.265Z"
start_git_commit: "35f51ce"
start_git_branch: "task/issue-36-posting-identity-contract"
completed_at: "2026-10-16T11:30:56.708Z"
completed_git_commit: "35f51ce"
verification_evidence: "ADR-008 published at docs/adr/ADR-008-durable-posting-identity-and-replay-contract.md; 7/7 architectural suites and 50/50 backend tests passing"
---

## Objective
Produce a code-grounded, reviewable contract (ADR-008) for idempotency identity, payload equality, replay outcomes, concurrency, and recovery before changing persistence or posting behavior (Day 1 of Issue #8 DAG).

## Acceptance Criteria
- [x] Current flow and gaps are documented from the actual code/schema (inspected `ExpenseIntegrationService.php#L64-L72`, `VoucherService.php#L70-L80`, and migration `2025_12_05_053448`).
- [x] Identity scope and payload fingerprint rules are deterministic and company/domain-scoped (`UNIQUE(domain_uuid, idempotency_key)` and field-aware canonicalization).
- [x] Replay/conflict/concurrency/timeout/failure behavior is specified (FSM matrix with HTTP 200, 422, 409 responses).
- [x] Proposed persistence constraints and transaction boundary are identified (separated atomic commit from durable failure boundary).
- [x] Design is small enough to implement through the subsequent daily tasks without hidden unresolved decisions (#37 through #43).

## Verification Evidence
- `node scripts/verify-architecture.js`: 7/7 suites passed (100% PASS).
- `php artisan test`: 50/50 tests passed (165 assertions).
- Zero production code or schema mutations introduced (contract-only documentation task).

## Learning Capture
1. Multi-Tenant Constraint Scoping: Global unique constraints on client-supplied idempotency keys violate tenant isolation; constraints must be composite with `domain_uuid`.
2. Fixed-Point Formatting: Float serialization requires explicit `number_format($val, 4, '.', '')` to avoid false-positive payload mismatches.
3. Autonomous Failure Survival: Failure records must be recorded in an independent catch block outside the rolled-back business transaction to ensure accountant auditability.
