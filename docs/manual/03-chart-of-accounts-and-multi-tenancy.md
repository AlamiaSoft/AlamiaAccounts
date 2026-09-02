---
id: manual-03-chart-of-accounts
title: Chart of Accounts Architecture & Multi-Tenancy
module: Chart of Accounts
tags: [coa, accounts, category, folder, leaf, posting, normal_balance, multi_tenancy, isolation, domains]
api_endpoints:
  - GET /api/accounts
  - POST /api/accounts
  - PUT /api/accounts/{code}
  - DELETE /api/accounts/{code}
tables:
  - ledger_accounts
  - domain_ledger_accounts
  - ledger_domains
invariants:
  - "category accounts cannot accept journal voucher postings"
  - "account codes must be unique"
  - "balances are computed strictly per domainUuid"
---

# 03 - Chart of Accounts (COA) Architecture & Multi-Tenancy

This manual chapter documents the architecture, data model, validation rules, and multi-tenant scoping mechanisms governing the Chart of Accounts (COA) in Alamia Accounts.

---

## 1. Account Roles: Folder / Category vs. Posting Account

In double-entry bookkeeping and within the Abivia Ledger core engine, every account has an explicit structural role:

```text
               ┌─────────────────────────────────────────┐
               │         1000 Assets (Category)          │
               └────────────────────┬────────────────────┘
                                    │
               ┌────────────────────▼────────────────────┐
               │     1100 Current Assets (Category)      │
               └────────────────────┬────────────────────┘
                                    │
        ┌───────────────────────────┴───────────────────────────┐
        ▼                                                       ▼
┌───────────────────────────────┐               ┌───────────────────────────────┐
│     1110 Cash in Hand         │               │     1120 Bank Accounts        │
│   (Posting / Leaf Account)    │               │    (Category / Folder)        │
│     • Accepts vouchers        │               │     • Groups bank branches    │
│     • Running ledger balance  │               │     • Non-posting parent      │
└───────────────────────────────┘               └───────────────┬───────────────┘
                                                                │
                                    ┌───────────────────────────┴───────────────────────────┐
                                    ▼                                                       ▼
                    ┌───────────────────────────────┐                       ┌───────────────────────────────┐
                    │  1130 Meezan Bank Operations  │                       │ 1135 Bank Alfalah Operational │
                    │   (Posting / Leaf Account)    │                       │   (Posting / Leaf Account)    │
                    │     • Accepts vouchers        │                       │     • Accepts vouchers        │
                    └───────────────────────────────┘                       └───────────────────────────────┘
```

### A. Folder / Category Account (`category: true`)
- **UI Label**: *"Is Folder / Category (Non-posting parent)"*
- **Purpose**: Organizational grouping container used to structure the COA tree and compute rollup totals on financial statements (Balance Sheet, Trial Balance, P&L).
- **Posting Invariant**: **Journal vouchers CANNOT be posted to a category account.**
  - If any voucher detail attempts to debit or credit a category account, the ledger core rejects the request with:
    `Can't post to category account: [account_code]`
- **Examples**:
  - `1000 Assets` (Root category)
  - `1100 Current Assets` (Sub-category)
  - `1120 Bank Accounts` (Bank folder account)
  - `2000 Liabilities` (Root category)
  - `4000 Expenses` (Root category)

### B. Posting / Leaf Account (`category: false`)
- **UI Label**: *"Is Folder / Category (Non-posting parent)"* (Unchecked)
- **Purpose**: Operational account that records financial movements, running balances, and journal entries.
- **Posting Invariant**: **Only posting accounts can appear in journal details.**
- **Examples**:
  - `1110 Cash`
  - `1130 Meezan Bank - Main Operations`
  - `1135 Bank Alfalah - Operational A/C`
  - `1200 Accounts Receivable`
  - `3100 Sales Revenue`
  - `4200 Operating Expenses`
  - `5100 Owner's Capital`

---

## 2. Normal Balance Conventions

Every account must have an identified **Normal Balance Side**:

| Account Class | Code Range | Normal Balance Side | Default Category |
| :--- | :--- | :--- | :--- |
| **Assets** | `1000 - 1999` | **Debit Normal** | `1000 Assets` |
| **Liabilities** | `2000 - 2999` | **Credit Normal** | `2000 Liabilities` |
| **Equity** | `3000, 5100 - 5300` | **Credit Normal** | `3000 Equity` |
| **Expenses** | `4000 - 4999` | **Debit Normal** | `4000 Expenses` |
| **Revenue / Income** | `5000, 3100 - 3300` | **Credit Normal** | `5000 Revenue` |

### Backend Auto-Inference
If a user or API call creates an account without explicitly providing `debit`, `credit`, or `parent_code`, `AccountController.php` applies the following automated inference rules:
- Code starting with `1`: `debit = true`, `credit = false`, default parent `1000`
- Code starting with `2`: `debit = false`, `credit = true`, default parent `2000`
- Code starting with `4`: `debit = true`, `credit = false`, default parent `4000`
- Code starting with `31/32/33` or `50`: `debit = false`, `credit = true`, default parent `5000`
- Code starting with `51/52/53` or `30`: `debit = false`, `credit = true`, default parent `3000`

---

## 3. Multi-Tenant COA Initialization Architecture

Alamia Accounts uses a shared template database schema scoped by ledger domains (`ledger_domains`).

### Pivot Relationship: `domain_ledger_accounts`
The `domain_ledger_accounts` pivot table maps which accounts are visible to which company tenant:

```sql
CREATE TABLE domain_ledger_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domainUuid VARCHAR(36) NOT NULL,
    ledgerUuid VARCHAR(36) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    CONSTRAINT domain_account_unique UNIQUE (domainUuid, ledgerUuid),
    FOREIGN KEY (domainUuid) REFERENCES ledger_domains(domainUuid) ON DELETE CASCADE,
    FOREIGN KEY (ledgerUuid) REFERENCES ledger_accounts(ledgerUuid) ON DELETE CASCADE
);
```

> [!IMPORTANT]
> **Unique Constraint Business Rule**:
> - There is a composite unique constraint `(domainUuid, ledgerUuid)` ensuring an account cannot be linked to the same company twice.
> - There must **never** be a global unique constraint on `ledgerUuid` alone in this table. A global unique constraint would lock the standard Chart of Accounts to the first company (`MAIN`) and prevent fresh tenants from inheriting the base template.

### Automated Company Initialization Lifecycle
When a new company is created via `POST /api/companies` (or `CompanyService::createCompany`):

1. **Domain Creation**: Creates the company domain record in `ledger_domains` (e.g. code `KAMAL`, currency `PKR`).
2. **COA Auto-Seeding (`initializeCompanyChartOfAccounts`)**:
   - Fetches the standard root accounts and categories from the system template.
   - Inserts records into `domain_ledger_accounts` linking all 5 root categories and base accounts to the newly created company's `domainUuid`.
3. **Clean Slate Guarantee**:
   - The new company immediately sees the complete tree hierarchy in **Tree View**.
   - The new company's **Parent Account / Category** dropdown is populated with all valid categories.
   - **Balances are 100% isolated**: Because transaction queries join `domain_journal_entries.domainUuid`, every account for the new company begins with a verified balance of **Rs. 0.00**.

---

## 4. Editing Accounts & Structural Rules

When editing accounts through the UI or API:

1. **Converting an Account to Category**:
   - Allowed only if the account has **zero posted journal entries**.
   - If transactions have already been recorded to the account, the ledger core prohibits converting it to a category because that would invalidate historical posting integrity.
2. **Converting a Category to Non-Category**:
   - Allowed only if the category has **no category sub-accounts** underneath it.
3. **Reparenting**:
   - Cycles are strictly prohibited by the tree validator (`LedgerAccount::parentPath`). An account cannot be set as its own parent, nor can an ancestor be nested under its own descendant.
