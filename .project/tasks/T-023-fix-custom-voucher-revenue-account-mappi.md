---
id: "T-023"
title: "Fix Custom Voucher Revenue Account Mapping & Dynamic Commission Line Items"
status: "done"
priority: "high"
epic: ""
assigned_to: "agent"
depends_on:
blocks:
relevant_files:
verification: ""
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T11:30:40.619Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T11:34:05.424Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Corrected hardcoded initial state in CustomVoucherEntry from 5100 (Owner's Capital) to 3100 (Sales Revenue), fixed default revenue account fallbacks in SalesIntegrationService and PosSalesApproval, and implemented dynamic agent commission detection with 1-click accounting lines addition (Commission Expense Dr & Commission Payable Cr). Verified with automated tests and production build."
---

## Objective
Fix Custom Voucher Revenue Account Mapping & Dynamic Commission Line Items

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
