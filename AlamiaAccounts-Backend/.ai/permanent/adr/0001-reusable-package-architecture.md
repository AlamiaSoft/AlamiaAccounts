# ADR-0001: Reusable Standalone Accounting Engine Package

## Status
Accepted

## Date
2026-02-15

## Context
The accounting engine features were initially developed as prototype scripts. To ensure reusability across any Laravel project, core logic needed to be extracted into a package.

## Decision
We moved all domain models, services, API controllers, routes, migrations, and seeders into `packages/AlamiaSoft/alamia-accounts` mounted as a path repository.
