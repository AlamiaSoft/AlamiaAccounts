# ADR-0002: TanStack React Query for State Synchronization

## Status
Accepted

## Date
2026-02-15

## Context
Complex accounting states (Chart of Accounts tree, dynamic voucher line items, running balances) require low-latency caching and automatic background refetching.

## Decision
We chose TanStack React Query (`@tanstack/react-query`) to encapsulate server queries and mutations in dedicated hooks (`hooks/use-*.ts`).
