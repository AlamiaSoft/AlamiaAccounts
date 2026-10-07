---
id: "T-010"
title: "Cashier Shift Closure & End-of-Day Physical Cash Handover UI"
status: "done"
priority: "high"
epic: "EP-05-pos-cashier-operations"
assigned_to: "frontend"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Frontend/components/pos-sales-approval.tsx"
  - "AlamiaAccounts-Frontend/components/cashbook.tsx"
decisions:
  - "ADR-002-pos-integration-contract"
verification: "cd AlamiaAccounts-Frontend && npm run build"
tags:
  - "frontend"
  - "pos"
  - "reconciliation"
created_at: "2026-10-07T14:10:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T09:14:57.989Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T09:17:16.187Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Frontend build passed with 0 errors. Cashier shift closure modal with denomination calculator and discrepancy slips completed."
---

## Objective
Provide front-desk cashiers and managers an interactive shift closure modal to count currency notes, compare against expected cash collected from `GET /api/v1/sales/reconcile-shift`, and print a formal Cash Drawer Handover Slip.

## Acceptance Criteria
- [ ] Denomination counter (5000, 1000, 500, 100, 50, 20, 10, coins)
- [ ] Live discrepancy computation (Balanced / Surplus / Shortage)
- [ ] Shift handover print slip with cashier & supervisor signatures
- [ ] Clean Next.js build with 0 TypeScript errors
