---
id: "T-015"
title: "Fix POS Workflow Mode Selector & Voucher Badges Synchronization"
status: "done"
priority: "critical"
epic: "EP-02-pos-integration"
assigned_to: "frontend"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Frontend/public/ke-pos.html"
  - "AlamiaAccounts-Frontend/components/pos-sales-approval.tsx"
  - "AlamiaAccounts-Frontend/hooks/use-sales.ts"
decisions:
  - "ADR-002-pos-integration-contract"
verification: "cd AlamiaAccounts-Frontend && npm run build"
tags:
  - "pos"
  - "bugfix"
  - "ux"
created_at: "2026-10-07T14:31:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T09:30:46.758Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T09:33:49.346Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Updated ke-pos.html with dynamic workflow mode selector (instant_post vs pending_approval), live status badges, receipt modal handling, and verified 8/8 backend sales integration tests pass and Next.js frontend builds with 0 errors."
---

## Objective
Ensure seamless synchronization between Kamal Express POS (`public/ke-pos.html`) and Alamia Accounts POS Approvals / Daybook. Allow the counter staff to choose between **Instant Post** (generates live `SV-` and `RV-` vouchers immediately) and **Stage for Manager Review** (queues in Alamia Accounts Pending Approvals tab for manager sign-off).

## Acceptance Criteria
- [ ] Add Posting Workflow dropdown to POS checkout form (`Instant Post` vs `Stage for Manager Review`).
- [ ] POS UI immediately updates local table and receipt with real `SV-` / `RV-` references returned by API instead of `SV-PENDING`.
- [ ] Staged sales appear in Alamia Accounts under the active tenant (`KAMAL_EXPRESS`) with one-click `"Approve & Post to Ledger"`.
- [ ] POS `fetchSalesFromApi()` retrieves all tenant sales and renders live status badges.
- [ ] Clean Next.js build with 0 errors.
