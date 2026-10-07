---
id: "T-022"
title: "Encapsulate Company Default Currency in CompanyService and DomainContext Helper"
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
started_at: "2026-10-07T11:16:38.432Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T11:17:33.556Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Created DomainContext::getDefaultCurrency() and CompanyService::getDefaultCurrency() to fully encapsulate company currency lookups. Eliminated direct Abivia kernel domain queries from VoucherController. All tests passing (14/14 custom voucher, 11/11 sales integration, custom voucher submission HTTP 201)."
---

## Objective
Encapsulate Company Default Currency in CompanyService and DomainContext Helper

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
