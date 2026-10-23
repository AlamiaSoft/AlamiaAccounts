---
trigger: always_on
description: Enforces SQLite and PostgreSQL 16 as the sole supported database engines, prevents PostgreSQL transactional DDL aborts in migrations and RefreshDatabase tests, and disambiguates SQLite constraint error codes.
---

# Database Engine & PostgreSQL Transaction Discipline Rule

## 1. Supported Database Engines Only (SQLite & PostgreSQL 16)
- Alamia Accounts targets **SQLite** (local/in-memory test execution) and **PostgreSQL 16** (production and CI certification).
- **NEVER** add MySQL or MariaDB error codes (such as `1062`), syntax, or engine-specific workarounds.

## 2. PostgreSQL Transactional Migrations (No PHP `try/catch` Around Failing DDL)
- On PostgreSQL, Laravel executes each migration inside a database transaction (`BEGIN ... COMMIT`).
- **NEVER** wrap `Schema::table(..., fn ($table) => $table->unique(...))` or other DDL statements in a PHP `try { ... } catch (\Throwable $e)` block to ignore "already exists" errors. In PostgreSQL, a failed SQL statement immediately aborts the active transaction (`SQLSTATE 25P02: current transaction is aborted, commands ignored until end of transaction block`), causing all subsequent statements in the migration to fail even when the PHP exception is caught.
- **ALWAYS** use idempotent SQL or schema introspection before running DDL:
  - `CREATE UNIQUE INDEX IF NOT EXISTS ...` / `DROP INDEX IF EXISTS ...`
  - `Schema::hasColumn(...)`, `Schema::hasTable(...)`, or `Schema::hasIndex(...)`

## 3. PostgreSQL Savepoint Discipline in `RefreshDatabase` Tests
- In PHPUnit tests using `RefreshDatabase`, PostgreSQL wraps the entire test method in an outer transaction.
- When a test intentionally triggers a raw database constraint violation (`QueryException`) to verify an index or foreign key, **ALWAYS** wrap that statement in `DB::transaction(fn () => ...)` so Laravel issues `SAVEPOINT` and `ROLLBACK TO SAVEPOINT`. Without a savepoint, the expected `QueryException` aborts the outer `RefreshDatabase` transaction and causes subsequent queries in the test to fail.

## 4. SQLite Constraint Error Disambiguation (`SQLSTATE 23000` / Code `19`)
- SQLite uses generic error code `19` (`SQLITE_CONSTRAINT`) for `UNIQUE`, `FOREIGN KEY`, `NOT NULL`, and `CHECK` constraint failures.
- Any helper that detects unique constraint violations (e.g. `PostingIdempotencyService::isUniqueConstraintViolation()`) **MUST** explicitly return `false` when the error message contains `foreign key constraint`, `not null constraint`, or `check constraint` before matching code `19` or `2067`.
