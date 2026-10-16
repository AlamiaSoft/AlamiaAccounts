# Alamia Accounts — Abivia Boundary (Hard Rule)

## Non-negotiable contract

Client/consumer code must **never** call the Abivia Ledger package directly. Consumers use Alamia Accounts-owned services and contracts. This applies to controllers, middleware, jobs, commands, Copilot/AI adapters, integrations, frontend code, feature tests, end-to-end tests, and manual verification scripts.

A test for a public Alamia Accounts workflow must enter through the Alamia Accounts public contract. It must not import/query Abivia models or call Abivia services to arrange, execute, or assert the workflow. Use Alamia-owned services for operations and Alamia-owned read-only contracts or narrow persistence assertions for verification.

## Allowed internal boundary

The current internal integration boundary is an explicit file-level allowlist in `scripts/abivia-boundary.js`. Do not add a directory-wide exemption. The approved files are existing Alamia-owned ledger/domain services and explicitly listed Alamia-owned ORM/persistence mapping models that map to Abivia domain identity. Any new Abivia reference outside those files is a violation until reviewed and explicitly approved.

Direct Abivia access in tests is permitted only under `AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Adapters/Abivia/`, and only when the test specifically validates the Abivia adapter boundary. Such tests do not replace public-contract tests. Do not create a generic adapter-test exception or rename ordinary feature tests to evade the rule.

## Automated enforcement

- `node scripts/test-abivia-boundary.js` must pass. It contains negative fixtures proving that consumer imports, fully qualified model calls, and non-model package APIs fail the scanner, plus positive fixtures for the narrow approved boundary.
- `node scripts/verify-architecture.js` must pass before a task can be considered verified.
- CI must run the scanner tests and repository-wide architecture audit when backend/frontend code, scripts, these rules, or the workflow changes.
- The scanner reports file and line for every violation. Do not weaken the pattern, broaden the allowlist, or suppress an entire test suite to obtain green CI.
- Comments/documentation mentioning Abivia are not code dependencies and should not trigger the scanner.
- A green result means only that the automated checks passed; it does not replace human architecture review.

## Required behavior when a capability is missing

Add or extend an Alamia Accounts-owned service/contract. Do not bypass the boundary for convenience, even in tests. Before adding an exception, explain why the operation is truly part of the internal integration adapter and add a focused positive/negative test for that exception.
