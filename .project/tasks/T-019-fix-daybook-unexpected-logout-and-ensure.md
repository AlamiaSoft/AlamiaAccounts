---
id: "T-019"
title: "Fix Daybook unexpected logout and ensure custom vouchers display correctly"
status: "done"
priority: "critical"
epic: "EP-01-core-architecture"
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
started_at: "2026-10-07T10:48:40.182Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T10:52:02.180Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Resolved Daybook 401 logout issue by enabling Sanctum Bearer token resolution in validateTenantAuthorization. Enhanced Daybook custom voucher badge coloring and parsing. Verified with automated test test_sanctum_sales_call.php (HTTP 200) and verify_custom_voucher_system.php (14/14 PASS)."
---

## Objective
Fix Daybook unexpected logout and ensure custom vouchers display correctly

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
