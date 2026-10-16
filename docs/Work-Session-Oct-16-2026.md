# Autonomous Execution Plan — Alamia Accounts

**Canonical repository:** `AlamiaSoft/AlamiaAccounts`  
**Target branch:** `main`  
**Objective:** Complete the outstanding GitHub issues in their established dependency and priority order, making Alamia Accounts a reliable, standalone, accountant-ready accounting core for Alamia Travel OS and the Kamal Express pilot.

## 1. Establish the current state

Before modifying code:

- Verify the repository root, Git remote, current branch, working-tree status, and latest `main` commit.
- Read the repository's execution-plan issue and inspect the current open GitHub Issues feed.
- Inspect existing pull requests, including draft PRs, CI results, review comments, and linked issues.
- Identify issues already resolved, duplicated, blocked, or superseded. Do not repeat completed work.
- Review project instructions, architecture documentation, accounting dependencies, existing schema, public APIs, and test conventions.
- Record the verified execution queue and dependencies before starting implementation.

## 2. Follow the established priority order

Use the repository's current issue state and dependency graph as the source of truth.

1. **Security and tenant/company isolation:** resolve the remaining security issues in the established order, including fail-closed company context. Respect the dependency that issue #5 must precede #8.
2. **Posting integrity:** implement durable idempotency and reliable posting behavior. Issue #9 depends on the preceding work.
3. **Accounting correctness:** work through the outstanding accounting issues, including the established issues #10–#14 and #16, in dependency order.
4. **Package boundaries:** address issue #15 without breaking standalone installation or existing integrations.
5. **Certification and UAT:** complete issue #17 only after its prerequisites are satisfied.

Re-check each issue's current status before acting. This sequence is a starting point from the existing plan, not permission to ignore newly discovered blockers or already completed work.

## 3. Implementation rules

- Work on one issue at a time in a dedicated branch and pull request.
- Keep changes narrow, maintainable, and backward-compatible where appropriate.
- Add regression tests for every meaningful fix.
- Preserve tenant/company isolation, balanced-ledger invariants, transaction atomicity, idempotency, and auditability.
- Inspect the existing accounting library and implementation before designing new ledger structures. Do not create parallel ledger tables or duplicate existing accounting functionality without a documented, justified need.
- Keep accounting-core changes in this repository. Do not mix Kamal-specific configuration or Travel OS workflows into the core.
- Never weaken tests, bypass authorization, suppress meaningful failures, or alter production behavior solely to obtain green CI.
- Do not commit directly to `main`, force-push, merge your own PRs, or overwrite unrelated working-tree changes. Use the project's established review and merge process.

## 4. Quality gates for each issue

For each issue:

1. Read the issue, acceptance criteria, dependencies, and related code.
2. Implement the smallest justified fix.
3. Add or update unit, feature, security, and accounting regression tests as appropriate.
4. Run targeted tests, then relevant broader suites and architecture/static checks.
5. Inspect the diff for unintended changes and regressions.
6. Open or update the issue-linked PR with the rationale, changed files, test commands, actual results, and remaining risks.
7. Verify CI and address failures before proceeding to the next dependent issue.

Never report a test as passing unless it was actually executed successfully. Clearly distinguish local results, CI results, and unverified assumptions.

## 5. Kamal Accounts validation

After a core change is ready for integration testing:

- Verify which local directory and package version Composer actually resolves for `alamiasoft/alamia-accounts`.
- Ensure Kamal Accounts is testing the intended version/commit of the core, not a stale or unrelated copy.
- Run relevant package and host-application tests.
- Validate real business scenarios where applicable, especially company isolation, journal posting, duplicate submissions, reversals, sales/POS posting, and reconciliation.
- Report integration failures separately from core defects. Fix core defects in Alamia Accounts; fix host-specific issues in Kamal Accounts.

Do not begin unrelated Travel OS work until the relevant accounting contract and core behavior are sufficiently verified.

## 6. Progress reporting and autonomy

Proceed autonomously through the ordered queue while the repository, permissions, and quality gates allow.

Maintain a concise execution log containing:

- Issue number and title
- Status and dependencies
- Branch and PR
- Implementation summary
- Tests actually executed and results
- CI status
- Blockers and decisions requiring human approval
- Next issue in priority order

If blocked by credentials, permissions, ambiguous accounting policy, destructive migration risk, or a decision that requires business/accountant approval, stop that specific change, document the blocker, and continue only with independent work that does not depend on it.

**Definition of success:** outstanding issues are resolved through reviewed PRs, regression tests and CI are green, the standalone package contract is preserved, and relevant fixes are validated in Kamal Accounts. Do not equate a green CI run alone with accounting certification.