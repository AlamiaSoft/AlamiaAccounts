---
id: "T-045"
title: "Automated Architectural & Coding Standards Conformance Test Suite"
status: "done"
priority: "high"
epic: "EP-01-core-ledger"
assigned_to: "agent"
depends_on:
blocks:
relevant_files:
verification: "node scripts/verify-architecture.js"
created_at: "2026-10-07"
updated_at: "2026-10-07"
decision_refs:
files:
  - "scripts/verify-architecture.js"
claimed_by: "agent"
started_at: "2026-10-07T23:00:01.251Z"
start_git_commit: "7e70fa2"
start_git_branch: "main"
completed_at: "2026-10-07T23:05:41.871Z"
completed_git_commit: "7e70fa2"
verification_evidence: "Architectural conformance auditor scripts/verify-architecture.js created covering 7 suites, successfully detecting and highlighting violations."
---

## Objective
Build a dedicated, automated architectural compliance scanner (`node scripts/verify-architecture.js`) that statically audits the codebase for violations of AGENTS.md Rule 8 (Abivia Encapsulation), Rule 9 (Zero Hardcoded Heuristics), GAAP Immutability, Package Boundaries, and DAG integrity.

## Acceptance Criteria
- [x] Create static architectural auditor `scripts/verify-architecture.js` covering 7 distinct invariant suites.
- [x] Pinpoint exact file, line number, offending code, and remediation guidance for refactor agents.
- [x] Return non-zero exit code on detected violations to gate CI / Agent-PM execution.
