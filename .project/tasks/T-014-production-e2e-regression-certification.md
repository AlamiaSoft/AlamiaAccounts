---
id: T-014
title: Full Production E2E Regression Certification (Suites 01 to 10 + POS Integration)
status: backlog
priority: critical
epic: EP-07-kamal-express-pilot
assigned_to: reviewer
depends_on: [T-010, T-011, T-013]
blocks: []
relevant_files:
  - tests/e2e/run-all.js
  - tests/e2e/10-accountant-production-readiness.test.js
decisions:
  - ADR-001-hybrid-knowledge
  - ADR-002-pos-integration-contract
verification: node tests/e2e/run-all.js
tags:
  - e2e
  - certification
  - production
created_at: 2026-10-07T14:15:00Z
updated_at: 2026-10-07T14:15:00Z
---

## Objective
Comprehensive manual end-to-end testing and formal sign-off conducted thoroughly by QA developers and lead accountant on the live Kamal Express staging environment before production deployment.

## Acceptance Criteria
- [ ] Manual walkthrough of all core accounting workflows (Chart of Accounts, Opening Balances, Journal/Payment/Receipt/Sales Vouchers, Period Locking, Daybook reversals).
- [ ] Manual walkthrough of Front-Office POS counter checkout (`public/ke-pos.html`), cash & credit sales, and thermal receipt printing.
- [ ] Manual walkthrough of Manager Staged Sales Approvals and Cashier Shift Handover calculations.
- [ ] Double-entry verification on Financial Reports: Trial Balance ($Dr = Cr$), Profit & Loss, and Balance Sheet.
- [ ] Automated regression suite (`node tests/e2e/run-all.js`) and Next.js production build (`npm run build`) passing 100% with zero console errors.
- [ ] Formal QA Developer & Lead Accountant sign-off recorded.
