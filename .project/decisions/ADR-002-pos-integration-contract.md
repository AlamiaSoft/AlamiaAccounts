# ADR-002: Front-Office POS & Double-Entry Sales Gateway

## Status
Accepted (2026-10-07)

## Context
Frontline booking desks (e.g. Kamal Express Travels) and retail POS apps require simple sales submission, but standard double-entry accounting demands balanced ledger vouchers (`SV-` for receivable/revenue and `RV-` for cash collection).

## Decision
1. Provide a REST integration endpoint (`POST /api/v1/sales`) accepting either structured line items or normalized booking form fields.
2. Automate double-entry voucher orchestration via `VoucherService` wrapper:
   - Sales Voucher (`SV-`): Dr Accounts Receivable (`1200`), Cr Sales Revenue (`5100`).
   - Receipt Voucher (`RV-`): Dr Cash in Hand (`1110`), Cr Accounts Receivable (`1200`).
3. Enforce idempotency via `idempotency_key` and staff scoping so frontline agents only see their own shifts.

## Consequences
- Frontline staff never interact directly with debits/credits.
- Permanent general ledger remains mathematically balanced and fully audited.
