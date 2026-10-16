---
id: data-retention-and-disaster-recovery
title: Data Retention, Immutability & Disaster Recovery Policy
module: Security & Governance
tags: [gaap, ifrs, immutability, data-retention, disaster-recovery, p0-security]
api_endpoints:
  - POST /api/vouchers/{ref}/reverse
  - DELETE /api/vouchers/{ref}
invariants:
  - historical_ledger_immutability
  - production_reset_prohibition
---

# Data Retention, Ledger Immutability & Disaster Recovery

## 1. Institutional Double-Entry Ledger Immutability (GAAP / IFRS)

In accordance with institutional GAAP and IFRS standards, posted financial records in Alamia Accounts are **permanently immutable**:
- **Zero Physical Deletion**: Direct deletion of posted vouchers (`DELETE /api/vouchers/{ref}`) is strictly blocked at the API gateway and service boundaries (returning HTTP 422 `CANNOT_DELETE_POSTED_VOUCHER`).
- **Correction via Compensating Reversals**: To correct or void a posted voucher, accountants must utilize the formal reversal workflow (`POST /api/vouchers/{ref}/reverse`). This creates a compensating `REV-` transaction that neutralizes the financial impact while preserving full audit history and the original journal entry.
- **Mandatory Justification**: Reversals require a substantive business justification ($\ge 15$ characters and 3 words) recorded permanently in `accounting_audit_trails`.

## 2. Production Protection Against Bulk Ledger Reset

Bulk ledger resets physically purge journal entries, line items, and operational sales records. Under [Issue #6](https://github.com/AlamiaSoft/AlamiaAccounts/issues/6), destructive resets are governed by a multi-layer security defense:

1. **Production Environment Lock**: Destructive reset operations cannot be executed when `app()->environment('production')` is active. Both the API controller and domain service unconditionally fail closed with HTTP 403 `production_reset_prohibited`.
2. **Default-Off Feature Flag**: The setting `alamia-accounts.allow_destructive_reset` defaults to `false`. Routes and service methods reject reset attempts unless this flag is explicitly enabled.
3. **Route Exclusion**: In production environments or when the feature flag is disabled, the route `POST /api/vouchers/clear-all` is not registered in the application routing table.
4. **Elevated Role Authorization**: Permitted non-production resets require authenticated administrative credentials (`admin`, `owner`, `super_admin`). Normal accountants, staff, and machine POS integration tokens are strictly denied (HTTP 403 `insufficient_privileges`).
5. **Confirmation Token Guard**: Resets require an explicit confirmation token matching `CONFIRM_RESET_{COMPANY_CODE}`.
6. **Strict Tenant Scoping**: A reset operation cannot cross company domain boundaries. Attempting to supply a different company code is rejected with HTTP 403 `company_access_denied`.

## 3. Data Retention & Archival Standards

1. **Statutory Retention Period**: Financial records, vouchers, audit logs, and transaction details must be retained for a minimum of **10 years** from the close of the relevant fiscal period.
2. **Audit Trail Retention**: Entries in `accounting_audit_trails` and `posting_idempotency_records` must never be purged during routine maintenance. Telemetry and diagnostic traces may be pruned after 90 days.

## 4. Backup & Disaster Recovery Architecture

1. **Continuous Write-Ahead Logging (WAL)**: Production PostgreSQL databases must maintain continuous WAL archiving enabling Point-In-Time Recovery (PITR) with an RPO (Recovery Point Objective) $\le 5$ minutes.
2. **Automated Daily Snapshots**: Full encrypted database snapshots must be taken daily and replicated to an off-site secondary geographic region with an RTO (Recovery Time Objective) $\le 60$ minutes.
3. **Restoration Verification**: Periodic automated restore drills must verify database integrity, sequence synchrony, and double-entry balance consistency (`sum(debits) === sum(credits)` across all tenant domains).
