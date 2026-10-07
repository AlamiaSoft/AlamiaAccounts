# ADR-001: Hybrid Knowledge Architecture for ERP Rules

## Status
Accepted (2026-10-07)

## Context
Hardcoding accounting policies in procedural code causes maintenance drift. Storing exclusively in a database risks losing version history.

## Decision
Adopt a Hybrid Knowledge Architecture:
1. Markdown files stored in `docs/copilot/standard-guidance/` serve as the version-controlled source of truth.
2. An artisan sync command (`php artisan copilot:sync-knowledge`) loads Markdown into SQLite (`copilot_knowledge_entries`).
3. Runtime queries evaluate dynamically against the synchronized database cache.

## Consequences
- 100% database-driven runtime with zero hardcoded policy strings in PHP classes.
- Full Git tracking and diff capability for accounting rules.
