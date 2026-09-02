# Project Rules & Guidelines for AI Agents

Welcome to **Alamia Accounts**. All AI agents operating on this repository must strictly adhere to the following architectural and testing guidelines.

---

## 1. Browser End-to-End (E2E) Testing Policy

> [!IMPORTANT]
> **TOKEN PRESERVATION & SCRIPT PERSISTENCE MANDATE**
> - **DO NOT generate ad-hoc, repetitive inline test code** inside tool calls.
> - **ALL E2E test scripts must be stored in the repository under [`tests/e2e/`](file:///e:/Alamia/AlamiaAccounts/tests/e2e)** so they can be re-run indefinitely without consuming model generation tokens.
> - When verifying features, bug fixes, or accounting balances, **execute the pre-built scripts** using `run_command`:
>   ```bash
>   node tests/e2e/run-all.js
>   ```
> - If a new accounting scenario is tested, **add it as a permanent test file under `tests/e2e/`** (or extend an existing suite) before running it.

### Available Test Suites in `tests/e2e/`

| File | Scope | Command |
| :--- | :--- | :--- |
| [`run-all.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/run-all.js) | Full regression suite runner | `node tests/e2e/run-all.js` |
| [`01-auth-navigation.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/01-auth-navigation.test.js) | Login, app title, branding & company header | `node tests/e2e/01-auth-navigation.test.js` |
| [`02-chart-of-accounts.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/02-chart-of-accounts.test.js) | COA hierarchy, folders (`1120`), leaf accounts & parent dropdown | `node tests/e2e/02-chart-of-accounts.test.js` |
| [`03-financial-reports.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/03-financial-reports.test.js) | Trial Balance (`Dr=Cr`), P&L calculations & Balance Sheet | `node tests/e2e/03-financial-reports.test.js` |
| [`04-multi-company-isolation.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/04-multi-company-isolation.test.js) | Company switching, tenant isolation & zero-leakage check | `node tests/e2e/04-multi-company-isolation.test.js` |

---

## 2. Double-Entry Accounting Invariants

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

---

## 3. Documentation & Manual Maintenance

- Any architectural changes, accounting logic additions, or domain-specific workflows must be documented in markdown under [`docs/`](file:///e:/Alamia/AlamiaAccounts/docs).
- Technical chapters belong in `docs/manual/` with YAML frontmatter.
- Machine-readable rules and intent mappings for the AI Copilot must be maintained in `docs/copilot/`.
