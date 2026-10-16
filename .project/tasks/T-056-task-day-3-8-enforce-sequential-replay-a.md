---
id: "T-056"
title: "[Task][Day 3][#8] Enforce sequential replay and reject idempotency payload conflicts"
status: "done"
priority: "high"
epic: ""
assigned_to: "agent"
depends_on:
  - "T-055"
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Exceptions/IdempotencyPayloadMismatchException.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/PostingIdempotencyService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ExpenseIntegrationService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/PostingIdempotencyRecord.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/ExpenseIdempotencySequentialReplayTest.php"
verification: "php artisan test --filter=ExpenseIdempotencySequentialReplayTest"
created_at: "2026-10-16"
updated_at: "2026-10-16"
decision_refs:
  - "ADR-008"
files:
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Exceptions/IdempotencyPayloadMismatchException.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/PostingIdempotencyService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ExpenseIntegrationService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/PostingIdempotencyRecord.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Feature/ExpenseIdempotencySequentialReplayTest.php"
claimed_by: "agent"
started_at: "2026-10-16T12:50:18.623Z"
start_git_commit: "baf57b2"
start_git_branch: "task/issue-38-sequential-replay-idempotency"
completed_at: "2026-10-16T12:58:08.398Z"
completed_git_commit: "baf57b2"
verification_evidence: "ExpenseIdempotencySequentialReplayTest 8/8 PASS; Full Backend Suite 66/66 PASS; Architecture Auditor 7/7 PASS"
---

## Objective
Wire the approved idempotency contract into expense integration so an identical sequential retry returns the original posting, while reuse of the same identity with a different payload is rejected (Day 3 of Issue #8 DAG, Issue #38).

## Acceptance Criteria
- [x] **Sequential replay never creates a second journal posting**:
  - Replaying the identical payload returns `is_idempotent_replay: true` with cached journal entry reference and ID.
  - Asserted zero increase in `journal_entries`, `domain_journal_entries`, or `posting_idempotency_records`.
- [x] **Replay returns original posting identity/result**:
  - Replay response contains the original `voucher_reference`, `journal_entry_id`, accounts, and amount.
- [x] **Reusing identity with different fingerprint is rejected and leaves no second posting**:
  - Mutating amount, category (debit account), payment method (credit account), or other payload attributes throws `IdempotencyPayloadMismatchException` (422).
  - Asserted zero increase in ledger entries on mismatch.
  - Recorded security violation in `accounting_audit_trails` with `action: IDEMPOTENCY_PAYLOAD_MISMATCH`.
- [x] **Multi-tenant isolation allows same identity across different domains without collision**:
  - Verified Company `MAIN` and Company `KAMAL_EXPRESS` can post with identical reference and key simultaneously without collision.
- [x] **Zero deduplication depends on metadata substring search**:
  - Eliminated unindexed `extra LIKE '%"reference":...%'` scan; all deduplication is powered by `posting_idempotency_records` table and database constraints.

## Verification Evidence
1. **Targeted Feature Suite**:
   - Command: `php artisan test --filter=ExpenseIdempotencySequentialReplayTest`
   - Result: 8 passed (40 assertions) in 2.07s.
2. **Full Backend Suite**:
   - Command: `php artisan test`
   - Result: 66 passed (229 assertions) in 9.04s.
3. **Architecture Auditor**:
   - Command: `node scripts/verify-architecture.js`
   - Result: 7/7 suites passed (100% compliance).

## Learning Capture
- **Finding 1: Decimal-Safe String Normalization Eliminates Float Drift**:
  - Normalizing monetary numbers through integer and 4-decimal string decomposition (`150.0000`) prevents binary IEEE-754 precision drift from causing false payload mismatch detections.
- **Finding 2: Resolved Account Intent in Payload Fingerprint**:
  - Hashing both high-level intent (`category`, `payment_method`) and resolved ledger accounts (`resolved_debit_account`, `resolved_credit_account`) ensures that configuration changes in account mappings between retries are safely surfaced as conflicts rather than posting to unexpected accounts.
