# Production-Readiness Accounting & System Audit

**Alamia Accounts — Accountant Production Readiness & Hardening Audit**  
**Audit Date**: 2026-09-02  
**Baseline Status**: Mathematical Master E2E Suite Certified (21/21 PASS, Variance: Rs. 0.00)  
**Target Verdict**: ACCOUNTANT READY

---

## 1. Executive Summary

Alamia Accounts has successfully certified its double-entry core mathematics, zero reconciliation variance, parent account rollups, and multi-tenant ledger isolation under ideal test conditions.

However, a rigorous production-readiness inspection reveals **critical operational, architectural, and governance gaps** that prevent immediate handover to an accountant for live business accounting. Most notably:
1. **Destructive deletion endpoint (`DELETE /api/vouchers/{ref}`)** that physically purges journal details and entries.
2. **Missing Accounting Periods and Period Lock** allowing backdated or future-dated postings without boundaries.
3. **Flawed Opening Balance Entry model** that isolates single accounts and silently offsets all differences into Owner's Capital.
4. **Lack of an accountant-facing UI workflow for Voucher Reversal** (reversal exists only in API).
5. **Absence of a dedicated Accounting Audit Trail** tracking actor, timestamp, reason, and entity lifecycle.
6. **Non-atomic multi-step posting** susceptible to orphan ledger records if domain association fails.
7. **Silent fallback to `MAIN` domain** when requests omit the `X-Company-Code` header.

Below is the complete catalog of capabilities, risks, missing controls, and prioritized mitigations.

---

## 2. Audit Findings & Risk Matrix

| ID | Category | Severity | Handover Blocker? | Issue Description & Accounting Risk | Recommended Mitigation |
| :--- | :--- | :---: | :---: | :--- | :--- |
| **AUD-01** | Historical Immutability | **BLOCKER** | **YES** | `DELETE /api/vouchers/{reference}` physically executes SQL `delete()` on `journal_details` and `journal_entries`, destroying accounting history and violating GAAP/IFRS audit standards. | Disable physical voucher deletion. Enforce **Reverse + Correct** lifecycle. Return `422/405` error explaining that posted vouchers cannot be deleted. |
| **AUD-02** | Period Controls | **BLOCKER** | **YES** | Zero period-locking mechanisms exist. An accountant or user can post vouchers dated decades in the past or far in the future, altering finalized prior-period reports without warning. | Implement `accounting_periods` table and `PeriodService`. Reject ordinary postings dated in closed periods with user-friendly error. Support authorized reopen with reason. |
| **AUD-03** | Opening Balances | **BLOCKER** | **YES** | Opening balances are entered account-by-account with differences silently dumped into `5100 Owner's Capital`. Real companies have compound opening balance sheets (Cash, Bank, AR, Inventory, Assets, Depreciation, AP, Loans, Capital, Retained Earnings). | Create dedicated **Opening Balance Batch/Voucher** workflow (`OB-` reference). Require debits = credits or explicit accountant assignment of balancing differences. Prevent duplicate opening posts. |
| **AUD-04** | Voucher Correction UI | **BLOCKER** | **YES** | Backend endpoint `POST /api/vouchers/{ref}/reverse` exists, but the UI (Daybook, Voucher View) has no "Reverse Voucher" button or workflow. Accountants are forced to manually craft compensating journals. | Add "Reverse Voucher" action in Daybook and Voucher View with mandatory reversal reason modal and real-time status badges. |
| **AUD-05** | Atomic Posting | **BLOCKER** | **YES** | In `VoucherService::createJournalEntry()`, Abivia `journalController->add()` is followed by `DomainJournalEntry::create()` outside a database transaction. If domain linkage fails, orphan journal records remain in SQLite. | Wrap entire posting and domain linkage sequence in `DB::transaction(...)` to guarantee all-or-nothing atomicity. |
| **AUD-06** | Tenant Scoping Default | **HIGH** | **YES** | `DomainContext::get()` falls back to `LedgerDomain::first()` (`MAIN`) if `X-Company-Code` is missing. A misconfigured client could unintentionally write to or read from the primary company. | Throw `400 Bad Request: Missing X-Company-Code header` for tenant-scoped operations instead of falling back silently to `MAIN`. |
| **AUD-07** | Duplicate Submission | **HIGH** | **YES** | Duplicate reference checks currently query in-memory collections rather than enforcing transactional unique database constraints. Rapid double-clicks can create duplicate postings. | Add database unique index on `(domainUuid, reference)` or atomic lock during creation; enforce frontend button submission locks. |
| **AUD-08** | Audit Trail & Governance | **HIGH** | **YES** | No dedicated accounting audit trail logs which user created, posted, reversed, closed, or modified accounting entities. | Implement `accounting_audit_trails` table capturing actor, action, timestamp, entity reference, reason, and before/after state. |
| **AUD-09** | Error Message Polish | **MEDIUM** | **YES** | Certain database or validation failures expose framework exceptions or raw strings (e.g. `Breaker: account not found`) rather than clear, actionable accounting guidance. | Catch domain exceptions and map to friendly accounting error messages explaining the exact requirement (e.g., group vs posting account). |
| **AUD-10** | Cash & Bank Subledger Visibility | **MEDIUM** | **NO (Post-Handover)** | Cashbook and Daybook provide transaction tables, but dedicated Bank Reconciliation matching statement lines against ledger postings is not yet implemented. | Plan Bank Reconciliation module for Phase 2 roadmap. |

---

## 3. Detailed Component Analysis

### 3.1 Database & Multi-Tenant Model
- **Current State**: Uses Abivia Ledger core tables (`ledger_accounts`, `journal_entries`, `journal_details`, `ledger_domains`) partitioned via custom pivot tables (`domain_ledger_accounts`, `domain_journal_entries`).
- **Gaps**:
  - Missing `accounting_periods` table (`id`, `domain_uuid`, `fiscal_year`, `period_number`, `period_name`, `start_date`, `end_date`, `status`, `closed_at`, `closed_by`, `reopened_at`, `reopened_by`, `reopen_reason`).
  - Missing `accounting_audit_trails` table.
  - Missing unique constraint on `(domainUuid, reference)`.

### 3.2 Opening Balance Architecture
- **Current State**: Single account edit modal allows setting an opening balance, which generates a 2-line journal entry between the target account and `5100 Capital A/C`.
- **Gaps**:
  - Unsuitable for multi-account company inception.
  - Does not support assigning equity components between Paid-in Capital and Retained Earnings.
  - Allows multiple conflicting opening entries to be posted repeatedly.

### 3.3 Voucher Lifecycle & Immutability
- **Current State**: Supports creation and reversal via API. Daybook renders all vouchers.
- **Gaps**:
  - Active `destroy()` route allows hard deletion of journal entries.
  - No UI trigger for voucher reversal with audit reason.
  - No direct visual link in the UI between original voucher `VCH-xxx` and compensating `REV-VCH-xxx`.

### 3.4 Period Controls
- **Current State**: Date input accepts any valid ISO date. No validation against fiscal calendar.
- **Gaps**:
  - Cannot close a month or year.
  - Cannot lock books after tax or financial statement filing.

---

## 4. Phase-by-Phase Remediation Roadmap

1. **Phase 2.1 — Opening Balance Engine**:
   - Migration for opening balance batch tracking.
   - Compound Opening Balance entry endpoint (`POST /api/opening-balances`).
   - Dedicated UI modal allowing full balance sheet entry with live debit/credit reconciliation.
2. **Phase 2.2 — Period Controls**:
   - Migration for `accounting_periods`.
   - `PeriodService` enforcing open/closed checks during voucher posting.
   - Period management API and UI settings card.
3. **Phase 2.3 — Transaction Immutability & Reversal UI**:
   - Remove/disable physical `DELETE` on posted vouchers.
   - Add "Reverse Voucher" modal to Daybook & Voucher View with reason input.
   - Visual badges: `Posted`, `Reversed`, `Compensating Entry`.
4. **Phase 2.4 — Atomicity & Duplicate Locks**:
   - Wrap voucher creation in `DB::transaction()`.
   - Add database unique reference constraints and UI submission debouncing.
5. **Phase 4 — Audit Trail**:
   - Migration and model for `accounting_audit_trails`.
   - Event listeners/service hooks logging all voucher creations, reversals, and period status changes.
6. **Phase 5 & 6 — Test Suite & Certification**:
   - Develop new E2E suite [`tests/e2e/10-accountant-production-readiness.test.js`](file:///e:/Alamia/AlamiaAccounts/tests/e2e/10-accountant-production-readiness.test.js).
   - Generate [`docs/ACCOUNTANT_READINESS_CERTIFICATION.md`](file:///e:/Alamia/AlamiaAccounts/docs/ACCOUNTANT_READINESS_CERTIFICATION.md) and [`docs/ACCOUNTANT_UAT_GUIDE.md`](file:///e:/Alamia/AlamiaAccounts/docs/ACCOUNTANT_UAT_GUIDE.md).
