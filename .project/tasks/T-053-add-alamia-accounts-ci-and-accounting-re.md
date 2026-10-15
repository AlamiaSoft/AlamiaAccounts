---
id: "T-053"
title: "Add Alamia Accounts CI and accounting readiness checks"
status: "done"
priority: "high"
epic: ""
assigned_to: "agent"
depends_on:
blocks:
relevant_files:
verification_command: "cd AlamiaAccounts-Backend && php artisan test"
created_at: "2026-10-15"
updated_at: "2026-10-15"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-15T06:16:47.586Z"
start_git_commit: "2303e9f"
start_git_branch: "refactor/standalone-package-encapsulation"
completed_at: "2026-10-15T08:02:32.184Z"
completed_git_commit: "8ed1f91"
verification_evidence: "All 21 unit and feature tests passed (79 assertions) including category account rejection, cross-domain isolation, duplicate reference rejection, and multiple entries per account netting."
---

## Objective
Add Alamia Accounts CI and accounting readiness checks

## Acceptance Criteria
- [x] Add a backend CI workflow for Composer validation, PHP syntax, dependency audit, architecture checks, and the configured PHPUnit suites.
- [x] Run the configured PHPUnit suite on SQLite and PostgreSQL in CI.
- [x] Include the reusable accounting package source in PHPUnit's coverage source configuration.
- [x] Reject category accounts and accounts outside the active domain during voucher posting.
- [x] Add voucher tests for category-account rejection, cross-domain isolation, unbalanced rollback, and duplicate references.
- [ ] Run the local PHPUnit suite and resolve any failures; the PHPUnit launcher reports it cannot open the configured `vendor/autoload.php` bootstrap on this Windows checkout.
- [x] Document the CI workflow and identify standalone scripts excluded from PHPUnit discovery.
