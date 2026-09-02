# Project Rules & Guidelines for AI Agents

Welcome to **Alamia Accounts**. All AI agents operating on this repository must strictly adhere to the following architectural, accounting, and testing guidelines.

---

## 1. Browser End-to-End (E2E) Testing Policy

> [!IMPORTANT]
> **TOKEN PRESERVATION & SCRIPT PERSISTENCE MANDATE**
> - **DO NOT generate ad-hoc, repetitive inline test code** inside tool calls.
> - **ALL E2E test scripts must be stored in the repository under [`tests/e2e/`](file:///e:/Alamia/AlamiaAccounts/tests/e2e)** so they can be re-run indefinitely without consuming model generation tokens.
> - **DO NOT run browser tests automatically on every turn** unless the user explicitly requests it. Suggest/ask the user before running tests.
> - When running tests, execute the pre-built scripts using `run_command`:
>   ```bash
>   node tests/e2e/run-all.js
>   ```

### Available Test Suites in `tests/e2e/`

| Suite File | Scope / Purpose | Command |
| :--- | :--- | :--- |
| [`run-all.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/run-all.js) | Full regression suite runner (Suites 01 to 10) | `node tests/e2e/run-all.js` |
| [`01-auth-navigation.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/01-auth-navigation.test.js) | Login, app title, branding & company header | `node tests/e2e/01-auth-navigation.test.js` |
| [`02-chart-of-accounts.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/02-chart-of-accounts.test.js) | COA hierarchy, folders (`1120`), leaf accounts & parent dropdown | `node tests/e2e/02-chart-of-accounts.test.js` |
| [`03-financial-reports.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/03-financial-reports.test.js) | Trial Balance (`Dr=Cr`), P&L calculations & Balance Sheet | `node tests/e2e/03-financial-reports.test.js` |
| [`04-multi-company-isolation.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/04-multi-company-isolation.test.js) | Company switching, tenant isolation & zero-leakage check | `node tests/e2e/04-multi-company-isolation.test.js` |
| [`05-voucher-entry-ux.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/05-voucher-entry-ux.test.js) | Interactive voucher creation, debit/credit inputs, validation | `node tests/e2e/05-voucher-entry-ux.test.js` |
| [`06-opening-balance-and-coa-ux.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/06-opening-balance-and-coa-ux.test.js) | COA modals, confirmation dialogs, z-index validation | `node tests/e2e/06-opening-balance-and-coa-ux.test.js` |
| [`07-master-e2e-accounting.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/07-master-e2e-accounting.test.js) | Full browser UI-driven accounting certification (21/21 PASS) | `node tests/e2e/07-master-e2e-accounting.test.js` |
| [`08-group-ledger-and-subledgers.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/08-group-ledger-and-subledgers.test.js) | Group rollup balances, AR & AP subledgers, customer ledgers | `node tests/e2e/08-group-ledger-and-subledgers.test.js` |
| [`09-voucher-lifecycle-negative.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/09-voucher-lifecycle-negative.test.js) | Duplicate references, unbalance blocks, category post rejection | `node tests/e2e/09-voucher-lifecycle-negative.test.js` |
| [`10-accountant-production-readiness.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/10-accountant-production-readiness.test.js) | Institutional hardening, period locks, compound OB, Daybook reversal, audit logs (15/15 PASS) | `node tests/e2e/10-accountant-production-readiness.test.js` |

---

## 2. Institutional Double-Entry Accounting Invariants

1. **Double-Entry Balance Invariant**:
   - Every journal entry must strictly satisfy: `sum(debits) === sum(credits)`.
   - Never bypass this constraint or disable ledger validation.
2. **Category / Folder Accounts vs. Posting Accounts**:
   - Accounts with `category: true` are non-posting grouping folders (`1000 Assets`, `1100 Current Assets`, `1120 Bank Accounts`, etc.).
   - Journal transactions can **only** be posted to leaf accounts (`category: false`).
   - If an account has existing transactions, it cannot be converted into a category.
3. **Multi-Tenant Isolation**:
   - All transactions are partitioned by `domainUuid` in `domain_journal_entries`.
   - Standard Chart of Accounts is inherited from the base template via `domain_ledger_accounts` with clean initial balances (Rs. 0.00).
   - All API calls must supply the `X-Company-Code` header to respect tenant boundaries.
4. **Historical Ledger Immutability (GAAP/IFRS)**:
   - Posted accounting vouchers must **never** be physically deleted or purged from ledger tables (`DELETE /api/vouchers/{ref}` returns HTTP 422).
   - If a transaction requires correction or voiding, use **Reverse + Correct** via `POST /api/vouchers/{ref}/reverse`, which creates a compensating `REV-` voucher with documented audit reasons.
5. **Compound Opening Balances (`OB-`)**:
   - Opening balance positions must be established using the dedicated batch endpoint `POST /api/opening-balances` (`OB-YYYY-001`).
   - All balance sheet accounts (Assets, Liabilities, Equity) must balance compoundly; any difference must be explicitly allocated to Capital (`5100`) or Retained Earnings (`5200`).
   - Duplicate opening balance batches are prohibited per tenant.
6. **Fiscal Accounting Periods & Period Locking**:
   - The fiscal year is partitioned into 12 monthly accounting periods (`accounting_periods`).
   - Once a period is locked (`status: closed`), ordinary postings dated in that period are blocked (HTTP 422).
   - Reopening a locked period requires a documented business reason and is logged in the audit trail.
7. **Permanent Accounting Audit Trail**:
   - All financial operations (`POST_OPENING_BALANCES`, `CREATE_VOUCHER`, `REVERSE_VOUCHER`, `CLOSE_PERIOD`, `REOPEN_PERIOD`) are recorded in `accounting_audit_trails` capturing user, action, timestamp, IP, entity, and reasons.

---

## 3. Standard API Endpoints & Routes

| Endpoint | Method | Purpose |
| :--- | :---: | :--- |
| `/api/vouchers` | `GET` / `POST` | List and create journal/contra/payment/receipt vouchers |
| `/api/vouchers/{ref}/reverse` | `POST` | Reverse posted voucher (creates `REV-` compensating entry) |
| `/api/vouchers/{ref}` | `DELETE` | **BLOCKED (422)**: Preserves double-entry audit history |
| `/api/opening-balances` | `GET` / `POST` | Check status & post compound balanced opening position |
| `/api/periods` | `GET` | List 12 fiscal periods for given year |
| `/api/periods/{id}/close` | `POST` | Lock accounting period |
| `/api/periods/{id}/reopen` | `POST` | Reopen locked period with documented business reason |
| `/api/audit-trail` | `GET` | Retrieve chronological accounting audit history |
| `/api/reports/balance-sheet` | `GET` | Assets, Liabilities, Equity & retained earnings balance |
| `/api/reports/trial-balance` | `GET` | Debit/Credit trial balance (`Dr === Cr`) |
| `/api/reports/profit-loss` | `GET` | Revenue, Expenses, and Net Profit |

---

## 4. Documentation & Knowledgebase Standards

- All architectural changes and core accounting logic additions must be documented in markdown under [`docs/`](file:///e:/Alamia/AlamiaAccounts/docs).
- Technical chapters belong in `docs/manual/` with YAML frontmatter.
- Machine-readable rules and intent mappings for the AI Copilot must be maintained in `docs/copilot/`.
- Executive readiness certification sign-offs belong in `docs/ACCOUNTANT_READINESS_CERTIFICATION.md`.
- Accountant operational instructions belong in `docs/ACCOUNTANT_UAT_GUIDE.md`.
