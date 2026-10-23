---
id: "T-067"
title: "[P0][Accounting] Centralize posting validation and enforce approval at the write boundary (Issue #9)"
status: "in_progress"
priority: "critical"
epic: "EP-09"
assigned_to: "agent"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/PostingValidationService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Exceptions/PostingValidationException.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/VoucherController.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Copilot/AccountsCopilotBridge.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/config/alamia-accounts.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/CentralizedPostingValidationAndApprovalTest.php"
verification: "cd AlamiaAccounts-Backend && php artisan test --filter=CentralizedPostingValidationAndApprovalTest"
created_at: "2026-10-23"
updated_at: "2026-10-23"
decision_refs:
  - "ADR-008"
files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/PostingValidationService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Exceptions/PostingValidationException.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/VoucherController.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Copilot/AccountsCopilotBridge.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/config/alamia-accounts.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/CentralizedPostingValidationAndApprovalTest.php"
claimed_by: "agent"
started_at: "2026-10-23T07:03:06.892Z"
start_git_commit: "3ec0517"
start_git_branch: "task/issue-9-centralize-posting-validation"
---

## Objective
[P0][Accounting] Centralize posting validation and enforce approval at the write boundary (Issue #9)

## Acceptance Criteria
- [x] Define one authoritative posting use case used by UI, API, Copilot and external integrations (`PostingValidationService`).
- [x] Enforce on every posting: authorized company, postable leaf accounts belonging to that company, positive amounts, balanced totals, valid date/period, currency, reference/idempotency, permission and approval state.
- [x] Define maker/checker policy and enforce it server-side at commit time, including separation of requester and approver where required (`maker !== approver`).
- [x] Reject contradictory line payloads (e.g. debit and credit both positive on one line, or contradictory type) and ambiguous amount/type combinations rather than guessing.
- [x] Make posting outcomes and validation errors consistent across entry points.
- [x] Invalid payloads cannot create partial journal entries (atomic rollback verified).
- [x] All postings have authenticated actor, source and audit correlation IDs recorded in accounting audit trails.
- [x] Parameterized tests verify unbalanced, zero/negative, contradictory, missing-account, category-account, cross-domain and invalid-date payloads (8/8 pass).
