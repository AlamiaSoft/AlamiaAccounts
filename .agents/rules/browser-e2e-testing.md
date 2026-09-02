---
trigger: always_on
description: Mandatory rule for running browser E2E tests using repository scripts instead of inline code generation.
---

# Browser E2E Testing Rule

## Directive
When testing accounting workflows, UI navigation, or financial reports:
1. **NEVER** generate inline Playwright scripts repeatedly in conversation turns or tool calls.
2. **ALWAYS** use the pre-built, reusable test scripts in [`tests/e2e/`](file:///e:/Alamia/AlamiaAccounts/tests/e2e).
3. To run all tests, execute:
   ```bash
   node tests/e2e/run-all.js
   ```
4. To run an individual test:
   - Auth & UI Navigation: `node tests/e2e/01-auth-navigation.test.js`
   - Chart of Accounts: `node tests/e2e/02-chart-of-accounts.test.js`
   - Financial Reports: `node tests/e2e/03-financial-reports.test.js`
   - Tenant Isolation: `node tests/e2e/04-multi-company-isolation.test.js`
5. If testing a new workflow, create a new `.test.js` file in `tests/e2e/` and add it to `run-all.js` so it remains reusable forever.
