# Company-Scoped Voucher Reference Uniqueness (Issue #42)

## 1. Overview & Invariant

In **Alamia Accounts**, every posted voucher (`JournalEntry`) is associated with exactly one tenant company domain (`domainUuid`) via the `domain_journal_entries` pivot table.

The **Company-Scoped Voucher Reference Uniqueness** invariant requires that:
1. Within a single company domain (`domainUuid`), no two vouchers may share the same normalized voucher reference (`TRIM(reference)` compared case-insensitively).
2. Across different company domains (`domainUuid_A !== domainUuid_B`), identical voucher reference strings (e.g. `OB-2026-001`, `JV-001`, `PV-001`) are **permitted** and strictly isolated per tenant.
3. Duplicate reference rejection is enforced at **both** the application preflight layer and the relational database persistence boundary (SQLite and PostgreSQL 16), guaranteeing zero duplicate voucher references even under concurrent posting races.

---

## 2. Database-Level Persistence Boundary

Two unique constraints on `domain_journal_entries` enforce company-scoped reference uniqueness:

| Index Name | Definition | Purpose |
| :--- | :--- | :--- |
| `domain_reference_unique` | `UNIQUE ("domainUuid", "reference")` | Standard composite uniqueness on `(domainUuid, reference)` (`2026_10_16_000002_create_posting_idempotency_records_table.php`). |
| `domain_reference_ci_unique` | `UNIQUE ("domainUuid", UPPER("reference"))` | Functional expression unique index enforcing case-insensitive uniqueness on SQLite and PostgreSQL (`2026_10_22_000002_harden_domain_journal_entries_reference_uniqueness.php`). |

In addition, `DomainJournalEntry::booted()` normalizes `reference` via `trim((string) $entry->reference)` before every model save.

---

## 3. Atomic Rollback on Concurrent Collision

Inside [`VoucherService::createJournalEntry()`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php):

1. **Tenant Context Scoping**: `createJournalEntry(array $data, ?string $domainCode = null)` executes within `DomainContext::scope($domain->code, ...)` so that explicit `$domainCode` calls always evaluate duplicate checks, period locks, and integrity checkpoints against the target company and restore the caller's `DomainContext` in `finally`.
2. **Indexed Preflight Check**: `DomainJournalEntry::referenceExistsInDomain($domain->domainUuid, $refToCheck)` performs an indexed case-insensitive lookup against `domain_journal_entries` before constructing the Abivia `Entry` message.
3. **Database Boundary Catch & Atomic Rollback**: Inside `DB::transaction(...)`, after `$this->journalController->add($message)` creates the Abivia `journal_entries` and `journal_details` rows, `DomainJournalEntry::create(...)` inserts the company-scoped association row. If a concurrent worker inserted a colliding `(domainUuid, UPPER(reference))` row after the preflight check, the database engine raises a unique constraint violation (`QueryException`). `VoucherService` catches the `QueryException`, verifies `PostingIdempotencyService::isUniqueConstraintViolation($e)`, and throws:
   ```text
   Duplicate voucher reference '{$voucherRef}' is not allowed for this company
   ```
   Because this exception exits the `DB::transaction(...)` closure, the Abivia `journal_entries` and `journal_details` rows are rolled back atomically.

---

## 4. Historical Backfill & Duplicate Disambiguation

For databases containing legacy rows created prior to reference uniqueness enforcement, [`DomainJournalEntry::reconcileAndBackfillReferences()`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainJournalEntry.php) (invoked automatically by migration `2026_10_22_000002_harden_domain_journal_entries_reference_uniqueness.php`) applies a deterministic, zero-data-loss reconciliation strategy:

1. **Ordering**: Rows in `domain_journal_entries` are processed deterministically in `(domainUuid ASC, journalEntryId ASC, id ASC)` order.
2. **NULL / Blank Backfill**: If `reference` is `NULL` or whitespace-only, the reconciler inspects `journal_entries.extra`:
   - If `extra` is JSON containing `reference` or `voucher_number`, that trimmed value is extracted.
   - If `extra` is a non-empty plain string, that trimmed string is used.
   - Otherwise, the deterministic fallback `'LEGACY-' . $journalEntryId` is assigned.
3. **In-Domain Collision Disambiguation**: Within each `domainUuid`, the first row claiming `UPPER(TRIM(reference))` retains its canonical reference. Any subsequent row in the same `domainUuid` with a colliding normalized reference is disambiguated as:
   ```text
   {$baseRef}-DUP-{$journalEntryId}
   ```
   When disambiguated, if `journal_entries.extra` is a JSON object, `extra.legacy_duplicate_reference` records the original colliding value and `extra.reference` is updated to the disambiguated reference so ledger queries remain consistent.
