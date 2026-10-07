---
id: "T-021"
title: "Fix Custom Voucher Payload Mapping for Currency and Entries"
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
started_at: "2026-10-07T11:10:45.840Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T11:13:30.192Z"
completed_git_commit: "e9a94d4"
verification_evidence: "Updated VoucherController and VoucherService to dynamically default currency to the active company's configured default currency from LedgerDomain/DomainContext, normalized incoming lineItems/details into entries format, and enabled custom_fields and custom voucher types in extra metadata. Verified with automated integration tests (HTTP 201 pass, metadata persistence pass)."
---

## Objective
Fix Custom Voucher Payload Mapping for Currency and Entries

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
