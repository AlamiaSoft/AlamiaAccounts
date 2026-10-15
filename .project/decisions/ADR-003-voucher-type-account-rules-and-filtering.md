# ADR-003: Voucher Type Account Rules & Parametric Line Filtering Architecture

## Status
Accepted (2026-10-08)

## Context
Vouchers in double-entry accounting are multi-leg transactions ($N$ debits, $M$ credits). Previously, voucher type definitions risked coupling to flat header accounts (`default_debit_account`, `default_credit_account`) which failed to model compound vouchers and multi-tenant chart-of-accounts isolation. Furthermore, when creating specialized vouchers (e.g. Petty Cash, Fee Collection, Asset Purchase), accountants need instant UI guidance restricting debits and credits strictly to configured account groups, backed by institutional server-side write barriers.

## Decision
Adopt a Hybrid, Three-Tiered Architecture for Voucher-Scoped Account Rules and Filtering:

1. **Normalized Rules Schema**:
   - `custom_voucher_types` stores purely tenant-scoped voucher type metadata (`name`, `prefix`, `company_code`, `active`).
   - `voucher_account_rules` defines line-level rules per side (`side: 'debit' | 'credit'`), allowed account groups (`account_groups: JSON`), and optional default accounts (`default_account: string`).

2. **Parametric Server-Side Accounts API**:
   - `GET /api/accounts` accepts query filters: `?voucher_type_id={id}&side={debit|credit}`, `?groups=Cash,Bank`, and `?posting_only=true`.
   - The backend evaluates allowed account groups dynamically and returns eligible leaf posting accounts for third-party consumers, POS systems, or mobile clients.

3. **Client-Side High-Performance Cache & Instant Filter**:
   - The web SPA caches the active company's chart of accounts using TanStack Query.
   - The UI evaluates active voucher type `account_rules` locally for 0ms latency in combobox search, restricting dropdown selections to allowed groups per line side.

4. **Hard Backend Enforcement Barrier**:
   - Upon `POST /api/vouchers`, `CustomVoucherTypeService::applyAccountRules()` and `VoucherService` evaluate all journal details. Invalid account entries dated for that voucher type are strictly rejected with HTTP 422.

## Consequences
- Single source of truth: Rules are stored relationally and verified on the server.
- Zero latency: Combobox filtering remains instant without per-keystroke server roundtrips.
- Institutional safety: Zero heuristic guessing and zero unpermitted account postings in the general ledger.
