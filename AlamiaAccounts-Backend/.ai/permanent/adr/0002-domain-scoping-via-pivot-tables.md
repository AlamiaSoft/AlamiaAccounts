# ADR-0002: Domain Scoping via Non-Invasive Pivot Tables

## Status
Accepted

## Date
2026-02-15

## Context
Multi-domain accounting requires accounts and journals to belong to specific companies/branches. Altering upstream Abivia schema would prevent cleanly updating composer vendor packages.

## Decision
We implemented non-invasive pivot tables `domain_ledger_accounts` and `domain_journal_entries`, enforcing domain scoping inside `AccountService` and `VoucherService`.
