# Posting Transaction & Connection Boundary Specification (Issue #40)

**Status:** Implemented & Verified (Issue [#40](https://github.com/AlamiaSoft/AlamiaAccounts/issues/40), Parent [#8](https://github.com/AlamiaSoft/AlamiaAccounts/issues/8))  
**Date:** 2026-10-22  

---

## 1. Database Connection Topology

All core accounting models in `abivia/ledger` and `alamiasoft/alamia-accounts` extend `Illuminate\Database\Eloquent\Model` without overriding `$connection`, and all raw/transactional calls use the default `Illuminate\Support\Facades\DB` facade (`DB::connection()`):

| Layer | Model / Class | Table | Connection |
| :--- | :--- | :--- | :--- |
| **Abivia Ledger Kernel** | `Abivia\Ledger\Models\JournalEntry` | `journal_entries` | Default (`sqlite` / `pgsql`) |
| **Abivia Ledger Kernel** | `Abivia\Ledger\Models\JournalDetail` | `journal_details` | Default (`sqlite` / `pgsql`) |
| **Abivia Ledger Kernel** | `Abivia\Ledger\Models\LedgerBalance` | `ledger_balances` | Default (`sqlite` / `pgsql`) |
| **Alamia Domain Association** | `AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry` | `domain_journal_entries` | Default (`sqlite` / `pgsql`) |
| **Alamia Idempotency** | `AlamiaSoft\AlamiaAccounts\Models\PostingIdempotencyRecord` | `posting_idempotency_records` | Default (`sqlite` / `pgsql`) |
| **Alamia Audit Trail** | `AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail` | `accounting_audit_trails` | Default (`sqlite` / `pgsql`) |
| **Alamia Integrity Guard** | `AlamiaSoft\AlamiaAccounts\Models\AccountingIntegrityCheckpoint` | `accounting_integrity_checkpoints` | Default (`sqlite` / `pgsql`) |
| **Alamia Unposted Queue** | `AlamiaSoft\AlamiaAccounts\Models\UnpostedIntegrationEntry` | `unposted_integration_entries` | Default (`sqlite` / `pgsql`) |

Because all tables reside on the same database connection, Laravel's `ManagesTransactions` transaction manager governs nested `DB::transaction()` and `DB::beginTransaction()` / `DB::commit()` blocks using SQL `SAVEPOINT`s on both **PostgreSQL 16** and **SQLite**.

---

## 2. Nested Transaction & Savepoint Mechanics

```mermaid
sequenceDiagram
    participant Caller as Integration Caller
    participant EIS as ExpenseIntegrationService
    participant PIS as PostingIdempotencyService
    participant DB as Laravel DB (Savepoint Manager)
    participant VS as VoucherService
    participant Abivia as JournalEntryController::add()
    participant Audit as AccountingAuditTrail

    Caller->>EIS: processExpense(payload, companyCode)
    EIS->>PIS: acquireReservation(identity, payloadHash)
    Note over PIS,DB: Phase 1 (Separate Pre-Transaction Commit):<br/>Inserts status='in_flight' with lease_token<br/>so concurrent workers observe active lease.
    PIS-->>EIS: status='acquired', record (in_flight)

    EIS->>DB: DB::transaction() [Level 0 -> 1: BEGIN]
    EIS->>VS: createJournalEntry(voucherData, companyCode)
    VS->>DB: DB::transaction() [Level 1 -> 2: SAVEPOINT trans2]
    VS->>Abivia: add(Entry)
    Abivia->>DB: DB::beginTransaction() [Level 2 -> 3: SAVEPOINT trans3]
    Abivia->>DB: INSERT journal_entries, journal_details, UPDATE ledger_balances
    Abivia->>DB: DB::commit() [Level 3 -> 2: RELEASE SAVEPOINT]
    VS->>DB: INSERT domain_journal_entries
    VS->>Audit: record('CREATE_VOUCHER')
    VS->>DB: Commit inner closure [Level 2 -> 1]
    EIS->>DB: record->markCommittedFenced() (WHERE lease_token & status='in_flight')
    EIS->>Audit: record('INTEGRATION_EXPENSE_POSTED')
    EIS->>DB: UPDATE unposted_integration_entries SET status='resolved'
    EIS->>DB: Commit outer closure [Level 1 -> 0: COMMIT]
    EIS-->>Caller: Return committed responseData
```

### Key Atomicity Guarantees
1. **Abivia `JournalEntryController::add()` Savepoint Nesting**:
   - `JournalEntryController::add()` calls `DB::beginTransaction()` (line 51) and `DB::commit()` (line 60).
   - When invoked inside `VoucherService::createJournalEntry()`'s `DB::transaction()` (or `ExpenseIntegrationService::processExpense()`'s outer `DB::transaction()`), `JournalEntryController::add()` runs at transaction depth `>= 2`. Its `DB::commit()` only decrements Laravel's transaction depth; the underlying database transaction is **not** durably committed until the outermost `DB::transaction()` returns and transitions depth `1 -> 0`.
2. **No Unassociated Journal Entries**:
   - If `DomainJournalEntry::create()` or `AccountingAuditTrail::record('CREATE_VOUCHER')` throws inside `VoucherService::createJournalEntry()`, the transaction rolls back across the savepoint, undoing `journal_entries`, `journal_details`, and `ledger_balances`.
3. **No Orphan Vouchers on Lease Preemption or Audit Failure**:
   - In `ExpenseIntegrationService::processExpense()`, `VoucherService::createJournalEntry()`, `$idempotencyRecord->markCommittedFenced()`, `AccountingAuditTrail::record('INTEGRATION_EXPENSE_POSTED')`, and `UnpostedIntegrationEntry` resolution execute inside a single outer `DB::transaction()`.
   - If `markCommittedFenced()` throws `IdempotencyLeaseFencingException` (because the worker's lease expired and was taken over by another worker), or if `AccountingAuditTrail::record('INTEGRATION_EXPENSE_POSTED')` fails, the outer `DB::transaction()` rolls back the entire voucher (`journal_entries`, `journal_details`, `ledger_balances`, `domain_journal_entries`, and `CREATE_VOUCHER` audit).
   -Posted ledger history is never compensated by deleting committed vouchers (`deleteVoucher` remains prohibited); uncommitted work is rolled back cleanly by the database engine before commit.
4. **Atomic Reversal Audit (`VoucherService::reverseVoucher()`)**:
   - `VoucherService::reverseVoucher()` wraps `createJournalEntry()` and `AccountingAuditTrail::record('REVERSE_VOUCHER')` in a single `DB::transaction()` so a compensating `REV-` voucher cannot commit without its `REVERSE_VOUCHER` audit trail entry.

---

## 3. Explicit Separate-Transaction Boundaries

Two operations intentionally execute outside the atomic ledger-posting `DB::transaction()`:

1. **Pre-Posting Lease Acquisition (`PostingIdempotencyService::acquireReservation()`)**:
   - Must commit `status = 'in_flight'` and `lease_token` *before* the ledger-posting transaction starts so concurrent requests on separate database connections immediately see the active reservation via unique-index enforcement (`#39`).
2. **Post-Rollback Failure Diagnostics (`catch (Throwable $e)` in `ExpenseIntegrationService::processExpense()`)**:
   - When the outer `DB::transaction()` rolls back due to a posting, mapping, or audit error, the `catch` block executes at transaction depth `0` (after rollback) to persist:
     - `$idempotencyRecord->markFailed($e->getMessage())` — fenced by `WHERE lease_token = :token AND status = 'in_flight'` so a rolled-back worker whose lease was preempted (`IdempotencyLeaseFencingException`) can never overwrite another worker's `in_flight` or `committed` state.
     - `UnpostedIntegrationEntry::recordFailure(...)` — durable failure queue record for accountant visibility.
     - `AccountingAuditTrail::record(..., 'INTEGRATION_POSTING_FAILED', ...)` (or `'IDEMPOTENCY_LEASE_FENCED'`).
   - Each post-rollback diagnostic write is isolated in its own `try/catch` block so a secondary failure in audit logging cannot prevent `markFailed()` from releasing the `in_flight` idempotency state or mask the original exception.
   - Crash/timeout recovery where the PHP process terminates mid-transaction before reaching the `catch` block is handled via lease expiration (`lease_expires_at`) and covered in [#41](https://github.com/AlamiaSoft/AlamiaAccounts/issues/41).


---

## 4. Failure Recovery, Post-Commit Timeout Retry & Reconciliation (Issue #41)

### 4.1 Post-Commit Delivery / Timeout Protection
If the outer `DB::transaction()` in `ExpenseIntegrationService::processExpense()` commits at transaction depth `0` (meaning `journal_entries`, `domain_journal_entries`, `posting_idempotency_records.status = 'committed'`, and `INTEGRATION_EXPENSE_POSTED` audit are durably persisted), and an exception or network timeout occurs **after** commit (before the caller receives the HTTP/RPC response):
1. The `catch (Throwable $e)` handler inspects `$idempotencyRecord->fresh()`.
2. Because `$freshIdempotency->isCommitted()` is `true`, the handler **never** calls `markFailed()`, **never** records a false `UnpostedIntegrationEntry::recordFailure()`, and **never** logs `INTEGRATION_POSTING_FAILED`.
3. Instead, it logs `INTEGRATION_POST_COMMIT_DELIVERY_TIMEOUT` in `accounting_audit_trails` and rethrows the delivery/timeout exception.
4. When the caller retries with the same payload, `PostingIdempotencyService::acquireReservation()` detects the committed record and returns the original posting (`is_idempotent_replay: true`, matching `journal_entry_id` and `voucher_reference`) without creating a duplicate journal entry.

### 4.2 Stranded In-Flight Lease Sweeper (`PostingIdempotencyService::sweepStaleInFlightRecords()`)
When a worker crashes hard (e.g., `SIGKILL`, container eviction, or DB connection loss before reaching the `catch` block), the database rolls back the uncommitted transaction and leaves `posting_idempotency_records` in `status = 'in_flight'` with an expired `lease_expires_at`:
1. **Live-Lease Immunity:** The sweeper queries only `status = 'in_flight'` where `lease_expires_at < cutoff` and fences its `UPDATE` with `WHERE id = :id AND status = 'in_flight' AND lease_token = :observedToken AND lease_expires_at < :now`. It can **never** steal a live lease or overwrite a committed record.
2. **Normal Crash Recovery:** If no `DomainJournalEntry` exists for `(domain_uuid, reference)`, the sweeper transitions `posting_idempotency_records` to `status = 'failed'`, creates/updates the tenant-scoped `UnpostedIntegrationEntry` (`status = 'failed'`), and logs `IDEMPOTENCY_STALE_LEASE_SWEPT`.
3. **Ambiguous Reference Guard:** If an unlinked `DomainJournalEntry` already exists for `(domain_uuid, reference)`, the sweeper does **not** guess or overwrite ledger history; it transitions `posting_idempotency_records` to `status = 'failed'`, marks `UnpostedIntegrationEntry` as `status = 'reconciliation_required'`, and logs `IDEMPOTENCY_RECONCILIATION_REQUIRED`.

### 4.3 Deterministic Unposted Entry Reconciliation (`ExpenseIntegrationService::reconcileUnpostedEntry()`)
`ExpenseIntegrationService::reconcileUnpostedEntry()` reconciles entries in `unposted_integration_entries` with explicit, stable `actionable_code` outcomes:

| `status` | `actionable_code` | `reconciled` | Behavior |
| :--- | :--- | :---: | :--- |
| `already_resolved` | `ALREADY_RESOLVED` | `true` | Entry was already resolved; no-op. |
| `reconciled_from_committed` | `RECONCILED_EXISTING_COMMIT` | `true` | Corresponding `PostingIdempotencyRecord` is already `committed` with matching payload; marks `UnpostedIntegrationEntry` resolved against existing `journal_entry_id` without re-posting. |
| `reconciliation_required` | `RECONCILIATION_PAYLOAD_CONFLICT` | `false` | Unposted queue payload conflicts with an already-committed `PostingIdempotencyRecord`; flagged for manual accountant review. |
| `in_progress` | `ACTIVE_LEASE_IN_PROGRESS` | `false` | Another worker holds a live `in_flight` lease (`retry_after_seconds: 2`). |
| `reconciliation_required` | `AMBIGUOUS_EXISTING_VOUCHER_REFERENCE` | `false` | A `DomainJournalEntry` with the same `reference` already exists in the domain without a committed idempotency link; flagged for manual review without duplicating or altering ledger history. |
| `reconciled_posted` | `RECONCILED_NEW_POSTING` | `true` | Re-executed through `processExpense()`, committed atomically, and marked `resolved`. |
| `failed` | `RECONCILIATION_POSTING_FAILED` | `false` | Re-execution failed pre-commit (rolled back); `retry_count` incremented and `error_message` updated. |
