---
id: "T-065"
title: "[Task][Day 7][#8] Enforce company-scoped voucher-reference uniqueness (Issue #42)"
status: "done"
priority: "critical"
epic: "EP-08"
assigned_to: "agent"
depends_on:
  - "T-064"
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainJournalEntry.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/PostingIdempotencyService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/database/migrations/2026_10_22_000002_harden_domain_journal_entries_reference_uniqueness.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/CompanyScopedVoucherReferenceUniquenessTest.php"
  - "docs/architecture/voucher-reference-uniqueness.md"
verification: "cd AlamiaAccounts-Backend && php artisan test packages/AlamiaSoft/alamia-accounts/tests/Feature/CompanyScopedVoucherReferenceUniquenessTest.php && cd .. && node scripts/verify-architecture.js"
created_at: "2026-10-22"
updated_at: "2026-10-23"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-22T12:24:42.449Z"
start_git_commit: "9935d07"
start_git_branch: "task/issue-42-voucher-reference-uniqueness"
completed_at: "2026-10-23T06:46:34.448Z"
completed_git_commit: "5eccbfd"
verification_evidence: "PR #71 merged into main. CI Run 39913015497 100% green (sqlite, pgsql, syntax/architecture). 5/5 feature tests passing (42 assertions). Case-insensitive company-scoped voucher-reference uniqueness enforced at DB and service layer."
---

## Objective
[Task][Day 7][#8] Enforce company-scoped voucher-reference uniqueness (Issue #42)

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
