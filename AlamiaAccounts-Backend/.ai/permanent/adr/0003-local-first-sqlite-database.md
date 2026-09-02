# ADR-0003: Local-First SQLite Database Engine

## Status
Accepted

## Date
2026-02-15

## Context
Local-first accounting requires zero-configuration, zero-latency database management and simple backup/portability workflows.

## Decision
We adopted SQLite (WAL mode) as the default engine, which also allows exploring `sqlite-vss` vector extensions for local semantic search.
