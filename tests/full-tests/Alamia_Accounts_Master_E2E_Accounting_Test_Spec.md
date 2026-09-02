# Alamia Accounts — Master E2E Accounting Test Specification

**Document type:** QA / Accounting Integrity / E2E Certification  
**System:** Alamia Accounts  
**Scope:** Financial accounting only; inventory is explicitly out of scope for this suite  
**Execution:** Browser/UI automation by a development/QA agent  
**Primary objective:** Prove that a fresh company can be configured, its COA can be created and used, vouchers can be posted correctly, account/group ledgers update correctly, standard and custom reports reconcile, and the complete accounting equation remains intact.

---

## 1. Purpose

This specification is the master acceptance and regression suite for the accounting engine.

The agent MUST test the system from a **fresh company** rather than relying on pre-existing test data.

The suite covers:

1. Company creation and configuration
2. Automatic/base COA generation
3. COA hierarchy and account creation
4. Opening balances
5. Voucher types and numbering
6. Receipt, payment, contra and journal vouchers
7. Sales/revenue and purchase/expense transactions
8. Receivables and payables
9. Account and group ledger behavior
10. Cash and bank books
11. Trial Balance
12. General Ledger
13. Profit & Loss
14. Balance Sheet
15. Custom Receivables and Payables reports
16. Voucher/day-book reporting
17. Reversals, cancellation and editing
18. Date and fiscal-period behavior
19. Validation and negative cases
20. Audit/data integrity
21. Cross-report reconciliation
22. Complete end-to-end accounting certification

---

# 2. Non-Negotiable Testing Rules

The agent MUST:

- Use normal browser/UI workflows for business operations.
- NOT modify accounting records directly in the database to make a test pass.
- NOT silently compensate for incorrect accounting behavior.
- Capture before/after balances for affected accounts.
- Inspect the generated voucher after posting.
- Verify debit and credit lines.
- Verify affected account ledgers.
- Verify relevant reports.
- Independently calculate expected results.
- Compare expected vs actual.
- Mark a test PASS only when the expected accounting result is achieved.
- Record every defect with sufficient evidence to reproduce it.

For financial calculations, use exact decimal arithmetic. Do not rely on floating-point approximations.

For every posted voucher:

`Total Debit == Total Credit`

For every account:

`Opening Balance + Debit Movements - Credit Movements = Closing Balance`

For the complete company:

`Total Assets == Total Liabilities + Equity`

---

# 3. Test Environment

Create a brand-new test company.

Suggested company:

- **Name:** Alamia Accounts E2E Test Company
- **Currency:** PKR
- **Fiscal year:** Use the application's supported/default fiscal year
- **Decimal precision:** Application default
- **Tax:** Disabled unless tax is already implemented and required by the application
- **Inventory:** Not configured/used
- **Company opening balances:** Zero initially

If the application requires a mandatory field not specified here, use a sensible deterministic value and record it.

---

# 4. Test Data / COA

The agent must first inspect the automatically generated base COA and identify the application's actual IDs/names for equivalent groups.

Do NOT assume internal IDs.

Required logical hierarchy:

```text
Assets
└── Current Assets
    ├── Cash & Cash Equivalents
    │   ├── Cash in Hand
    │   ├── HBL Bank
    │   └── UBL Bank
    └── Accounts Receivable
        ├── A Rehman
        └── XYZ Travels

Fixed Assets
└── Computer Equipment

Liabilities
└── Current Liabilities
    └── Accounts Payable
        ├── TST Co
        └── ABC Supplier

Equity
└── Capital A/C

Income
├── Sales A/C
└── Service Revenue

Expenses
├── Purchase A/C
├── Electricity Expense A/C
├── Rent Expense A/C
├── Internet Expense A/C
└── Depreciation Expense A/C

Contra/Accumulated account where supported:
Accumulated Depreciation
```

If the application's accounting model uses different standard names, map the logical role to the closest valid account and document the mapping.

---

# 5. Global Test Matrix

| Suite | IDs | Priority |
|---|---|---|
| Company & configuration | CO-001–CO-012 | Critical |
| Base COA | COA-001–COA-015 | Critical |
| COA hierarchy | HIER-001–HIER-015 | Critical |
| Opening balances | OB-001–OB-010 | Critical |
| Voucher setup | VT-001–VT-010 | High |
| Journal | JE-001–JE-015 | Critical |
| Receipts | REC-001–REC-012 | Critical |
| Payments | PAY-001–PAY-012 | Critical |
| Contra | CON-001–CON-010 | Critical |
| Sales/revenue | SAL-001–SAL-012 | Critical |
| Purchases/expenses | EXP-001–EXP-012 | Critical |
| Receivables | AR-001–AR-015 | Critical |
| Payables | AP-001–AP-015 | Critical |
| Ledger | LED-001–LED-020 | Critical |
| Group ledger | GLED-001–GLED-015 | Critical |
| Cash/bank | CB-001–CB-012 | Critical |
| Trial Balance | TB-001–TB-012 | Critical |
| General Ledger | GL-001–GL-015 | Critical |
| P&L | PL-001–PL-015 | Critical |
| Balance Sheet | BS-001–BS-015 | Critical |
| Receivables report | ARR-001–ARR-012 | Critical |
| Payables report | APR-001–APR-012 | Critical |
| Voucher/day book | VBR-001–VBR-010 | High |
| Lifecycle/reversal | LIFE-001–LIFE-015 | Critical |
| Date/period | DATE-001–DATE-012 | High |
| Negative validation | NEG-001–NEG-020 | Critical |
| Audit/data integrity | AUD-001–AUD-015 | Critical |
| Cross-report reconciliation | RECX-001–RECX-020 | Critical |
| Final E2E scenarios | E2E-001–E2E-010 | Critical |

---

# 6. Company Creation & Configuration

## CO-001 — Create company

**Input:** New company name and required configuration.

**Expected:**
- Company created successfully.
- Company opens without errors.
- Correct company identity displayed.

## CO-002 — Company isolation

Create a second company if supported.

**Expected:** Records from company A do not appear in company B.

## CO-003 — Currency

Set PKR.

**Expected:** Transactions/reports use PKR consistently.

## CO-004 — Fiscal year

Configure the supported fiscal year.

**Expected:** Reports and voucher dates respect the fiscal period.

## CO-005 — Decimal precision

Verify configured precision.

**Expected:** Monetary values are consistently rounded/displayed.

## CO-006 — Default accounting configuration

Inspect defaults.

**Expected:** Required accounting defaults are valid and usable.

## CO-007 — Base COA auto-generation

Immediately inspect COA.

**Expected:** Base groups/categories are generated automatically.

## CO-008 — Initial Trial Balance

Before transactions.

**Expected:** Trial Balance is balanced and reflects zero/opening state.

## CO-009 — Initial reports

Open P&L and Balance Sheet.

**Expected:** No unexplained balances.

## CO-010 — Voucher numbering

Inspect default numbering.

**Expected:** Valid sequence exists.

## CO-011 — Company persistence

Reload/logout/login if supported.

**Expected:** Configuration persists.

## CO-012 — Company deletion/isolation behavior

If deletion exists, verify appropriate safeguards.

**Expected:** No accidental deletion of accounting records.

---

# 7. Base COA Tests

## COA-001 — Base groups

Verify standard top-level groups.

**Expected:** Assets, Liabilities, Equity, Income and Expenses or equivalent accounting categories exist.

## COA-002 — Base group classification

Verify each group has the correct accounting nature.

## COA-003 — Create Cash account

**Input:** Cash in Hand.

**Expected:** Under appropriate cash/current asset group.

## COA-004 — Create HBL

**Expected:** Bank account under appropriate current asset group.

## COA-005 — Create UBL

Same expected behavior.

## COA-006 — Create customer account

A Rehman under Receivables.

## COA-007 — Create second customer

XYZ Travels.

## COA-008 — Create supplier

TST Co under Payables.

## COA-009 — Create second supplier

ABC Supplier.

## COA-010 — Create Sales

Sales A/C under Income/Revenue.

## COA-011 — Create Service Revenue

Correct income group.

## COA-012 — Create Purchase Expense

Purchase A/C under Expenses if inventory is not implemented.

## COA-013 — Create Electricity Expense

Correct expense group.

## COA-014 — Create Rent Expense

Correct expense group.

## COA-015 — Create Fixed Asset

Computer Equipment under Fixed Assets.

---

# 8. COA Hierarchy Tests

## HIER-001 — Child account placement

Cash in Hand appears beneath its intended parent.

## HIER-002 — Bank placement

HBL and UBL appear under the intended bank/cash grouping.

## HIER-003 — Receivable hierarchy

A Rehman and XYZ Travels appear under Receivables.

## HIER-004 — Payable hierarchy

TST Co and ABC Supplier appear under Payables.

## HIER-005 — Revenue hierarchy

Sales and Service Revenue appear under Income.

## HIER-006 — Expense hierarchy

Expense accounts appear beneath Expenses.

## HIER-007 — Account cannot have invalid parent

Attempt invalid hierarchy if UI permits.

**Expected:** Rejected or correctly constrained.

## HIER-008 — Parent total

After transactions, parent total equals sum of descendants.

## HIER-009 — Multi-level parent total

Top-level group equals all descendant accounts.

## HIER-010 — Deactivated account

Historical entries remain available.

## HIER-011 — Account rename

Historical transactions remain attached to the account.

## HIER-012 — Duplicate account name

System handles duplicate names according to accounting rules/design.

## HIER-013 — Account selection

Selecting an account identifies only that account.

## HIER-014 — Group selection

Selecting a group includes all descendants.

## HIER-015 — Hierarchy persistence

Reload and verify hierarchy remains intact.

---

# 9. Opening Balances

If the product supports opening balances as a dedicated workflow, use it. Otherwise use the documented opening journal mechanism.

## OB-001

Opening Cash = 400,000 Dr.

## OB-002

Opening HBL = 600,000 Dr.

## OB-003

Opening Capital = 1,000,000 Cr.

Expected:

`Assets 1,000,000 = Equity 1,000,000`

## OB-004

Verify Trial Balance.

Expected: Dr 1,000,000 / Cr 1,000,000.

## OB-005

Opening AR = 0.

## OB-006

Opening AP = 0.

## OB-007

Opening balance account ledger.

Expected exact opening entry.

## OB-008

Opening balances in Balance Sheet.

Expected correct classification.

## OB-009

Opening balances in P&L.

Expected no artificial revenue/expense.

## OB-010

Opening balance editing/reversal.

Expected accounting remains balanced.

---

# 10. Voucher Type & Numbering

## VT-001

Verify Journal voucher type.

## VT-002

Verify Receipt voucher type.

## VT-003

Verify Payment voucher type.

## VT-004

Verify Contra voucher type.

## VT-005

Verify Sales transaction/voucher type if implemented.

## VT-006

Verify Purchase/Expense transaction/voucher type if implemented.

## VT-007

Sequential numbering.

**Expected:** No duplicate numbers.

## VT-008

Voucher date.

Expected correct accounting date.

## VT-009

Required fields.

Expected validation.

## VT-010

Voucher posting status.

Expected distinction between draft and posted if drafts exist.

---

# 11. Journal Vouchers

| ID | Input | Expected |
|---|---|---|
| JE-001 | Dr Rent 20,000 / Cr Cash 20,000 | Rent +20k, Cash -20k |
| JE-002 | Dr Electricity 5,000 / Cr HBL 5,000 | Electricity +5k, HBL -5k |
| JE-003 | Dr HBL 50,000 / Cr Cash 50,000 | HBL +50k, Cash -50k |
| JE-004 | Dr Cash 10,000 / Cr HBL 10,000 | Cash +10k, HBL -10k |
| JE-005 | Multi-line Dr expenses / Cr Cash | All lines correctly posted |
| JE-006 | Multi-line Dr asset / Cr bank | Asset and bank correct |
| JE-007 | Balanced transfer | Both accounts update |
| JE-008 | Unbalanced journal | Rejected |
| JE-009 | Zero amount | Rejected |
| JE-010 | Missing account | Rejected |
| JE-011 | Missing amount | Rejected |
| JE-012 | Invalid account | Rejected |
| JE-013 | Duplicate voucher number | Rejected/prevented |
| JE-014 | Posted voucher view | Lines match GL |
| JE-015 | Voucher total | Debit = Credit |

---

# 12. Receipt Vouchers

| ID | Input | Expected |
|---|---|---|
| REC-001 | Cash receipt 50k from income | Dr Cash 50k / Cr Income 50k |
| REC-002 | HBL receipt 75k | Dr HBL / Cr Income |
| REC-003 | A Rehman pays 150k cash/bank | Dr Cash/Bank / Cr A Rehman |
| REC-004 | A Rehman pays 50k | AR decreases 50k |
| REC-005 | XYZ pays 50k | XYZ AR decreases 50k |
| REC-006 | Full customer settlement | Customer balance zero |
| REC-007 | Partial settlement | Correct remaining balance |
| REC-008 | Multiple receipt lines | Correct allocation |
| REC-009 | Invalid customer/account | Rejected |
| REC-010 | Zero receipt | Rejected |
| REC-011 | Cancel receipt | Original impact reversed |
| REC-012 | Date-specific receipt | Correct reporting period |

---

# 13. Payment Vouchers

| ID | Input | Expected |
|---|---|---|
| PAY-001 | Cash rent 50k | Dr Rent / Cr Cash |
| PAY-002 | HBL electricity 20k | Dr Electricity / Cr HBL |
| PAY-003 | Pay TST Co 60k | Dr TST Co / Cr Cash/Bank |
| PAY-004 | Partial supplier payment | AP reduced |
| PAY-005 | Full supplier payment | Supplier balance zero |
| PAY-006 | Multiple expense lines | Correct allocations |
| PAY-007 | Bank payment | Bank decreases |
| PAY-008 | Cash payment | Cash decreases |
| PAY-009 | Invalid supplier | Rejected |
| PAY-010 | Zero payment | Rejected |
| PAY-011 | Cancel payment | Reversed |
| PAY-012 | Backdated payment | Correct period |

---

# 14. Contra Vouchers

| ID | Input | Expected |
|---|---|---|
| CON-001 | Cash → HBL 300k | Cash -300k, HBL +300k |
| CON-002 | HBL → Cash 50k | HBL -50k, Cash +50k |
| CON-003 | HBL → UBL 100k | HBL -100k, UBL +100k |
| CON-004 | UBL → HBL | Reverse correctly |
| CON-005 | Cash → Cash | Prevent/handle invalid transfer |
| CON-006 | Expense account in contra | Prevent invalid account |
| CON-007 | Zero transfer | Reject |
| CON-008 | Contra voucher totals | Dr = Cr |
| CON-009 | Contra report | Appears correctly |
| CON-010 | Cancellation | Source/destination restored |

---

# 15. Sales / Revenue

No inventory is involved.

| ID | Input | Expected |
|---|---|---|
| SAL-001 | Cash sale 100k | Dr Cash / Cr Sales |
| SAL-002 | HBL sale 100k | Dr HBL / Cr Sales |
| SAL-003 | Credit sale to A Rehman 300k | Dr A Rehman / Cr Sales |
| SAL-004 | Credit sale to XYZ 200k | Dr XYZ / Cr Sales |
| SAL-005 | Service Revenue 50k cash | Dr Cash / Cr Service Revenue |
| SAL-006 | Multiple income lines | Correct classification |
| SAL-007 | Partial customer receipt | AR decreases |
| SAL-008 | Full customer settlement | AR cleared |
| SAL-009 | Multiple customers | Separate balances |
| SAL-010 | Sale cancellation | Revenue and AR/cash reversed |
| SAL-011 | Sale date filter | Correct period |
| SAL-012 | Sales report/ledger | Matches GL |

---

# 16. Purchases / Expenses

Inventory is NOT used.

| ID | Input | Expected |
|---|---|---|
| EXP-001 | Cash purchase/expense 30k | Dr Purchase / Cr Cash |
| EXP-002 | Electricity 20k via HBL | Dr Electricity / Cr HBL |
| EXP-003 | Rent 50k cash | Dr Rent / Cr Cash |
| EXP-004 | Credit expense to TST 100k | Dr Expense / Cr TST |
| EXP-005 | Pay TST 60k | AP decreases |
| EXP-006 | Partial supplier settlement | Correct outstanding |
| EXP-007 | Full supplier settlement | Zero outstanding |
| EXP-008 | Multiple expense categories | Correct grouping |
| EXP-009 | Multiple suppliers | Correct supplier balances |
| EXP-010 | Expense cancellation | Reversed |
| EXP-011 | Date filtering | Correct period |
| EXP-012 | Expense report/ledger | Matches GL |

---

# 17. Receivables

Required custom report if not currently implemented.

## AR-001

Create credit sale to A Rehman for 300,000.

Expected:

`A Rehman AR = 300,000`

## AR-002

Receive 150,000.

Expected:

`A Rehman AR = 150,000`

## AR-003

Create XYZ credit sale 200,000.

Expected:

`XYZ AR = 200,000`

## AR-004

Receive 50,000 from XYZ.

Expected:

`XYZ AR = 150,000`

## AR-005

Receivables total.

Expected:

`150,000 + 150,000 = 300,000`

## AR-006

Customer ledger vs AR report.

Expected exact match.

## AR-007

AR report vs AR control/group ledger.

Expected exact match.

## AR-008

Date filtering.

Expected only qualifying transactions.

## AR-009

Customer detail.

Expected all relevant vouchers.

## AR-010

Customer outstanding.

Expected correct running balance.

## AR-011

Reversal.

Expected outstanding restored.

## AR-012

Zero-balance customer.

Expected displayed according to report design.

## AR-013

Multiple customers.

Expected no cross-contamination.

## AR-014

Total AR.

Expected sum of customer balances.

## AR-015

AR vs Balance Sheet.

Expected AR asset equals Balance Sheet receivable amount.

---

# 18. Payables

Required custom report if not currently implemented.

## AP-001

Create 100,000 credit expense with TST Co.

Expected:

`TST Co payable = 100,000`

## AP-002

Pay 60,000.

Expected:

`TST Co payable = 40,000`

## AP-003

Create ABC Supplier payable 50,000.

Expected:

`ABC Supplier payable = 50,000`

## AP-004

Pay 20,000.

Expected:

`ABC Supplier payable = 30,000`

## AP-005

Total AP.

Expected:

`40,000 + 30,000 = 70,000`

## AP-006

Supplier ledger vs AP report.

Expected exact match.

## AP-007

AP report vs AP control/group ledger.

Expected exact match.

## AP-008

Date filtering.

Expected correct period.

## AP-009

Supplier detail.

Expected all relevant vouchers.

## AP-010

Partial settlement.

Expected correct balance.

## AP-011

Full settlement.

Expected zero balance.

## AP-012

Reversal.

Expected balance restored.

## AP-013

Multiple suppliers.

Expected isolation.

## AP-014

Total AP.

Expected sum of supplier balances.

## AP-015

AP vs Balance Sheet.

Expected liability equals payable report.

---

# 19. Account Ledger

## LED-001 — Individual account

Select Cash in Hand.

**Expected:** Only Cash in Hand's transactions.

## LED-002 — HBL

Only HBL transactions.

## LED-003 — A Rehman

Only A Rehman transactions.

## LED-004 — TST Co

Only TST Co transactions.

## LED-005 — Sales

Only Sales postings.

## LED-006 — Electricity

Only Electricity postings.

## LED-007 — Running balance

Every row's balance is mathematically correct.

## LED-008 — Opening balance

Correct starting point.

## LED-009 — Date range

Only transactions within range.

## LED-010 — Voucher drill-down

Each ledger entry opens the correct voucher.

## LED-011 — Debit total

Ledger debit total equals underlying entries.

## LED-012 — Credit total

Ledger credit total equals underlying entries.

## LED-013 — Closing balance

Ledger closing balance equals account balance.

## LED-014 — Empty account

Correct empty/zero state.

## LED-015 — Reversed voucher

Reversal appears correctly.

## LED-016 — Cancelled voucher

Behavior matches product's accounting policy.

## LED-017 — Multiple transaction types

All relevant voucher types appear.

## LED-018 — Account search

Correct account selected.

## LED-019 — Account identity

No unrelated account transactions.

## LED-020 — Ledger refresh

After posting, ledger updates correctly.

---

# 20. Parent / Group Ledger

This is a critical product requirement.

## GLED-001 — Select Current Assets

Expected all descendant transactions.

## GLED-002 — Select Cash & Cash Equivalents

Expected Cash, HBL and UBL transactions.

## GLED-003 — Select Accounts Receivable

Expected A Rehman + XYZ transactions.

## GLED-004 — Select Payables

Expected TST Co + ABC Supplier transactions.

## GLED-005 — Select Assets

Expected all asset descendants.

## GLED-006 — Select Expenses

Expected all expense descendants.

## GLED-007 — Select Income

Expected all income descendants.

## GLED-008 — Summary mode

Show group/subgroup totals without individual transaction detail.

Example:

```text
Current Assets
  Cash & Cash Equivalents    500,000
  Accounts Receivable        300,000
```

## GLED-009 — Detailed mode

Show descendant accounts and their transactions.

## GLED-010 — Parent total

Parent total equals sum of all descendant balances.

## GLED-011 — Nested parent total

Each subgroup total equals its children.

## GLED-012 — Date filtering

Group results respect date range.

## GLED-013 — Voucher filtering

Group results respect voucher filtering if supported.

## GLED-014 — Expand/collapse

UI hierarchy behaves correctly.

## GLED-015 — No duplicate aggregation

Parent totals do not double-count child/subgroup totals.

---

# 21. Cash & Bank Books

## CB-001

Cash receipts increase cash.

## CB-002

Cash payments decrease cash.

## CB-003

Cash transfers decrease/increase appropriately.

## CB-004

HBL receipts increase HBL.

## CB-005

HBL payments decrease HBL.

## CB-006

HBL transfers behave correctly.

## CB-007

Cash Book = Cash Ledger.

## CB-008

Bank Book = Bank Ledger.

## CB-009

Cash Book closing balance = Cash account balance.

## CB-010

Bank Book closing balance = Bank account balance.

## CB-011

Date filtering.

## CB-012

Voucher drill-down.

---

# 22. Trial Balance

## TB-001

Debit total equals credit total.

## TB-002

Opening balances included correctly.

## TB-003

All posting accounts included.

## TB-004

No draft/unposted voucher included unless product explicitly defines otherwise.

## TB-005

Cash balance correct.

## TB-006

Bank balances correct.

## TB-007

AR balance correct.

## TB-008

AP balance correct.

## TB-009

Income balances correctly classified.

## TB-010

Expense balances correctly classified.

## TB-011

Date filter works.

## TB-012

Trial Balance reconciles to General Ledger.

---

# 23. General Ledger

## GL-001

All posted transactions appear.

## GL-002

No unrelated transactions appear for an account.

## GL-003

Voucher reference is correct.

## GL-004

Voucher date is correct.

## GL-005

Debit/credit direction is correct.

## GL-006

Running balances are correct.

## GL-007

Account totals are correct.

## GL-008

Date filter works.

## GL-009

Account filter works.

## GL-010

Voucher type filter works if available.

## GL-011

Drill-down opens originating voucher.

## GL-012

Reversal entries appear correctly.

## GL-013

Cancelled voucher treatment is correct.

## GL-014

GL total reconciles with Trial Balance.

## GL-015

GL total reconciles with account balances.

---

# 24. Profit & Loss

Use controlled transactions.

Revenue:

- Sales = 100,000
- Service Revenue = 50,000

Expenses:

- Purchase/Expense = 30,000
- Electricity = 20,000
- Rent = 10,000

Expected:

`Total Revenue = 150,000`

`Total Expenses = 60,000`

`Net Profit = 90,000`

## PL-001

Revenue total = 150,000.

## PL-002

Expense total = 60,000.

## PL-003

Net profit = 90,000.

## PL-004

Add revenue 10,000.

Expected profit = 100,000.

## PL-005

Add expense 5,000.

Expected profit = 95,000.

## PL-006

Reverse revenue.

Expected profit decreases accordingly.

## PL-007

Reverse expense.

Expected profit increases accordingly.

## PL-008

Income classification.

## PL-009

Expense classification.

## PL-010

Date filter.

## PL-011

Current-period filter.

## PL-012

Prior-period filter.

## PL-013

No asset/liability accounts incorrectly included as income/expense.

## PL-014

P&L profit equals calculated revenue minus expenses.

## PL-015

P&L profit reconciles with Balance Sheet equity/profit treatment.

---

# 25. Balance Sheet

Construct a controlled scenario and calculate independently.

Required checks:

- Cash
- Bank
- Receivables
- Fixed assets
- Payables
- Capital
- Current-period profit

## BS-001

Assets correctly classified.

## BS-002

Liabilities correctly classified.

## BS-003

Equity correctly classified.

## BS-004

Receivables included as assets.

## BS-005

Payables included as liabilities.

## BS-006

Cash correct.

## BS-007

Bank correct.

## BS-008

Fixed assets correct.

## BS-009

Depreciation reduces carrying value appropriately.

## BS-010

Capital correct.

## BS-011

Profit/equity treatment correct.

## BS-012

Total Assets calculated correctly.

## BS-013

Total Liabilities + Equity calculated correctly.

## BS-014

Accounting equation:

`Assets == Liabilities + Equity`

## BS-015

Date/as-of filter works.

---

# 26. Voucher Register / Day Book

## VBR-001

All posted vouchers appear.

## VBR-002

Correct voucher numbers.

## VBR-003

Correct dates.

## VBR-004

Correct voucher types.

## VBR-005

Correct totals.

## VBR-006

Date filtering.

## VBR-007

Voucher drill-down.

## VBR-008

Cancelled/reversed status.

## VBR-009

Draft handling.

## VBR-010

Voucher register totals reconcile with underlying vouchers.

---

# 27. Voucher Lifecycle

## LIFE-001 — Draft

Create draft if supported.

Expected: no GL impact until posting.

## LIFE-002 — Post

Post voucher.

Expected: GL impact occurs exactly once.

## LIFE-003 — Reload after posting

Expected: accounting impact persists.

## LIFE-004 — Edit draft

Expected: editable.

## LIFE-005 — Edit posted voucher

Expected behavior matches accounting policy; posted records must not silently corrupt history.

## LIFE-006 — Cancel voucher

Expected controlled reversal/removal according to design.

## LIFE-007 — Cancelled voucher auditability

Original voucher remains traceable.

## LIFE-008 — Reverse voucher

Expected exact opposite accounting effect.

## LIFE-009 — Double reversal

Prevent duplicate reversal.

## LIFE-010 — Duplicate posting

Cannot create duplicate GL impact.

## LIFE-011 — Voucher number uniqueness

No duplicates.

## LIFE-012 — Voucher reference

Ledger references correct voucher.

## LIFE-013 — Reversal report

Original/reversal relationship visible if supported.

## LIFE-014 — Balance after reversal

All reports reconcile.

## LIFE-015 — Audit trail

Lifecycle actions are attributable.

---

# 28. Date & Fiscal Period Tests

## DATE-001

Transaction on fiscal-year start.

## DATE-002

Transaction on fiscal-year end.

## DATE-003

Transaction outside period.

Expected validation/handling according to configuration.

## DATE-004

Date filter exact start date.

## DATE-005

Date filter exact end date.

## DATE-006

Month filter.

## DATE-007

Year filter.

## DATE-008

Backdated transaction.

Expected reports update correctly.

## DATE-009

Future-dated transaction.

Expected behavior according to system policy.

## DATE-010

Opening balance date.

## DATE-011

Period close if implemented.

Expected closed period cannot be altered improperly.

## DATE-012

Comparative date filtering.

Expected period totals remain mathematically consistent.

---

# 29. Negative / Validation Tests

## NEG-001

Unbalanced journal.

Expected rejection.

## NEG-002

Debit-only journal.

Expected rejection.

## NEG-003

Credit-only journal.

Expected rejection unless voucher type explicitly supports it.

## NEG-004

Zero amount.

Expected rejection.

## NEG-005

Negative amount.

Expected rejection or explicitly supported normalization.

## NEG-006

Missing account.

Expected validation.

## NEG-007

Missing date.

Expected validation.

## NEG-008

Missing voucher type.

Expected validation.

## NEG-009

Duplicate voucher number.

Expected prevention.

## NEG-010

Inactive account.

Expected prevention.

## NEG-011

Invalid parent account.

Expected prevention.

## NEG-012

Invalid customer.

Expected prevention.

## NEG-013

Invalid supplier.

Expected prevention.

## NEG-014

Malformed amount.

Expected validation.

## NEG-015

Excessive decimal precision.

Expected rounding/rejection according to configuration.

## NEG-016

Deleting referenced account.

Expected prevention or safe archival.

## NEG-017

Posting duplicate transaction.

Expected prevention.

## NEG-018

Posting after cancellation.

Expected controlled behavior.

## NEG-019

Unauthorized accounting operation.

Expected permission denial where RBAC exists.

## NEG-020

Browser refresh during submission.

Expected no duplicate voucher/GL posting.

---

# 30. Audit / Data Integrity

## AUD-001

Every posted voucher has ledger entries.

## AUD-002

Every ledger entry references a valid account.

## AUD-003

Every ledger entry references a valid voucher where required.

## AUD-004

No orphan ledger entries.

## AUD-005

No orphan vouchers.

## AUD-006

Posted voucher total equals GL posting total.

## AUD-007

Debit total equals credit total globally.

## AUD-008

Account balance equals ledger-derived balance.

## AUD-009

Parent balance equals descendant balances.

## AUD-010

Report totals equal source ledger totals.

## AUD-011

Cancelled/reversed entries retain appropriate history.

## AUD-012

User attribution exists where supported.

## AUD-013

Timestamp exists where supported.

## AUD-014

No duplicate posting after retry/refresh.

## AUD-015

Company data remains isolated.

---

# 31. Cross-Report Reconciliation

These are **certification tests**, not optional reporting checks.

## RECX-001

Cash Book = Cash Account Ledger.

## RECX-002

Bank Book = Bank Account Ledger.

## RECX-003

Customer Ledger total = Receivables Report total.

## RECX-004

Receivables Report = Balance Sheet AR.

## RECX-005

Supplier Ledger total = Payables Report total.

## RECX-006

Payables Report = Balance Sheet AP.

## RECX-007

General Ledger = Trial Balance.

## RECX-008

Trial Balance debit total = credit total.

## RECX-009

Revenue ledger total = P&L revenue.

## RECX-010

Expense ledger total = P&L expense.

## RECX-011

P&L net profit = calculated revenue - expenses.

## RECX-012

P&L profit = Balance Sheet current-period profit/equity treatment.

## RECX-013

Parent group = sum of descendants.

## RECX-014

Subgroup = sum of child accounts.

## RECX-015

Voucher total = GL total.

## RECX-016

Opening balance + movements = closing balance.

## RECX-017

Balance Sheet:

`Assets = Liabilities + Equity`

## RECX-018

Report date filter = source ledger date filter.

## RECX-019

Reversal = original accounting effect reversed.

## RECX-020

Final company accounting state contains no unexplained variance.

---

# 32. Master E2E Scenario

The agent must execute this scenario from the beginning on a fresh company.

## E2E-001 — Company

Create and configure:

```text
Company: Alamia Accounts E2E Test Company
Currency: PKR
Fiscal Year: Application-supported current test year
```

Verify base COA.

---

## E2E-002 — COA

Create/map:

```text
Cash in Hand
HBL Bank
UBL Bank

A Rehman
XYZ Travels

TST Co
ABC Supplier

Sales A/C
Service Revenue

Purchase A/C
Electricity Expense A/C
Rent Expense A/C
Internet Expense A/C

Computer Equipment
Capital A/C
Accumulated Depreciation
```

Verify hierarchy.

---

## E2E-003 — Opening Capital

Post:

```text
Dr Cash in Hand       400,000
Dr HBL Bank           600,000
    Cr Capital A/C            1,000,000
```

Expected:

```text
Cash = 400,000
HBL = 600,000
Capital = 1,000,000
TB = balanced
```

---

## E2E-004 — Asset purchase

Buy computer:

```text
Dr Computer Equipment    100,000
    Cr HBL Bank                    100,000
```

Expected:

```text
Computer Equipment = 100,000
HBL = 500,000
```

---

## E2E-005 — Expenses

Pay:

```text
Dr Rent                 50,000
    Cr Cash                      50,000

Dr Electricity          20,000
    Cr HBL                       20,000
```

Expected:

```text
Cash = 350,000
HBL = 480,000
Rent Expense = 50,000
Electricity = 20,000
```

---

## E2E-006 — Credit sales

```text
Dr A Rehman             300,000
    Cr Sales A/C                 300,000

Dr XYZ Travels          200,000
    Cr Sales A/C                 200,000
```

Expected:

```text
A Rehman AR = 300,000
XYZ AR = 200,000
Total AR = 500,000
Sales = 500,000
```

---

## E2E-007 — Customer receipts

```text
Dr HBL                 150,000
    Cr A Rehman                  150,000

Dr HBL                  50,000
    Cr XYZ Travels                50,000
```

Expected:

```text
A Rehman = 150,000
XYZ = 150,000
Total AR = 300,000
HBL = 680,000
```

---

## E2E-008 — Credit expense

```text
Dr Purchase A/C         100,000
    Cr TST Co                     100,000
```

Then:

```text
Dr TST Co                60,000
    Cr HBL                         60,000
```

Expected:

```text
TST Co payable = 40,000
Total AP = 40,000
Purchase Expense = 100,000
HBL = 620,000
```

---

## E2E-009 — Additional transactions

Execute:

```text
Dr UBL                  100,000
    Cr HBL                        100,000

Dr Internet Expense      10,000
    Cr Cash                        10,000

Dr Depreciation Expense   10,000
    Cr Accumulated Depreciation   10,000
```

Expected:

```text
UBL = 100,000
HBL = 520,000
Cash = 340,000
Internet = 10,000
Accumulated Depreciation = 10,000
```

---

## E2E-010 — Final certification

The agent must now inspect:

- All account ledgers
- Group ledgers
- Trial Balance
- General Ledger
- Cash Book
- Bank Book
- Receivables
- Payables
- P&L
- Balance Sheet
- Voucher Register/Day Book

Then independently reconcile all totals.

No test suite passes unless all critical reconciliations pass.

---

# 33. Expected Final Accounting State

For the controlled E2E scenario above:

### Assets

Cash:

`400,000 - 50,000 - 10,000 = 340,000`

HBL:

`600,000 - 100,000 - 20,000 + 150,000 + 50,000 - 60,000 - 100,000 = 520,000`

UBL:

`100,000`

Receivables:

`300,000 + 200,000 - 150,000 - 50,000 = 300,000`

Computer Equipment:

`100,000`

Accumulated Depreciation:

`10,000`

Net fixed asset:

`90,000`

Total Assets:

`340,000 + 520,000 + 100,000 + 300,000 + 90,000 = 1,350,000`

### Liabilities

TST Co:

`100,000 - 60,000 = 40,000`

Total Liabilities:

`40,000`

### Equity

Capital:

`1,000,000`

Current-period profit:

Revenue:

`500,000`

Expenses:

```text
Rent                    50,000
Electricity             20,000
Purchase                100,000
Internet                10,000
Depreciation             10,000
--------------------------------
Total Expenses          190,000
```

Profit:

`500,000 - 190,000 = 310,000`

Equity:

`1,000,000 + 310,000 = 1,310,000`

Therefore:

`Liabilities + Equity = 40,000 + 1,310,000 = 1,350,000`

### Certification equation

```text
TOTAL ASSETS              1,350,000
TOTAL LIABILITIES            40,000
TOTAL EQUITY              1,310,000
------------------------------------
L + E                     1,350,000

RESULT: BALANCED
VARIANCE: 0
```

If the application's treatment of purchases, depreciation, retained/current profit, or accumulated depreciation differs because of its accounting model, the agent must document the model and independently recalculate the expected figures accordingly rather than force these exact numbers.

---

# 34. Required Test Result Format

For every test:

```text
TEST ID:
TEST NAME:
STATUS: PASS / FAIL / BLOCKED / NOT APPLICABLE

PRECONDITION:
INPUT:
UI ACTIONS:

EXPECTED:
- Voucher:
- Debit:
- Credit:
- Account balances:
- Ledger:
- Reports:

ACTUAL:
- Voucher:
- Debit:
- Credit:
- Account balances:
- Ledger:
- Reports:

VARIANCE:
0 / amount / description

SEVERITY:
CRITICAL / HIGH / MEDIUM / LOW

EVIDENCE:
- Screenshot(s)
- Voucher number
- Relevant report/filter
- Error message

DEFECT:
Description if failed
```

---

# 35. Severity Rules

### CRITICAL

Any issue that can produce incorrect financial statements or corrupt double-entry accounting.

Examples:

- Debit != Credit
- Wrong account posted
- Wrong debit/credit direction
- Trial Balance doesn't balance
- Balance Sheet doesn't balance
- Incorrect P&L
- AR doesn't reconcile
- AP doesn't reconcile
- Duplicate posting
- Missing GL entry
- Incorrect reversal
- Parent totals double-count or omit transactions

### HIGH

Major accounting/reporting functionality incorrect but not necessarily corrupting the entire accounting engine.

Examples:

- Incorrect report filtering
- Incorrect customer/supplier totals
- Incorrect group ledger
- Voucher drill-down mismatch
- Incorrect date handling

### MEDIUM

Important usability/reporting defects with correct underlying accounting.

### LOW

Cosmetic/non-functional issues.

---

# 36. Final Certification Criteria

The agent MUST NOT declare the system accounting-ready merely because transactions can be entered.

The system passes only if:

```text
Company creation                 PASS
Base COA                         PASS
COA hierarchy                   PASS
Account creation                PASS
Opening balances                PASS
Voucher posting                 PASS
Double-entry integrity           PASS
Account ledgers                 PASS
Parent/group ledgers             PASS
Cash/Bank books                  PASS
Receivables                      PASS
Payables                         PASS
Trial Balance                    PASS
General Ledger                   PASS
Profit & Loss                    PASS
Balance Sheet                    PASS
Voucher Register                 PASS
Reversals/Cancellations          PASS
Date/Fiscal handling             PASS
Validation                       PASS
Audit/Data integrity             PASS
Cross-report reconciliation      PASS
Final accounting equation        PASS
```

And:

```text
CRITICAL FAILURES = 0
UNEXPLAINED VARIANCE = 0
TRIAL BALANCE VARIANCE = 0
BALANCE SHEET VARIANCE = 0
AR RECONCILIATION VARIANCE = 0
AP RECONCILIATION VARIANCE = 0
```

---

# 37. Final Agent Report

At completion produce:

```text
ALAMIA ACCOUNTS
ACCOUNTING E2E CERTIFICATION

Tests:
  Total:
  Passed:
  Failed:
  Blocked:
  N/A:

Severity:
  Critical:
  High:
  Medium:
  Low:

Reconciliation:
  Trial Balance: PASS/FAIL
  General Ledger: PASS/FAIL
  Cash Book: PASS/FAIL
  Bank Book: PASS/FAIL
  Receivables: PASS/FAIL
  Payables: PASS/FAIL
  P&L: PASS/FAIL
  Balance Sheet: PASS/FAIL
  Group Ledgers: PASS/FAIL
  Voucher/GL Reconciliation: PASS/FAIL

Accounting Equation:
  Assets:
  Liabilities:
  Equity:
  Variance:

FINAL STATUS:
  CERTIFIED
  CERTIFIED WITH NON-CRITICAL DEFECTS
  NOT CERTIFIED
```

For every failure include the exact test ID and reproduction steps.

**Never hide, skip, overwrite, or manually correct a failed accounting result.**
