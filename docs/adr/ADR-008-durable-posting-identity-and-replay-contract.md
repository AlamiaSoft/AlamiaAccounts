# ADR-008: Durable Posting Identity and Replay Contract

**Status**: Proposed (Under Review)  
**Date**: 2026-10-16  
**Context**: Issue #8, Issue #36 (Day 1 of DAG decomposition)  
**Parent Epic**: [Issue #8: Make posting identity and idempotency durable and concurrency-safe](https://github.com/AlamiaSoft/AlamiaAccounts/issues/8)  
**Task**: [Issue #36: Specify durable posting identity and replay contract](https://github.com/AlamiaSoft/AlamiaAccounts/issues/36)  

---

## 1. Executive Summary

In institutional double-entry accounting systems, financial mutations must enforce **strictly durable, exactly-once posting semantics**. Client retries, transient network timeouts, and concurrent requests must never generate duplicate ledger entries, corrupt ledger balances, or bypass tenant isolation boundaries.

This Architecture Decision Record (ADR) establishes the formal specification for Day 1 ([Issue #36](https://github.com/AlamiaSoft/AlamiaAccounts/issues/36)) without modifying production code or executing database migrations. It specifies:
1. **Tenant-Scoped Posting Identity**: A composite identity guaranteeing strict tenant isolation and reconciling client-supplied keys with backend-generated identity keys.
2. **Deterministic, Field-Aware Payload Fingerprinting**: Mathematical canonicalization across monetary amounts, calendar dates, currencies, account codes, and sorted line items.
3. **Database Reservation, Locking & Concurrency-Loser Protocol**: A deterministic protocol for reserving keys, catching unique constraint violations, and responding to concurrent race conditions.
4. **Separation of Atomic Posting vs. Durable Failure Records**: Precise rollback semantics ensuring ledger entries never commit partially, while failure and audit records survive rollback for accountant reconciliation.
5. **Concrete Plan for Subsequent DAG Tasks**: Clear sequencing into Tasks #37 through #43.

---

## 2. Verified Facts vs. Proposed Design Decisions

To ensure architectural integrity, this document explicitly distinguishes between **verified facts** from the current codebase and **proposed design decisions**:

| Aspect | Verified Existing Fact (Current Codebase) | Proposed Design Decision (Contract for Day 2+) |
| :--- | :--- | :--- |
| **Voucher Deduplication** | Searches `journal_entries.extra` JSON string with unindexed `LIKE` scans ([`ExpenseIntegrationService.php#L66-L71`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ExpenseIntegrationService.php#L66-L71)) or loops in memory before starting transactions ([`VoucherService.php#L70-L80`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php#L70-L80)). | Replace string scans with database unique constraints on `(domain_uuid, reference)` and `(domain_uuid, idempotency_key)`. |
| **Idempotency Identity Scope** | `unposted_integration_entries` defines a global unique constraint on `idempotency_key` ([migration 2026_10_15_000001](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/database/migrations/2026_10_15_000001_create_unposted_integration_entries_table.php#L21)), creating cross-tenant collision vulnerability. | Enforce composite tenant scoping: `UNIQUE (domain_uuid, idempotency_key)`. |
| **Failure Handling** | Only failure entries are persisted in `unposted_integration_entries`. Successful postings have no persisted idempotency record or cached response data. | Introduce `posting_idempotency_records` table to persist lifecycle status (`in_flight`, `committed`, `failed`) and cached response payloads. |
| **Audit Records** | `AccountingAuditTrail::record(...)` executes inside or outside transactions without guaranteed survival on failure. | Separate atomic transaction audit (committed with ledger) from autonomous failure audit (survives rollback for reconciliation). |

---

## 3. Current Implementation Analysis & Gap Identification

Inspection of the existing codebase reveals four concrete architectural vulnerabilities:

### 3.1 Unindexed SQL `LIKE` Scans against JSON Metadata
* **Inspected Code**: [`ExpenseIntegrationService.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ExpenseIntegrationService.php#L64-L72)
```php
// Existing Flow in ExpenseIntegrationService.php:
$existingEntry = DB::table('journal_entries')
    ->join('domain_journal_entries', 'journal_entries.journalEntryId', '=', 'domain_journal_entries.journalEntryId')
    ->where('domain_journal_entries.domainUuid', $domainUuid)
    ->where('journal_entries.extra', 'like', '%"reference":"' . $reference . '"%')
    ->select('journal_entries.*')
    ->first();
```
* **Defect**:
  - Full table scan on `journal_entries.extra` (JSON serialized text).
  - Subject to substring collisions (e.g. searching for reference `"EXP-1"` matches `"EXP-10"` or `"EXP-100"`).
  - Executed outside of a row-level lock or database constraint, creating a race window between read and subsequent write.

### 3.2 In-Memory Voucher Looping Before Transaction
* **Inspected Code**: [`VoucherService.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php#L70-L80)
```php
// Existing Flow in VoucherService.php:
$refToCheck = trim($data['reference'] ?? $data['voucher_number'] ?? '');
if ($refToCheck !== '') {
    $existingVouchers = $this->getJournalEntries(['reference' => $refToCheck]);
    foreach ($existingVouchers as $ev) {
        if (strcasecmp($ev['reference'] ?? '', $refToCheck) === 0) {
            throw new Exception("Duplicate voucher reference '{$refToCheck}' is not allowed for this company");
        }
    }
}
```
* **Defect**:
  - Check occurs in PHP memory before `DB::transaction(...)` begins.
  - Two concurrent HTTP requests with reference `INV-2026-001` both execute the check before either commits, passing validation simultaneously and creating duplicate vouchers in the ledger.

### 3.3 Missing Database Unique Constraints on Voucher Reference and Domain Association
* **Inspected Migration**: [`2025_12_05_053448_create_domain_journal_entries_table.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/database/migrations/2025_12_05_053448_create_domain_journal_entries_table.php#L17-L43)
```php
// Existing domain_journal_entries schema:
$table->foreignUuid('domainUuid');
$table->unsignedBigInteger('journalEntryId');
$table->unique(['domainUuid', 'journalEntryId'], 'domain_entry_unique');
```
* **Defect**:
  - There is no `reference` column on `domain_journal_entries`.
  - The database does not enforce `UNIQUE(domainUuid, reference)`. Duplicate references within a company cannot be prevented at the storage layer.

### 3.4 Insufficiency of Error-Only Logging in `unposted_integration_entries`
* **Inspected Model**: [`UnpostedIntegrationEntry.php`](file:///e:/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/UnpostedIntegrationEntry.php#L15-L35)
* **Defect**:
  - `unposted_integration_entries` only records failed requests.
  - Successful postings have no persisted idempotency record storing the original HTTP response. A replayed request must query and reconstruct responses ad-hoc.
  - Reusing an idempotency key with a mutated payload (e.g., changing amount from \$500 to \$5,000) is not detected or audited as a security violation.

---

## 4. Tenant-Scoped Posting Identity Specification

### 4.1 Tenant Scoping vs. Global Uniqueness
In multi-tenant SaaS accounting, idempotency **must always be tenant-scoped**. Enforcing global uniqueness on raw client-supplied keys (e.g. UUID, external invoice number `INV-2026-001`, or payroll batch ID) would allow Tenant A to unintentionally or maliciously deny service to Tenant B by reserving or exhausting their keyspace.

Therefore, the database constraint must strictly be composite:

$$\text{Storage Constraint} = \text{UNIQUE}(\text{domain\_uuid}, \text{idempotency\_key})$$

### 4.2 Reconciling Client-Supplied vs. Synthetic Canonical Keys
A request may provide an idempotency key through two channels:
1. **Explicit Caller-Supplied Key**: Passed via HTTP header `Idempotency-Key` or payload attribute `idempotency_key` (common in external integrations, webhooks, and modern API clients).
2. **System-Generated Synthetic Key**: Derived deterministically by the system when the caller does not supply an explicit key.

To reconcile both models seamlessly without data loss:
* The `idempotency_key` column in `posting_idempotency_records` stores the primary lookup key:
  - If the caller supplies an explicit key, that exact key is stored in `idempotency_key` (scoped strictly by `domain_uuid`).
  - If the caller omits an explicit key, the system generates a canonical synthetic key and stores it in `idempotency_key`.
* Regardless of whether the key is caller-supplied or synthetic, the 5-tuple canonical identity columns are always extracted and stored in dedicated table columns for tenant auditability, tracing, and multi-field querying:
  - `source_module`: e.g. `pos`, `travelos`, `api`, `voucher_ui`, `copilot`
  - `company_code`: target company tenant code (normalized uppercase, e.g. `MAIN`, `TRAVEL_EXP`)
  - `domain_uuid`: foreign UUID of the `LedgerDomain`
  - `source_transaction_id`: external invoice/ticket/voucher reference ID (e.g. `EXP-2026-0089`)
  - `operation`: financial action (`post`, `reverse`, `amend`)
  - `version`: contract schema revision (`v1`)

When synthetic keys are generated, they follow the canonical serialized format:
```text
{source_module}:{company_code}:{source_transaction_id}:{operation}:{version}
```

*Example Synthetic Keys*:
- `travelos:MAIN:EXP-2026-0089:post:v1`
- `pos:KAMAL_EXPRESS:TKT-991204:post:v1`
- `voucher_ui:HOLDING:JV-2026-1044:post:v1`

### 4.3 Cross-Company Isolation Examples

To illustrate how composite uniqueness protects multi-tenant isolation, consider two distinct companies hosted on the same database cluster:

| Tenant Attribute | Company 1 (Main Entity) | Company 2 (Subsidiary Entity) |
| :--- | :--- | :--- |
| **`company_code`** | `MAIN` | `SUB_TRAVEL` |
| **`domain_uuid`** | `11111111-1111-1111-1111-111111111111` | `22222222-2222-2222-2222-222222222222` |
| **Caller Key** | `EXP-2026-10-001` | `EXP-2026-10-001` |

* **Scenario A: Flawed Global Uniqueness (`UNIQUE(idempotency_key)`)**:
  1. Company 1 posts expense voucher with caller key `EXP-2026-10-001`. Key is inserted globally.
  2. Company 2 independently posts their own expense voucher with their internal reference `EXP-2026-10-001`.
  3. **Failure**: Company 2's request crashes with `QueryException` (duplicate key) or falsely replays Company 1's financial transaction, causing cross-tenant data leakage or denial of service.
* **Scenario B: Proposed Composite Tenant Uniqueness (`UNIQUE(domain_uuid, idempotency_key)`)**:
  1. Company 1 posts with key `EXP-2026-10-001`. Row inserted: `('11111111-...', 'EXP-2026-10-001')`. Ledger entry created in Company 1's ledger.
  2. Company 2 posts with key `EXP-2026-10-001`. Row inserted: `('22222222-...', 'EXP-2026-10-001')`. Ledger entry created in Company 2's ledger.
  3. **Outcome**: Both transactions succeed independently with complete cryptographic and ledger isolation. Neither company can block, observe, or collide with the other.

---

## 5. Field-Aware Deterministic Payload Fingerprinting

To detect key reuse with conflicting arguments (tampering, unintended copy-paste, or client bugs), a cryptographic SHA-256 fingerprint of the normalized request payload must be stored.

### 5.1 Field-by-Field Normalization Rules
Generic JSON serialization is insufficient because floating-point numbers, whitespace, and key ordering introduce false mismatches. Payload canonicalization must be **strictly field-aware**:

> [!IMPORTANT]
> **Type Safety Mandate**: NEVER indiscriminately cast numeric-looking fields to float. Non-monetary identifiers (such as account numbers `0110`, phone numbers, customer IDs, and reference numbers) MUST remain strings to preserve leading zeros and formatting.

1. **Monetary Decimal Amounts**:
   - Only fields explicitly defined in the accounting schema as monetary currency amounts (`amount`, `debit`, `credit`, `tax_amount`, `subtotal`, `total`, `unit_price`, `discount_amount`) are normalized to fixed-point strings with exactly 4 decimal places: `number_format((float)$val, 4, '.', '')`.
   - Zeroes are normalized: `0.0000`, `0`, and `-0.0000` all normalize to `"0.0000"`.
   - In double-entry lines, amounts are decomposed into non-negative `debit` and `credit` strings:
     `"debit": "1500.0000"`, `"credit": "0.0000"`.
2. **Account Codes & Non-Monetary Identifiers**:
   - `account_code`, `reference`, `source_transaction_id`, `tax_id`, and `cost_center` must **NEVER** be cast to float or integer.
   - Preserved as trimmed uppercase strings. For example, account code `"0110"` (Cash) remains `"0110"` and is never converted to `110`.
3. **Calendar Dates**:
   - Normalized to ISO-8601 calendar date format `YYYY-MM-DD` (e.g., `"2026-10-16"`).
   - Any time or timezone component is parsed and truncated to the accounting transaction date.
4. **Currencies**:
   - Trimmed uppercase 3-letter ISO-4217 code (e.g., `"PKR"`, `"USD"`).
   - If omitted, normalized to the domain's default currency resolved via `DomainContext::getDefaultCurrency()`.
5. **Account Intent vs. Dynamic Mappings**:
   - When a high-level intent is passed (e.g. `category: "office_supplies"`), the fingerprint hashes both:
     - The input intent: `category => "office_supplies"`
     - The resolved target ledger account: `resolved_debit_account => "4200"`
     If an accountant reconfigures the category mapping in company settings between retries, the fingerprint changes, preventing silent posting to a different ledger account under the same key.
6. **Line Items & Sorting**:
   - Line items are sorted deterministically before hashing using composite sorting:
     `account_code ASC`, then `debit ASC`, then `credit ASC`, then `description ASC`.
   - This ensures that line order permutation does not cause false-positive payload mismatches.
7. **Quantities**:
   - Integer quantities remain integer strings (`"1"`, `"5"`). Fractional quantities (e.g. units of service) are normalized to fixed 4-decimal precision.
8. **Transient Field Elimination**:
   - The following transport/metadata fields are explicitly excluded from the fingerprint:
     `['idempotency_key', 'timestamp', 'request_time', 'current_time', 'request_id', '_token', 'created_at', 'updated_at', 'agent_notes_transient']`.

### 5.2 Mathematical Fingerprint Contract & Qualified Equality

$$\text{payload\_hash} = \text{SHA256}(\text{CanonicalJson}(\text{Normalize}(P)))$$

The equality claim:
$$\text{payload\_hash}(P_1) = \text{payload\_hash}(P_2) \iff \text{SemanticAccountingEquality}(P_1, P_2)$$
is formally qualified as follows:
1. **Cryptographic Collision Resistance**: SHA-256 provides a collision probability of less than $2^{-128}$ against birthday attacks, rendering hash collisions practically impossible in operational accounting.
2. **Canonical Domain Projection**: Semantic accounting equality is evaluated strictly over the projected business attributes (accounts, debits, credits, currencies, transaction dates, and resolved mapping intent). Discarded transient transport fields (such as client request timestamps or temporary correlation IDs) are intentionally non-semantic.

---

## 6. Database Reservation, Locking & Concurrency Protocol

```mermaid
sequenceDiagram
    autonumber
    actor Client
    participant Service as PostingIdempotencyService
    participant DB as Database (posting_idempotency_records)
    participant Ledger as Abivia Ledger & Domain Pivot

    Client->>Service: POST /expenses {idempotency_key, payload}
    Service->>DB: INSERT INTO posting_idempotency_records (status: 'in_flight', created_at: now())
    alt Insert Succeeds (Worker Won Race)
        Service->>Ledger: BEGIN TX: Add JournalEntry + Add DomainJournalEntry(reference)
        Ledger-->>Service: Commit Successful
        Service->>DB: UPDATE posting_idempotency_records SET status='committed', response_data=...
        Service-->>Client: 201 Created {is_idempotent_replay: false, ...}
    else Unique Constraint Violation (Concurrent Worker Lost Race)
        Service->>DB: SELECT * FROM posting_idempotency_records WHERE domain_uuid = ? AND idempotency_key = ?
        alt Status is 'committed' & Hash Matches
            Service-->>Client: 200 OK (Cached Response, is_idempotent_replay: true)
        else Status is 'committed' & Hash Differs
            Service->>DB: Record IDEMPOTENCY_PAYLOAD_MISMATCH Audit
            Service-->>Client: 422 Unprocessable (Payload Mismatch)
        else Status is 'in_flight' & Lock Age < 30s
            Service-->>Client: 409 Conflict (Retry-After: 2)
        else Status is 'in_flight' & Lock Age >= 30s (Stale Lock Recovery)
            Service->>DB: Conditional UPDATE status='in_flight', created_at=now() WHERE age >= 30s
            alt Reclaimed Lock
                Service->>Ledger: Execute Transaction
            else Failed Reclaim
                Service-->>Client: 409 Conflict (Retry-After: 2)
            end
        end
    end
```

### 6.1 Reservation Protocol & Concurrency-Loser Sequence

The reservation and concurrency protocol proceeds in discrete steps:

1. **Step 1: Normalization & Hash Calculation**:
   - The incoming request payload is canonicalized using the field-aware rules in Section 5.1.
   - The SHA-256 `payload_hash` is computed.
2. **Step 2: Pre-Transaction Reservation (`INSERT`)**:
   - Before opening the ledger transaction, the service attempts an autonomous `INSERT` into `posting_idempotency_records`:
     ```sql
     INSERT INTO posting_idempotency_records (
         domain_uuid, company_code, idempotency_key, payload_hash,
         source_module, source_transaction_id, operation, version,
         request_payload, status, created_at, updated_at
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_flight', NOW(), NOW());
     ```
   - The database unique constraint `UNIQUE (domain_uuid, idempotency_key)` acts as the atomic mutex.
   - If the insert succeeds, this worker has won the execution rights and proceeds to Step 4 (Winner Ledger Execution).
3. **Step 3: Concurrent-Loser Recovery Sequence**:
   - If the insert throws a `QueryException` (SQLSTATE 23505 / 23000 / 19 unique violation), this worker has lost the initial reservation race.
   - The losing worker queries the existing record:
     `SELECT * FROM posting_idempotency_records WHERE domain_uuid = ? AND idempotency_key = ?`
   - The worker branches based on the existing record's state:
     - **Case A: `status === 'committed'`**:
       - Compare `existing.payload_hash === current.payload_hash`.
       - If matching: Return HTTP 200 OK immediately with cached `response_data` and header `X-Idempotent-Replay: true`.
       - If mismatch: Reject with HTTP 422 Unprocessable (`IdempotencyPayloadMismatchException`), logging a security audit event (`IDEMPOTENCY_PAYLOAD_MISMATCH`).
     - **Case B: `status === 'in_flight'` and Lock Age < 30 seconds**:
       - The initial worker is actively executing the transaction.
       - The losing worker rejects with HTTP 409 Conflict, returning header `Retry-After: 2` and error code `idempotency_conflict_in_flight`.
     - **Case C: `status === 'in_flight'` and Lock Age >= 30 seconds (Stale Lock Recovery)**:
       - The previous worker crashed or timed out before completing or failing.
       - Attempt atomic lock reclamation via conditional update:
         ```sql
         UPDATE posting_idempotency_records
         SET status = 'in_flight', created_at = NOW(), payload_hash = ?, updated_at = NOW()
         WHERE domain_uuid = ? AND idempotency_key = ? AND status = 'in_flight' AND created_at <= ?;
         ```
       - If affected rows == 1: This worker reclaims the lock and proceeds to Step 4.
       - If affected rows == 0: Another concurrent worker reclaimed the lock; return HTTP 409 Conflict (`Retry-After: 2`).
     - **Case D: `status === 'failed'`**:
       - The previous attempt encountered an unpostable business validation error or transient failure.
       - If payload hash matches: return HTTP 422 with the stored error message.
       - If client provides corrected payload or forces retry: permit re-reservation.
4. **Step 4: Winner Ledger Execution**:
   - Proceed to the atomic transaction boundary (Section 7.1).

---

## 7. Separation of Atomic Ledger Posting vs. Durable Failure Records

Institutional accounting systems require strict separation between what must roll back together and what must survive rollback:

### 7.1 Atomic Transaction Boundary (All-or-Nothing Commit)
The following operations execute strictly inside `DB::transaction(...)`:
1. Journal Entry creation in Abivia Ledger (`journal_entries`, `journal_entry_lines`).
2. Domain association in `domain_journal_entries` with `reference` (enforces `UNIQUE (domainUuid, reference)`).
3. Transition of `posting_idempotency_records` to `status = 'committed'`, storing `voucher_reference`, `journal_entry_id`, and `response_data`.
4. Successful posting audit record in `accounting_audit_trails` (`CREATE_VOUCHER` / `INTEGRATION_POSTED`).

**Rollback Guarantee**: If any constraint fails (unbalanced debits/credits, closed period, non-leaf category posting, or duplicate voucher reference), the transaction aborts completely. Zero ledger entries or domain associations are persisted.

### 7.2 Autonomous Durable Failure Boundary (Survives Rollback)
When an exception occurs during ledger posting, the transaction rolls back. An independent `catch (Throwable $e)` block executes outside the failed transaction to guarantee auditability:
1. `posting_idempotency_records` is updated to `status = 'failed'` with `error_message = $e->getMessage()`.
2. A durable failure entry is inserted into `unposted_integration_entries` (`status = 'failed'`, `retry_count = 1`, `error_message`, `payload`).
3. An audit record is written to `accounting_audit_trails` with action `INTEGRATION_POSTING_FAILED`.

This ensures that accountants can view, diagnose, and reprocess failed entries via the reconciliation workbench without leaving orphaned transactions in the general ledger.

### 7.3 Interruption Recovery for the Failure Handler
A critical edge case in distributed accounting is when the failure handler itself is interrupted (e.g. SIGKILL, container eviction, OOM, power loss, or database connection drop during the `catch` block):

* **Interruption State**: The database record in `posting_idempotency_records` remains stranded in `status = 'in_flight'` with its original `created_at` timestamp. No ledger entry was committed (rolled back by RDBMS crash recovery).
* **Self-Healing Recovery Mechanisms**:
  1. **Reactive Recovery on Client Retry**:
     - When the client retries the request after network timeout, the request arrives after the 30-second TTL.
     - The worker triggers the Stale Lock Recovery branch (Section 6.1, Case C).
     - The worker verifies that no ledger entry exists in `domain_journal_entries` for this reference/key, reclaims the in-flight lock, and executes the posting cleanly.
  2. **Proactive Reconciliation Sweeper (`ReconcileStaleInFlightPostingsJob`)**:
     - A background scheduled artisan command runs periodically (every 5 minutes):
       ```sql
       SELECT * FROM posting_idempotency_records
       WHERE status = 'in_flight' AND created_at < NOW() - INTERVAL 5 MINUTE;
       ```
     - For each stranded record:
       - It inspects `domain_journal_entries` by `reference` and `domainUuid`.
       - If no ledger entry exists: It safely marks the record as `status = 'failed'` with `error_message = 'Interrupted: worker terminated before transaction commit'`, enqueues an entry to `unposted_integration_entries`, and emits an alert for accountant review.
       - If a ledger entry does exist (an ultra-rare edge case where the transaction committed but the worker died before returning): It repairs the record to `status = 'committed'`, caches the response, and completes the audit trail.

---

## 8. Proposed Database Schema Changes (for Day 2 / Issue #37)

The persistence layer implementation for Day 2 will introduce the following concrete schema definitions:

### 8.1 Table: `posting_idempotency_records`
```php
Schema::create('posting_idempotency_records', function (Blueprint $table) {
    $table->id();
    $table->string('domain_uuid', 36)->index();
    $table->string('company_code', 50)->index();
    $table->string('idempotency_key', 191);
    $table->string('source_module', 50)->default('integration')->index();
    $table->string('source_transaction_id', 100)->nullable()->index();
    $table->string('operation', 50)->default('post');
    $table->string('version', 20)->default('v1');
    $table->string('payload_hash', 64)->index();
    $table->json('request_payload')->nullable();
    $table->string('status', 30)->default('in_flight')->index(); // in_flight, committed, failed
    $table->string('voucher_reference', 100)->nullable()->index();
    $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
    $table->json('response_data')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();

    // Tenant-scoped unique constraint
    $table->unique(['domain_uuid', 'idempotency_key'], 'domain_idempotency_unique');
    $table->index(['company_code', 'status']);
});
```

### 8.2 Alter Table: `domain_journal_entries`
```php
Schema::table('domain_journal_entries', function (Blueprint $table) {
    if (!Schema::hasColumn('domain_journal_entries', 'reference')) {
        $table->string('reference', 100)->nullable()->after('journalEntryId')->index();
    }
    $table->unique(['domainUuid', 'reference'], 'domain_reference_unique');
});
```

### 8.3 Alter Table: `operational_sales`
```php
Schema::table('operational_sales', function (Blueprint $table) {
    $table->unique(['company_code', 'idempotency_key'], 'op_sales_company_idempotency_unique');
});
```

---

## 9. Plan for Subsequent Daily Tasks in the Issue #8 DAG

This specification serves as the formal gate for the daily task breakdown:

```mermaid
graph TD
    T36["Day 1: #36 Identity & Replay Contract (ADR-008)"] --> T37["Day 2: #37 Persistence & DB Constraints"]
    T37 --> T38["Day 3: #38 Core PostingIdempotencyService"]
    T38 --> T39["Day 4: #39 Expense & Voucher Integration"]
    T39 --> T40["Day 5: #40 POS & Sales Integration"]
    T40 --> T41["Day 6: #41 Concurrency & Recovery Tests"]
    T37 --> T42["Parallel: #42 Audit & Reconciliation Workbench"]
    T41 --> T43["Day 7: #43 Certification Gate & Release"]
    T42 --> T43
```

| Task | Day | Title & Deliverable |
| :--- | :---: | :--- |
| **[#36](https://github.com/AlamiaSoft/AlamiaAccounts/issues/36)** | **Day 1** | **Current Task**: Reviewable ADR-008 (Proposed) with pure contract/documentation. |
| **[#37](https://github.com/AlamiaSoft/AlamiaAccounts/issues/37)** | **Day 2** | Add `posting_idempotency_records` table, unique constraints on `domain_journal_entries` and `operational_sales`, and model tests. |
| **[#38](https://github.com/AlamiaSoft/AlamiaAccounts/issues/38)** | **Day 3** | Implement core `PostingIdempotencyService` with field-aware normalization, reservation, and atomic transaction wrapper. |
| **[#39](https://github.com/AlamiaSoft/AlamiaAccounts/issues/39)** | **Day 4** | Refactor `ExpenseIntegrationService` and `VoucherService` to eliminate all JSON string scans. |
| **[#40](https://github.com/AlamiaSoft/AlamiaAccounts/issues/40)** | **Day 5** | Refactor `SalesIntegrationService` to enforce stable identity, replay, and payload mismatch rejection. |
| **[#41](https://github.com/AlamiaSoft/AlamiaAccounts/issues/41)** | **Day 6** | Comprehensive concurrency and recovery regression test suite (SQLite & PostgreSQL). |
| **[#42](https://github.com/AlamiaSoft/AlamiaAccounts/issues/42)** | **Parallel** | Audit trail correlation and unposted entry reconciliation tools. |
| **[#43](https://github.com/AlamiaSoft/AlamiaAccounts/issues/43)** | **Day 7** | Certification gate and evidence-based sign-off for Issue #8 closure. |

---

## 10. Learning Capture Review

1. **Multi-Tenant Constraint Scoping**:
   Global unique constraints on client-supplied idempotency keys violate tenant isolation. All idempotency constraints must be composite with `domain_uuid` or `company_code`.
2. **Fixed-Point Formatting in PHP**:
   Standard `json_encode` in PHP serializes floats with variable trailing zeros (e.g. `500.0` vs `500.00`). Hashing requires explicit formatting with `number_format((float)$val, 4, '.', '')` to avoid false-positive payload mismatches.
3. **Autonomous Failure Survival**:
   In Laravel, recording failures inside a transaction that subsequently rolls back causes the failure record itself to be discarded. Failure logging must execute in an independent catch block outside the rolled-back transaction.
