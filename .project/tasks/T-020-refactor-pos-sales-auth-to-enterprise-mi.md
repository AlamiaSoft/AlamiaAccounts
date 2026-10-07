---
id: "T-020"
title: "Refactor POS & Sales Auth to Enterprise Middleware Pipeline & Clean Services"
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
started_at: "2026-10-07T10:57:59.937Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T11:00:25.963Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Refactored POS and Sales Authentication into standard enterprise HTTP Middleware (AuthenticateSalesOrSanctum). Eliminated all ad-hoc token parsing and authorization methods from SalesIntegrationService and SalesIntegrationController. Cleaned up duplicate route definitions in api.php. All integration tests passed (11/11 Sales Integration, 14/14 Custom Voucher, Sanctum HTTP 200)."
---

## Objective
Refactor POS & Sales Auth to Enterprise Middleware Pipeline & Clean Services

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
