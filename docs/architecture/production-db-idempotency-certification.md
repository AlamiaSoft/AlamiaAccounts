# Production Database Idempotency & Concurrency Certification (Issue #43, Epic #8)

## 1. Executive Summary & Verification Scope

This document provides formal certification that the durable posting identity, transaction atomicity, failure recovery, company-scoped voucher-reference uniqueness, and concurrency-safety invariants of **Parent Epic #8** (`Make posting identity and idempotency durable and concurrency-safe`) are verified on both:
1. **PostgreSQL 16** (`postgres:16-alpine`, PHP 8.3 `pdo_pgsql`) — The institutional production database engine targeted for multi-tenant, high-throughput financial deployments.
2. **SQLite 3** (`pdo_sqlite`) — The in-memory / single-tenant embedded engine used for rapid developer tests and containerized local execution.

---

## 2. Production Database Engine Verification

| Parameter | SQLite (Local / In-Memory) | PostgreSQL 16 (Production & CI) |
| :--- | :--- | :--- |
| **Engine / Image** | SQLite 3.x (`sqlite3`, PDO) | PostgreSQL 16.x (`postgres:16-alpine`, PDO) |
| **Target Driver** | `sqlite` | `pgsql` |
| **Transaction Semantics** | File/In-memory database locks | Multi-Version Concurrency Control (MVCC) with Transactional DDL |
| **Savepoint Discipline** | Savepoints supported (`SAVEPOINT ... ROLLBACK TO`) | Nested savepoints strictly required inside outer transactions (e.g. `RefreshDatabase`) to prevent `25P02` (`in_failed_sql_transaction`) errors. |
| **Unique Constraints** | Composite index `domain_reference_unique` + Functional expression index `domain_reference_ci_unique` (`UPPER("reference")`). | Composite index `domain_reference_unique` + Functional expression index `domain_reference_ci_unique` (`UPPER("reference")`). |
| **Constraint Violation Codes** | Generic error code `19` / `2067` (`SQLITE_CONSTRAINT_UNIQUE`); disambiguated from `FOREIGN KEY`, `NOT NULL`, and `CHECK` violations. | Standard SQLSTATE `23505` (`unique_violation`). |

---

## 3. Epic #8 Verification Matrix (Child Issues #36 to #43)

| Task ID | Child Issue & Title | Status / PR | Invariants & Verifications Established |
| :--- | :--- | :---: | :--- |
| **#36** | Specify durable posting identity and replay contract | **Merged** ([PR #44](https://github.com/AlamiaSoft/AlamiaAccounts/pull/44)) | Defined canonical posting identity schema: `source_system` + `company_code` + `source_transaction_id` + `source_operation`. SHA-256 payload fingerprinting excluding transient fields. |
| **#37** | Add durable idempotency persistence and unique identity constraint | **Merged** ([PR #46](https://github.com/AlamiaSoft/AlamiaAccounts/pull/46)) | Created `posting_idempotency_records` table with `UNIQUE(domain_uuid, idempotency_key)` and `UNIQUE(domainUuid, reference)` on `domain_journal_entries`. |
| **#38** | Enforce sequential replay and reject idempotency payload conflicts | **Merged** ([PR #50](https://github.com/AlamiaSoft/AlamiaAccounts/pull/50)) | `ExpenseIntegrationService::processExpense()` returns cached response on identical replay; throws `IdempotencyPayloadMismatchException` (HTTP 409) on modified payload. |
| **#39** | Make concurrent posting retries race-safe | **Merged** ([PR #58](https://github.com/AlamiaSoft/AlamiaAccounts/pull/58)) | Concurrency-safe lease acquisition (`status: in_progress`, `lease_token`, `lease_expires_at`). Winner proceeds, concurrent contenders wait or receive deterministic conflict. |
| **#40** | Make posting identity, journal association and audit commit atomically | **Merged** ([PR #69](https://github.com/AlamiaSoft/AlamiaAccounts/pull/69)) | Enforced atomic `DB::transaction` across Abivia `JournalEntry`, `DomainJournalEntry`, `AccountingAuditTrail`, and `PostingIdempotencyService::commitSuccess` with lease fencing (`IdempotencyLeaseFencingException`). |
| **#41** | Verify failure recovery and retry after commit-timeout | **Merged** ([PR #70](https://github.com/AlamiaSoft/AlamiaAccounts/pull/70)) | Commit-timeout retry reconciles committed `domain_journal_entries` without double-posting. Stale unposted entries resolve to `posted`. |
| **#42** | Enforce company-scoped voucher-reference uniqueness | **Merged** ([PR #71](https://github.com/AlamiaSoft/AlamiaAccounts/pull/71)) | Case-insensitive unique constraint `domain_reference_ci_unique` (`UNIQUE("domainUuid", UPPER("reference"))`). Preflight scoping via `DomainContext::scope()`. Atomic rollback on concurrent collision. Substring collision protection in `getVoucher()`. |
| **#43** | Certify idempotency against the production database engine | **Verified** ([PR #72](https://github.com/AlamiaSoft/AlamiaAccounts/pull/72)) | Dedicated certification suite [`ProductionDatabaseIdempotencyCertificationTest.php`](../../AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/ProductionDatabaseIdempotencyCertificationTest.php) verifying all criteria against PostgreSQL 16 and SQLite. |

---

## 4. Key Accounting & Concurrency Guarantees

1. **Exact-Once Double-Entry Posting**:
   - Replaying identical requests returns the original voucher result without creating duplicate ledger entries.
   - Reusing an idempotency key with modified accounts, amounts, or currencies is rejected with a 409 conflict and logged in the audit trail.
2. **Atomicity & Zero-Orphan Invariant**:
   - If a failure occurs at any stage (e.g. database network error, audit trail failure, or pivot constraint collision), the database transaction rolls back all rows (`journal_entries`, `journal_details`, `domain_journal_entries`, and idempotency mutation) atomically.
3. **Company-Scoped Isolation**:
   - Duplicate references within the same tenant are rejected case-insensitively at both preflight and database boundary.
   - Different company tenants independently use identical voucher references without collision or leakage.
4. **PostgreSQL 16 Transactional Safety**:
   - Migrations avoid PHP `try/catch` around failing DDL to preserve PostgreSQL's transaction block.
   - Tests wrapping expected raw constraint exceptions use `DB::transaction()` savepoints.

---

## 5. Certification Acceptance & Parent Epic #8 Completion Verdict

With all 8 child tasks (#36 through #43) fully implemented, verified across SQLite and PostgreSQL 16 CI pipelines, and documented with regression tests, **Parent Epic #8 is fully satisfied and certified for production readiness**.
