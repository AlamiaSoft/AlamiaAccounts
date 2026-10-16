# Abivia Boundary Audit — 2026-10-16

## Scope and verified baseline

- Repository: `AlamiaSoft/AlamiaAccounts`
- Baseline commit: `53eb6d6f8a217f73bc7c80de651dd92f6e1371e6` (PR #53 merged to `main`)
- Governance: [Issue #18](https://github.com/AlamiaSoft/AlamiaAccounts/issues/18), [Issue #52](https://github.com/AlamiaSoft/AlamiaAccounts/issues/52), [Issue #54](https://github.com/AlamiaSoft/AlamiaAccounts/issues/54)
- Boundary enforcement: `scripts/abivia-boundary.js`, with fixtures in `scripts/test-abivia-boundary.js`.
- CI evidence at baseline: [run 38917757311](https://github.com/AlamiaSoft/AlamiaAccounts/actions/runs/38917757311) passed PHPUnit SQLite, PHPUnit PostgreSQL, and Composer/syntax/architecture checks.

This report is a source-level inventory of the scanner's explicitly approved production files and its approved adapter-test prefix. It is not a claim that the scanner was executed locally in this session; this environment cannot clone the repository, so runtime verification must come from the subsequent GitHub Actions run on this branch. The scanner is the authoritative executable check for forbidden references outside this inventory.

## Classification rules

- **Internal adapter/service — retain:** Abivia is used behind an Alamia-owned service that implements a public business capability. This is the intended boundary; consumers must call the Alamia service, not the underlying package.
- **Internal persistence mapping — retain:** an Alamia-owned Eloquent model maps an association or relationship to the ledger's underlying rows. It must not be used as a consumer-facing API.
- **Forensic adapter test script — retain narrowly:** scripts intentionally construct or inspect low-level ledger state for diagnostics/reconciliation and must not become the public-workflow test path.
- **No consumer-facing violation identified in this inventory:** every production reference below is in a file explicitly approved by the scanner. This means it is inside the current boundary, not that every low-level coupling is ideal or immutable.

## Approved production references

Line numbers are from the files on baseline `main`. The line references identify direct imports or fully qualified references detected by the scanner. The service purpose/classification is based on the file's public methods and direct use.

| File and direct reference lines | Purpose / access type | Classification and decision |
|---|---|---|
| `src/Services/AccountMappingResolver.php:5` — `LedgerAccount` model | Resolve and validate account mappings/postability in a domain | **Internal service; retain.** Account mapping is already exposed through Alamia's resolver. Consumers should not query the ledger model directly. |
| `src/Services/AccountService.php:5-10,128` — controller, messages, account/domain models, Breaker exception | Create/read/update account data, format chart of accounts, initialize standard accounts | **Internal service; retain.** This is the Alamia-owned account workflow façade and adapter implementation. Keep package-specific controller/message/error handling here. |
| `src/Services/AccountingDiagnosticService.php:5-6` — account/domain models | Read-only balance-sheet and ledger-integrity diagnostics, account classifications and reconciliation checks | **Internal diagnostic service; retain.** Read-only diagnostics need ledger reads; do not expose these model APIs to controllers/tests. Verify tenant scoping when changing diagnostic queries. |
| `src/Services/AccountingIntegrityGuard.php:5` — domain model | Resolve ledger domain while recording an accounting integrity checkpoint | **Internal service; retain.** Checkpoint contract remains Alamia-owned. |
| `src/Services/CompanyService.php:5-8` — domain/account models, domain controller and message | Create, initialize, list, switch, update and manage company/domain data and organizational structures | **Internal service; retain.** It is the existing Alamia-owned company API; preserve fail-closed domain resolution and tenant boundaries. |
| `src/Services/DomainContext.php:5` — domain model | Resolve and require the active domain for domain-scoped operations | **Internal tenant-context adapter; retain with heightened review.** This is security-sensitive. Never add ambient/default fallback; preserve explicit fail-closed semantics. |
| `src/Services/OpeningBalanceService.php:8-9` — domain/account models | Inspect and post opening balances | **Internal posting service; retain.** Public callers should use this service. Preserve approval, period, atomicity and audit controls. |
| `src/Services/ReportService.php:5-7` — account/domain/journal-entry models | Trial balance, P&L, balance sheet, ledger, receivable/payable and bank-book reports | **Internal read/report service; retain.** This is already the Alamia-owned report contract. Report queries must remain domain-scoped and reconciled. |
| `src/Services/SearchService.php:5-6` — account/domain models | Domain-scoped voucher, transaction, party, account and ledger search | **Internal read/search service; retain.** Consumers should use Alamia search methods/API only. Audit every new query for domain scoping. |
| `src/Services/VoucherService.php:5-10,99,225,306` — journal controller, entry/detail/entity messages, journal/domain/account models, Breaker exception | Create and retrieve vouchers, validate accounts, map ledger rows to Alamia voucher results, reverse posted vouchers | **Internal posting/read service; retain, highest review priority.** This is the core posting adapter. Keep double-entry validation, domain association, idempotency/atomicity and immutable correction workflows at the Alamia write boundary. Do not let consumer code call the controller/model directly. |

## Approved Alamia-owned persistence mappings

| File and direct reference lines | Purpose / access type | Classification and decision |
|---|---|---|
| `src/Models/AccountingAuditTrail.php:6` — `LedgerDomain` relationship | Resolve domain relationship for Alamia audit-trail records | **Internal persistence mapping; retain.** Do not use this model to perform ledger business operations. |
| `src/Models/AccountingPeriod.php:6` — `LedgerDomain` relationship | Relate fiscal-period records to a ledger domain | **Internal persistence mapping; retain.** Period policy stays in Alamia services. |
| `src/Models/DomainJournalEntry.php:6-7` — journal-entry/domain relationships | Maintain and query the Alamia domain-to-journal association | **Internal persistence mapping; retain.** Association methods are internal; public reads should pass through domain-scoped Alamia services. |
| `src/Models/DomainLedgerAccount.php:6-7` — account/domain relationships | Maintain and query domain-to-ledger-account association | **Internal persistence mapping; retain.** Keep account visibility and mapping fail-closed. |
| `src/Models/OpeningBalanceBatch.php:6` — domain relationship | Associate opening-balance batch state with its ledger domain | **Internal persistence mapping; retain.** Posting behavior stays in `OpeningBalanceService`. |
| `src/Models/PostingIdempotencyRecord.php:7-8` — journal-entry/domain relationships | Associate idempotency state with the resulting journal entry and domain | **Internal persistence mapping; retain.** Ensure relation use does not bypass the durable, company-scoped idempotency service. |
| `src/Models/UnpostedIntegrationEntry.php:6` — domain relationship | Associate failed/unposted integration entries with their domain | **Internal persistence mapping; retain.** Recovery operations remain in Alamia-owned workflows. |

## Approved adapter-test scripts

These are forensic scripts, not normal public-workflow tests. Their use of low-level models is acceptable only for constructing or examining diagnostic/reconciliation fixtures. They must not be used as a shortcut to execute a business operation that should be exercised through Alamia's public contract.

| File and direct reference lines | Purpose / access type | Classification and decision |
|---|---|---|
| `tests/Adapters/Abivia/ForensicDiagnosticFixturesScript.php:17-21` — domain, account, name, journal-entry and journal-detail models | Construct low-level diagnostic fixtures for forensic accounting analysis | **Narrow adapter-test exception; retain.** Keep isolated to explicit test-owned data; never run against production or real tenant data. |
| `tests/Adapters/Abivia/ForensicReconciliationScript.php:18-22` — domain, account, name, journal-entry and journal-detail models | Inspect/construct low-level reconciliation fixtures for forensic analysis | **Narrow adapter-test exception; retain.** Not a substitute for service-level reconciliation tests. |

## Findings and decision on new service/API work

1. **No forbidden consumer-facing callsite is identified by the existing boundary inventory:** the production direct references listed above are in the scanner's explicit file-level allowlist; the two test scripts are under the approved adapter-test prefix. The PR #53 architecture CI and fixtures enforce that other scanned application/test files cannot add direct references without failing.
2. **No new public service/API is justified by this inventory alone.** Alamia-owned service entry points already exist for account, company/domain context, opening balances, reports, search, diagnostics, integrity checkpoints and vouchers. Adding a second wrapper around these services would be speculative and would not improve encapsulation.
3. **Residual architecture debt:** the internal services themselves still couple to Abivia controller/message/model types. They are currently the approved integration boundary, but the allowlist is a file-level trust boundary, not proof that every line is safe. Future changes should extract a dedicated adapter only when a concrete benefit is identified (e.g. removing package-specific types from a service contract, isolating package version changes, or making a testable behavior boundary smaller). Such a refactor must be a separately scoped DAG task with contract tests; do not attempt a broad rewrite in this audit.
4. **Highest-risk areas for subsequent focused review:** `VoucherService` (posting/atomicity/idempotency), `DomainContext` (fail-closed tenancy), `CompanyService` (domain lifecycle), and `OpeningBalanceService` (initial posting). Existing P0 gates #4, #6 and #7 remain in force; this audit does not reorder or waive them.
5. **No allowlist expansion is proposed.** Existing allowlist entries are retained with the specific responsibilities documented above.

## Verification evidence to attach after CI

- `node scripts/test-abivia-boundary.js`
- `node scripts/verify-architecture.js`
- Focused tests for any code change (none proposed by this source inventory)
- GitHub Actions results for the branch/PR, including SQLite and PostgreSQL jobs

Do not mark this audit complete until the new branch's CI results are observed and recorded. The baseline run linked above is historical evidence for PR #53, not a substitute for validating this audit change.
