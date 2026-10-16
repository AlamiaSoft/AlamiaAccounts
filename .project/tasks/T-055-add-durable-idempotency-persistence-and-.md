---
id: "T-055"
title: "Add durable idempotency persistence and unique identity constraint"
status: "in_progress"
priority: "high"
epic: ""
assigned_to: "agent"
depends_on:
  - "T-054"
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/database/migrations/2026_10_16_000002_create_posting_idempotency_records_table.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/PostingIdempotencyRecord.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainJournalEntry.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/PostingIdempotencyPersistenceTest.php"
verification: "Repo Root: node scripts/verify-architecture.js; Backend Dir: php artisan test --filter=PostingIdempotencyPersistenceTest"
created_at: "2026-10-16"
updated_at: "2026-10-16"
decision_refs:
  - "ADR-008"
files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/database/migrations/2026_10_16_000002_create_posting_idempotency_records_table.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/PostingIdempotencyRecord.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainJournalEntry.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/PostingIdempotencyPersistenceTest.php"
claimed_by: "agent"
started_at: "2026-10-16T12:28:54.764Z"
start_git_commit: "a2730fc"
start_git_branch: "task/issue-37-idempotency-persistence"
completed_at: ""
completed_git_commit: ""
verification_evidence: "PostingIdempotencyPersistenceTest passing 8/8 assertions; backend full suite passing 58/58 tests; architectural auditor passing 7/7 suites"
---

## Objective
Implement the smallest persistence layer that durably reserves and records a posting identity, enforcing uniqueness in the database (Day 2 of Issue #8 DAG, Issue #37).

## Acceptance Criteria
- [x] **Schema enforces approved identity uniqueness at the database boundary**:
  - Implemented `posting_idempotency_records` table with composite `UNIQUE(domain_uuid, idempotency_key)` and canonical `UNIQUE(domain_uuid, source_module, source_transaction_id, operation, version)`.
  - Implemented composite `UNIQUE(domainUuid, reference)` on `domain_journal_entries`.
  - Implemented composite `UNIQUE(company_code, idempotency_key)` on `operational_sales`.
- [x] **Payload fingerprint and status can be persisted and retrieved deterministically**:
  - Verified `PostingIdempotencyRecord` model persists `payload_hash`, `request_payload` (JSON cast), `status`, `lease_token`, `lease_generation`, `lease_expires_at`, and `response_data`.
- [x] **Duplicate identity reservation is surfaced as a handled conflict/unique violation, not silently ignored**:
  - Verified duplicate insertions in the same tenant throw `QueryException` (SQLSTATE 23000 / unique constraint violation), while cross-tenant insertions with identical keys succeed with zero collision.
  - Verified divergent client keys with identical canonical 5-tuples are rejected by the database.
- [x] **Migration behavior for existing data is explicit and tested where applicable**:
  - Backfill migration logic maps `extra->reference` when present, with deterministic fallback to `'LEGACY-' || journalEntryId`, guaranteeing zero NULL references before applying unique index.
- [x] **Existing relevant tests remain green**:
  - All 58 backend tests passing (189 assertions).
  - All 7 architectural conformance suites passing (100% PASS).

## Verification Evidence
1. **Model & Uniqueness Feature Suite**:
   - Working Directory: `e:\Alamia\AlamiaAccounts\AlamiaAccounts-Backend`
   - Command: `php artisan test --filter=PostingIdempotencyPersistenceTest`
   - Result: `8 passed (24 assertions) in 1.62s`
2. **Backend Regression Test Suite**:
   - Working Directory: `e:\Alamia\AlamiaAccounts\AlamiaAccounts-Backend`
   - Command: `php artisan test`
   - Result: `58 passed (189 assertions) in 7.69s`
3. **Architectural Conformance Auditor**:
   - Working Directory: `e:\Alamia\AlamiaAccounts`
   - Command: `node scripts/verify-architecture.js`
   - Result: `7/7 Suites Passed (100% PASS)`

## Learning Capture
- **Finding 1: Cross-Database Unique Constraint Behavior on SQLite vs MySQL**:
  - SQLite requires explicit foreign key column population for composite keys in memory tables; ensuring `domainUuid` and `journalEntryId` are populated satisfies foreign key integrity across both engines.
- **Finding 2: Dual Uniqueness Testing**:
  - Testing divergent client keys for the identical canonical 5-tuple confirms that client-side UUID generation cannot bypass double-entry posting deduplication.
