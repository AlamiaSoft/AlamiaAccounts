# Alamia Accounts — Accountant Production Readiness & Final Certification

You have completed the current Accounting Master E2E Certification with 21/21 PASS and zero reconciliation variance.

We now have a management-readiness question:

> **"Can we hand Alamia Accounts to our accountant and let them set up a real company and maintain its accounts without encountering accounting, integrity, or operational problems?"**

The current answer is **NO — not yet**.

Your task is to take the existing system from its current certified state to a state where we can responsibly answer:

> **YES — an accountant can use Alamia Accounts for normal day-to-day accounting operations.**

This is a **production-readiness hardening sprint**.

Do NOT merely make tests pass.  
Do NOT weaken assertions.  
Do NOT hide limitations.  
Do NOT declare success based only on the existing E2E suite.

The target is **real accountant usability + accounting correctness + data integrity + operational safety**.

---

# 1. NON-NEGOTIABLE PRINCIPLES

## 1.1 Preserve the existing accounting engine

The existing 21/21 certification is our baseline.

Do not break existing:

- Double-entry accounting
- Chart of Accounts
- Multi-tenant isolation
- Voucher posting
- Cashbook
- Bankbook
- Daybook
- General Ledger
- Account ledgers
- Group ledgers
- Receivables
- Payables
- Trial Balance
- Profit & Loss
- Balance Sheet
- Voucher reversal
- Existing API behavior unless intentionally versioned/improved

Run the existing complete regression suite before and after changes.

---

## 1.2 Do not confuse mathematical correctness with production readiness

The existing tests prove important mathematical properties.

They do NOT automatically prove:

- safe opening-balance workflows
- safe period management
- safe correction workflows
- accountant usability
- auditability
- permission boundaries
- historical-data integrity
- accidental duplicate posting protection
- proper handling of edge cases
- real-world accounting workflows

You must investigate these independently.

---

# 2. FIRST: PERFORM A PRODUCTION-READINESS AUDIT

Before modifying code, inspect the entire existing accounting architecture.

Inspect at minimum:

- database schema/migrations
- accounting/domain models
- Abivia Ledger integration
- AccountService
- voucher services
- posting services
- journal/ledger services
- company/tenant handling
- COA provisioning
- opening balance implementation
- reversal implementation
- report generation
- authentication/authorization
- frontend voucher forms
- account forms
- company setup
- date handling
- audit/history functionality
- existing tests
- API routes/controllers
- validation
- transaction handling
- deletion/update behavior

Search the codebase for:

- TODO
- FIXME
- temporary
- mock
- development
- hardcoded account IDs
- hardcoded tenant/company IDs
- direct database writes to ledger tables
- direct journal manipulation
- destructive delete operations
- bypasses around validation
- silent exception handling
- swallowed errors
- unscoped queries
- missing tenant filters
- date-based posting without period validation

Produce:

`docs/production-readiness-audit.md`

The audit must contain:

1. Current capabilities
2. Accounting risks
3. Data-integrity risks
4. Security/tenant risks
5. Accountant UX risks
6. Missing controls
7. Recommended fixes
8. Severity:
   - BLOCKER
   - HIGH
   - MEDIUM
   - LOW
9. Whether each item must be fixed before accountant handover

Do not stop at the four gaps already identified.

Find additional gaps yourself.

---

# 3. BLOCKER #1 — OPENING BALANCES

This requires particular scrutiny.

The current implementation reportedly allows opening balances through account setup and automatically balances differences into Capital/Equity.

Do NOT assume that this is universally correct accounting.

An opening balance system must support a real existing business.

Example:

Assets:

- Cash
- Bank
- Receivables
- Inventory if supported
- Fixed Assets
- Accumulated Depreciation

Liabilities:

- Payables
- Loans
- Other liabilities

Equity:

- Capital
- Retained Earnings / accumulated results where applicable

The system must not blindly force an arbitrary difference into Capital.

### Implement a proper opening-balance workflow.

Prefer an explicit:

**Opening Balance Entry**

or equivalent accounting mechanism.

Requirements:

- opening balances must balance
- debit and credit totals must reconcile
- user must be able to see the balancing difference
- user must understand what account receives the balancing amount
- system must not silently invent accounting data
- opening balance date must be controlled
- opening balances must be auditable
- opening balance entries must be distinguishable from normal transactions
- duplicate opening balance initialization must be prevented
- opening balances must respect account normal balances
- opening balance posting must be atomic

If retained earnings/equity is required to balance an existing company's opening position, it must be an explicit accounting decision, not an invisible system side effect.

Add appropriate UI and validation.

Add automated tests for:

1. balanced opening balances
2. unbalanced input
3. assets + liabilities + equity
4. receivables/payables
5. accumulated depreciation
6. duplicate opening balance attempt
7. invalid account
8. wrong tenant
9. opening balance after transactions already exist
10. opening balance report visibility

---

# 4. BLOCKER #2 — ACCOUNTING PERIODS / PERIOD LOCK

Implement proper accounting-period controls.

At minimum support:

- fiscal year
- accounting periods/months
- open period
- closed period
- period close
- prevention of ordinary posting into closed periods
- controlled reopening by authorized user

Example:

If January 2026 is closed, an ordinary accountant must not be able to create a new transaction dated January 15, 2026.

The system should return a clear error such as:

> Accounting period 2026-01 is closed. Posting is not permitted.

### Requirements

Create appropriate domain/database model if necessary.

Support:

- fiscal year
- period start
- period end
- status
- closed_at
- closed_by
- reopened_at
- reopened_by
- reason for reopening

Permissions should distinguish:

- accountant
- manager/admin
- authorized period controller

An ordinary accountant must not be able to bypass a closed period by manipulating dates.

Test:

- posting to open period succeeds
- posting to closed period fails
- editing/reversal of closed-period voucher follows controlled policy
- reopening requires authorization
- reopening is audited
- tenant isolation applies
- boundary dates work correctly

Do not introduce a complex ERP monster.

Implement the smallest robust accounting-period mechanism appropriate for the existing system.

---

# 5. BLOCKER #3 — PROPER VOUCHER CORRECTION / REVERSAL

A posted accounting voucher must not simply be edited or deleted in a way that destroys accounting history.

Implement a clear lifecycle:

**Draft → Posted → Reversed**

Where appropriate:

**Posted → Reversed → Corrected replacement transaction**

Requirements:

- posted vouchers cannot be silently edited
- posted vouchers cannot be deleted destructively
- reversal references the original voucher
- original voucher remains visible
- reversal has its own audit identity
- original and reversal can be traced together
- reversal is atomic
- double reversal is prevented
- reversal reason is captured
- reversal date is captured
- user performing reversal is captured
- closed-period rules apply
- UI exposes the reversal action
- accountant can understand the status

If there is already a backend reversal API, expose the proper accountant-facing UI workflow.

Do not rely on accountants manually creating compensating entries as the primary correction mechanism.

Manual journal corrections may still be supported where appropriate, but the system should provide a controlled reversal workflow.

Add tests for:

- normal reversal
- double reversal
- reversal reason
- reversal visibility
- reversal linkage
- reversal in closed period
- unauthorized reversal
- tenant isolation
- original voucher immutability

---

# 6. BLOCKER #4 — POSTED TRANSACTION IMMUTABILITY

Audit all update/delete endpoints.

A posted voucher must have a controlled lifecycle.

Investigate whether any path can:

- edit posted debit amount
- edit posted credit amount
- change account
- change voucher date
- change company
- delete voucher
- delete journal lines
- modify ledger entries directly

Close all such loopholes.

If correction is needed:

**Reverse + Correct**

not:

**Edit historical ledger data**

Add explicit tests attempting unauthorized mutation.

---

# 7. BLOCKER #5 — TENANT / COMPANY ISOLATION

Perform a dedicated multi-tenant security audit.

Do not rely only on the existing successful E2E test.

Verify that Company A cannot:

- read Company B accounts
- read Company B vouchers
- create vouchers for Company B
- alter Company B accounts
- reverse Company B vouchers
- access Company B reports
- access Company B customers/vendors
- manipulate Company B opening balances
- access Company B period settings

Audit every relevant query and endpoint.

Never trust a client-supplied company identifier as the sole authorization mechanism.

Tenant/company context must come from authenticated context and authorization.

Add explicit cross-tenant negative tests.

---

# 8. BLOCKER #6 — ATOMIC TRANSACTION POSTING

Every accounting operation must be atomic.

For a transaction such as:

Sale:

Dr Customer
Cr Revenue

Either BOTH happen or NEITHER happens.

Likewise:

Receipt:

Dr Cash/Bank
Cr Customer

Purchase:

Dr Expense/Asset
Cr Vendor

Payment:

Dr Vendor
Cr Cash/Bank

Transfer:

Dr Destination Bank
Cr Source Bank

If any component fails:

**ROLL BACK EVERYTHING.**

Test deliberate failures halfway through posting and verify:

- no orphan journal
- no orphan journal lines
- no balance changes
- no partial voucher
- no partial audit record pretending the transaction succeeded

---

# 9. BLOCKER #7 — DUPLICATE POSTING PROTECTION

Investigate accidental double submission.

An accountant may:

- double-click Submit
- refresh
- lose connection and retry
- submit the same voucher twice

Implement safe duplicate protection where appropriate.

At minimum:

- unique voucher/reference rules where applicable
- server-side duplicate detection
- safe retry behavior
- frontend submit-state protection

Do not rely solely on frontend button disabling.

---

# 10. ACCOUNTING VALIDATION HARDENING

Audit all voucher types:

- Payment
- Receipt
- Journal
- Contra
- Sales
- Purchase

Verify:

- debit total = credit total
- at least one debit
- at least one credit
- valid accounts only
- leaf/postable accounts only
- inactive accounts rejected
- correct company
- valid date
- valid currency
- valid amount
- positive/non-zero amounts
- required fields
- appropriate account types
- proper customer/vendor association
- proper cash/bank semantics where applicable

Test edge cases:

- zero
- negative
- decimal
- extremely large amount
- duplicate lines
- same account on both sides
- inactive account
- group account
- wrong tenant
- malformed payload
- missing account
- invalid voucher type

---

# 11. CASH AND BANK CONTROLS

Verify accountant workflows for:

- cash receipts
- cash payments
- bank receipts
- bank payments
- bank-to-bank transfer
- cash-to-bank
- bank-to-cash

Make sure:

- cashbook is correct
- bankbook is correct
- contra transactions appear correctly
- account balances reconcile
- reports reflect the same transactions

Strengthen existing tests so they verify the **specific expected account and amount**, rather than merely searching page text for generic words.

---

# 12. RECEIVABLES / PAYABLES

Verify real-world AR/AP workflows.

Customer:

1. Credit sale
2. Partial receipt
3. Additional receipt
4. Outstanding balance
5. Full settlement
6. Overpayment behavior

Vendor:

1. Credit purchase
2. Partial payment
3. Additional payment
4. Outstanding balance
5. Full settlement
6. Overpayment behavior

Verify:

- customer balance
- vendor balance
- ageing/balance where supported
- ledger
- P&L
- balance sheet
- trial balance

All must reconcile.

---

# 13. FINANCIAL REPORT CERTIFICATION

Strengthen report tests.

Do not use broad:

`pageText.includes(...)`

as the primary accounting assertion.

Where practical, extract the actual report rows/values and compare them against expected values.

Certify:

### Trial Balance

Total Debit = Total Credit

### Profit & Loss

Revenue - Expenses = Net Profit/Loss

### Balance Sheet

Assets = Liabilities + Equity

### AR

Customer subledger = corresponding receivable control balance

### AP

Vendor subledger = corresponding payable control balance

### Cashbook

Cash transactions = cash account ledger

### Bankbook

Bank transactions = bank account ledger

### General Ledger

Journal lines = account ledger

Use exact numerical comparisons with tolerance only where decimal/currency handling genuinely requires it.

---

# 14. AUDIT TRAIL

A real accountant needs to know:

- who created a transaction
- who posted it
- when it was posted
- who reversed it
- when it was reversed
- why it was reversed
- original voucher
- reversal voucher

Implement or harden audit logging.

Audit records should not themselves be casually mutable/deletable.

At minimum capture:

- tenant/company
- actor
- action
- entity
- entity ID
- timestamp
- relevant reference
- before/after where appropriate
- reason where required

Do not store unnecessary sensitive data.

---

# 15. ACCOUNTANT UX REVIEW

Pretend you are an accountant who has never seen the codebase.

Start from a fresh company.

Ask:

> "Can I figure out what to do without a developer?"

Test:

1. Create company
2. Set fiscal year
3. Review COA
4. Create required accounts
5. Configure opening balances
6. Post cash transaction
7. Post bank transaction
8. Post sale
9. Receive customer payment
10. Post purchase
11. Pay vendor
12. Transfer between banks
13. Review ledgers
14. Review cashbook
15. Review bankbook
16. Review AR
17. Review AP
18. Review Trial Balance
19. Review P&L
20. Review Balance Sheet
21. Correct an erroneous voucher
22. Close period
23. Attempt invalid historical posting
24. Review audit trail

Identify:

- confusing labels
- missing confirmations
- ambiguous fields
- unsafe defaults
- unexplained errors
- workflows requiring developer intervention
- workflows where accounting terminology is misleading

Fix genuine blockers.

Do not redesign the entire UI unnecessarily.

---

# 16. ACCOUNTING SAFETY RULE

Do NOT implement convenience features that can silently create incorrect accounting.

Examples:

Bad:

> "If opening balances don't balance, automatically put difference into Capital."

Better:

> "Opening balances are out of balance by PKR X. Select/confirm the appropriate balancing treatment."

Bad:

> silently change a posted voucher

Better:

> controlled reversal

Bad:

> allow posting into closed historical period

Better:

> block and explain

Bad:

> delete accounting history

Better:

> preserve history and reverse/correct

---

# 17. TESTING REQUIREMENT

Create a new certification suite:

`tests/e2e/08-accountant-production-readiness.test.js`

This must simulate a real accountant using a **fresh company**.

Do not reuse the existing E2E_CERT data as the only scenario.

Create a clean test company.

The suite should cover:

### Company

- creation
- isolation
- currency
- fiscal period

### COA

- review
- create account
- parent/child validation
- postable/non-postable validation

### Opening

- opening balance workflow
- balanced opening
- invalid opening
- duplicate opening

### Transactions

- payment
- receipt
- journal
- contra
- sale
- purchase

### AR

- credit sale
- partial receipt
- full settlement

### AP

- credit purchase
- partial payment
- full settlement

### Reports

- TB
- P&L
- BS
- GL
- cashbook
- bankbook
- AR
- AP

### Correction

- reversal
- reversal UI
- immutable posted voucher
- replacement transaction

### Periods

- close period
- rejected historical posting
- authorized reopen

### Security

- cross-tenant access
- unauthorized actions

### Integrity

- failed transaction rollback
- duplicate submission
- invalid posting
- no orphan ledger records

### Audit

- transaction actor
- timestamp
- reversal history
- audit trail

---

# 18. TEST ORACLE

Keep the existing independent AccountingEngine as a test oracle if useful.

However:

**Do not allow the independent engine to merely reproduce the same production implementation.**

The purpose is independent verification.

Where possible, calculate expected accounting results independently.

Then reconcile:

**Expected accounting model**
        ↓
**API/domain ledger**
        ↓
**Rendered UI/report**

All three must agree.

Improve weak existing assertions.

---

# 19. REALISTIC ACCOUNTANT SCENARIO

Build at least one realistic business scenario.

Use a generic company, not hardcoded E2E_CERT.

Example:

Opening:

- Cash
- Bank
- Receivables
- Fixed assets
- Accumulated depreciation
- Payables
- Capital/equity

Then during the month:

- capital introduction
- asset purchase
- sales
- customer receipts
- supplier purchases
- supplier payments
- rent
- utilities
- bank transfer
- depreciation
- correction/reversal

At the end verify:

- Trial Balance
- P&L
- Balance Sheet
- AR
- AP
- Cash
- Bank

All must reconcile independently.

---

# 20. ERROR MESSAGES

Errors must be understandable to an accountant.

Bad:

`LedgerPostingException: invalid state`

Better:

> "This account is a group account and cannot receive transactions. Please select a posting account."

Bad:

`SQLSTATE...`

Better:

> "This voucher could not be posted because the accounting period is closed."

Never expose raw database/framework errors to normal users.

---

# 21. DO NOT OVERENGINEER

This is NOT a request to build a complete SAP/Oracle ERP.

Do not add:

- unnecessary microservices
- event bus infrastructure
- complicated workflow engines
- unnecessary abstractions
- speculative modules
- travel-specific accounting features yet
- REST integration platform yet

The immediate goal is:

> **A reliable accountant-facing accounting system.**

REST APIs and event-driven integration will come immediately afterward.

The accounting core must be trustworthy first.

---

# 22. PRODUCTION READINESS DEFINITION

At the end, we should be able to honestly answer:

### YES

An accountant can:

- create a company
- configure its accounting structure
- establish opening balances correctly
- maintain its Chart of Accounts
- record normal daily accounting transactions
- manage cash and banks
- manage receivables
- manage payables
- review ledgers
- produce financial statements
- correct mistakes safely
- operate within accounting periods
- rely on historical records remaining intact
- work without developer intervention for normal workflows

And the system must:

- maintain double-entry integrity
- maintain tenant isolation
- prevent destructive historical changes
- prevent invalid postings
- prevent closed-period violations
- preserve audit history
- recover safely from failed transactions
- prevent accidental duplicates
- reconcile reports correctly

---

# 23. FINAL CERTIFICATION GATE

Do NOT declare "production ready" merely because tests pass.

At completion produce:

`docs/ACCOUNTANT_READINESS_CERTIFICATION.md`

Include:

## Executive Verdict

One of:

- NOT READY
- READY FOR CONTROLLED PILOT
- ACCOUNTANT READY

The target is:

**ACCOUNTANT READY**

only if all blockers are resolved.

Include a table:

| Area | Status | Evidence | Remaining Risk |
|---|---|---|---|
| Company setup | | | |
| Tenant isolation | | | |
| COA | | | |
| Opening balances | | | |
| Voucher posting | | | |
| Cashbook | | | |
| Bankbook | | | |
| AR | | | |
| AP | | | |
| General Ledger | | | |
| Trial Balance | | | |
| P&L | | | |
| Balance Sheet | | | |
| Reversal | | | |
| Posted immutability | | | |
| Accounting periods | | | |
| Audit trail | | | |
| Duplicate protection | | | |
| Atomicity | | | |
| Error handling | | | |
| Accountant UX | | | |

Also include:

- all tests executed
- exact test results
- known limitations
- residual risks
- manual accountant UAT checklist
- recommendation for management

---

# 24. ACCOUNTANT UAT DOCUMENT

Create:

`docs/ACCOUNTANT_UAT_GUIDE.md`

It should be usable by a non-developer accountant.

Explain:

1. Create company
2. Configure COA
3. Enter opening balances
4. Post daily vouchers
5. Review ledgers
6. Review AR/AP
7. Review financial statements
8. Reverse/correct a transaction
9. Close a period
10. Review audit history

Include expected accounting behavior, but do not expose developer implementation details.

---

# 25. REGRESSION REQUIREMENT

The existing certification must remain green.

Run:

`node tests/e2e/run-all.js`

and the new accountant readiness suite.

Also run the project's relevant:

- unit tests
- integration tests
- frontend tests
- backend tests
- static/type checks if configured

Record exact results.

If an existing test breaks because behavior was intentionally corrected, update the test only after verifying that the new behavior is actually more correct.

Never weaken a test simply to restore green.

---

# 26. IMPLEMENTATION ORDER

Follow this order:

### Phase 1 — Audit

Inspect architecture and produce:

`docs/production-readiness-audit.md`

### Phase 2 — Accounting safety

Fix:

1. Opening balances
2. Period controls
3. Voucher reversal/correction
4. Posted immutability
5. Tenant isolation
6. Atomicity
7. Duplicate protection

### Phase 3 — Accounting correctness

Harden:

- voucher validation
- cash/bank
- AR/AP
- reports
- ledger reconciliation

### Phase 4 — Auditability

Implement/harden:

- audit trail
- actor tracking
- reversal history
- period history

### Phase 5 — Accountant UX

Perform the fresh-company accountant workflow and fix genuine usability blockers.

### Phase 6 — Certification

Create and execute:

`tests/e2e/08-accountant-production-readiness.test.js`

### Phase 7 — Final regression

Run all existing tests.

### Phase 8 — Certification report

Create:

`docs/ACCOUNTANT_READINESS_CERTIFICATION.md`

and:

`docs/ACCOUNTANT_UAT_GUIDE.md`

---

# 27. IMPORTANT: DO NOT START CODING BLINDLY

Before implementation:

1. inspect the current codebase
2. inspect the current test suite
3. inspect the current database model
4. inspect the existing accounting behavior
5. identify all blockers
6. produce the audit
7. produce an implementation plan

Then execute the plan.

Do not ask me to manually identify obvious gaps that you can discover from the repository.

If you find a significant accounting design ambiguity, document it and choose the safest accounting behavior consistent with double-entry principles and the existing architecture.

---

# FINAL SUCCESS CONDITION

The ultimate question is not:

> "Did the E2E tests pass?"

It is:

> **"Would we be comfortable putting a real accountant in front of a fresh company and telling them: this system is ready for your day-to-day accounting?"**

Your final output must provide evidence for that answer.

The desired final verdict is:

# ACCOUNTANT READY — YES

But only say YES if the evidence genuinely supports it.