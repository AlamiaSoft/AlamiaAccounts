# Backend Architecture & System Boundaries

## 1. Architectural Intent
`AlamiaAccounts-Backend` hosts the RESTful API services for the Alamia Accounts accounting system. The core design principle is the **strict decoupling of domain accounting logic from host infrastructure**.

```
+-------------------------------------------------------------------------+
|                  Host Application (Laravel 11 Project)                  |
|       - Authentication: Laravel Sanctum (Bearer Token / Cookies)        |
|       - Authorization: Spatie Permissions (RBAC)                        |
|       - File Storage, Runtime Configuration, Worker / Queue Environment |
+-------------------------------------------------------------------------+
                                     │
                                     ▼ (Composer Path Repository: @dev)
+-------------------------------------------------------------------------+
|         Standalone Accounting Package (packages/AlamiaSoft/alamia-accounts)|
|   - Controllers: AccountController, VoucherController, ReportController |
|   - Services: AccountService, VoucherService, ReportService, etc.       |
|   - Multi-Domain Scoping: DomainContext (Active Domain Resolver)        |
+-------------------------------------------------------------------------+
                 │                                            │
                 ▼ (Non-Invasive Scoping Pivots)              ▼ (Double-Entry Core)
+------------------------------------+       +----------------------------+
|  Alamia Domain Association Tables  |       |    Abivia Ledger Engine    |
|   - domain_ledger_accounts         |       |   - LedgerDomain           |
|   - domain_journal_entries         |       |   - LedgerAccount          |
|   - voucher_number_series          |       |   - JournalEntry & Details |
+------------------------------------+       +----------------------------+
                 │                                            │
                 └───────────────────────┬────────────────────┘
                                         ▼
                             +------------------------+
                             |     SQLite Database    |
                             |   (Local-First Engine) |
                             +------------------------+
```

---

## 2. Core Architectural Invariants

1. **Non-Invasive Upstream Schema Rule**:
   - Upstream `abivia/ledger` database tables must **never** be directly modified.
   - Domain scoping and multi-company segregation are enforced via dedicated pivot tables:
     - `domain_ledger_accounts` (maps `domainUuid` to `accountUuid`)
     - `domain_journal_entries` (maps `domainUuid` to `journalEntryUuid`)
   - Any query or creation flow MUST route through `AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount` and `DomainJournalEntry`.

2. **Domain Isolation & Execution Context**:
   - Every accounting operation occurs within an explicit active domain (`DomainContext::get()`).
   - If no domain is specified in the request header or execution state, `DomainContext` defaults to the root/first domain or fails closed when performing mutation operations.
   - Cross-domain data leakage is prevented by verifying that all referenced debit/credit accounts belong to the active domain before committing journal vouchers.

3. **Strict Double-Entry Ledger Discipline**:
   - No ledger entry can be posted with unbalanced debits and credits.
   - All transactions are represented as immutable `JournalEntry` records with granular split details.
   - Vouchers (Sales, Purchase, Payment, Receipt, Journal) map directly into double-entry journal transactions.

---

## 3. Package Structure & Responsibilities

* **`AlamiaAccountsServiceProvider`**: Automatically registers package routes, migrations, and binds singleton services.
* **`DomainContext`**: Thread/request-scoped state manager tracking the active domain (Company/Branch/Department) code and UUID.
* **`AccountService`**: Manages chart of accounts (creation, hierarchy traversal, balance computation, domain binding).
* **`VoucherService`**: Orchestrates voucher lifecycles (Sales, Purchase, Payment, Receipt, Journal). Enforces domain account validation and balances debits/credits before posting to the Abivia journal controller.
* **`VoucherNumberingService`**: Manages auto-incrementing serials with domain prefixes, financial year tokens, and type schemes (`voucher_number_series` table).
* **`ReportService`**: Compiles real-time Trial Balance, Profit & Loss, Balance Sheet, and Account Ledger statements.
* **`SearchService` & `PrintService`**: Cross-entity search and JSON template-driven PDF export.

---

## 4. Known Failure Modes & Mitigations

1. **Context Leakage Across Sub-Requests**:
   - *Risk*: `DomainContext::$currentDomain` static state persisting across subsequent async operations or worker queues.
   - *Mitigation*: Reset `DomainContext` per request middleware and utilize `DomainContext::scope()` scoped execution blocks.

2. **Unassigned Account Queries**:
   - *Risk*: Accounts created directly via raw Abivia controllers without registering in `domain_ledger_accounts` becoming orphaned or inaccessible in domain filtered views.
   - *Mitigation*: All account mutations must route strictly through `AccountService::createAccount()`.
