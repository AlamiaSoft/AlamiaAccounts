---
trigger: always_on
description: Governs cross-repository path resolution for TravelOS, CodeGraph-first exploration, evidence standards in audits, one-day task sizing, and merge-gated completion tracking.
---

# Cross-Repository & DAG Execution Governance Rule

## 1. Canonical Repository Paths & CodeGraph Mandate
- **Alamia Accounts (Standalone Accounting Core)**: `e:\Alamia\AlamiaAccounts`
- **Alamia TravelOS / SaaS Platform Starter (Host App)**: `F:\Playgrounds\Alamia-SaaS-Platform-Starter`
- Both repositories are indexed by **CodeGraph** (`.codegraph/`). **ALWAYS** query `codegraph_explore` (MCP tool) or `codegraph explore "<query>"` before falling back to broad grep/file scans in either repository.
- Before planning or auditing across repositories, refresh and verify the actual current branch and `HEAD` commit SHA in each repository (`git rev-parse HEAD`, `git status`).

## 2. Audit & Diagnosis Evidence Standard
When writing architectural audits, root-cause analyses, or issue plans, explicitly separate:
1. **Code Inspected**: Exact file paths, line ranges, and commit SHAs read.
2. **Tests Actually Run**: Exact commands executed and their observed pass/fail output.
3. **Root Causes Confirmed**: Defects proven by code and reproducible test execution.
4. **Hypotheses Requiring Reproduction**: Suspected issues not yet proven by an executed test.

## 3. One-Working-Day Task Limit & Cross-Repository Splitting
- Every implementation task in the DAG must fit comfortably within **one working day** (including code, tests, CI verification, and evidence).
- Never bundle multi-day or multi-repository changes into a single task; split cross-repository work into separate, dependency-ordered tasks per repository and concern.

## 4. Merge & CI-Gated Task Completion
- **NEVER** mark an Agent-PM task `DONE`, check off a DAG item, or close a GitHub issue merely because child issues were created or a PR was opened.
- Completion may be recorded **only** when:
  1. The PR is **merged** to `main`,
  2. All 3 required GitHub Actions CI checks (`PHPUnit (sqlite)`, `PHPUnit (pgsql)`, `Composer, syntax, and architecture checks`) are **green**, and
  3. Concrete verification evidence (test output, commit SHA, CI run link) is posted on the issue.

## 5. Pilot Safety & Provisional Accounting Mappings (Kamal Express)
- **NEVER** deploy to Kamal Express production/pilot environments or expose live customer/financial data during core or integration development.
- Treat TravelOS ticket-sale revenue recognition (gross vs net agency commission) and supplier settlement entries as **provisional** until explicitly validated and signed off by Mr. Ali Muzaffar.
