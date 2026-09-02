# Comprehensive Accounting Workflows Verification Guide

This document defines the end-to-end testing protocol to rigorously verify all financial workflows, double-entry mathematical invariants, and reporting accuracy in Alamia Accounts before production rollout.

---

## 1. Test Architecture & Objectives

| Objective | Verification Target | Expected Result |
| :--- | :--- | :--- |
| **Double-Entry Invariant** | Any posted transaction | `Sum(Debits) === Sum(Credits)` with 0 balance discrepancy |
| **Chart of Accounts Hierarchy** | Parent/Child category links | Leaf posting accounts roll up into category parents |
| **Books of Prime Entry** | Daybook & Cashbook | Accurate chronological order, running cash balance |
| **General Ledger Reconciliation** | Individual account statements | Opening Balance + Debits - Credits = Closing Balance |
| **Financial Statements** | Trial Balance, P&L, Balance Sheet | Trial Balance matches; P&L computes Net Profit; Balance Sheet satisfies `Assets = Liabilities + Equity + Retained Earnings` |
| **Multi-Company Isolation** | Company Switcher (`X-Company-Code`) | No transaction or account leakage between domains |

---

## 2. Step-by-Step Verification Workflows

### Test Workflow 1: Chart of Accounts Structure
- **Step 1.1**: Open [http://localhost:3000](http://localhost:3000) and log in (`admin@admin.com` / `password`).
- **Step 1.2**: Click **Chart of Accounts** in the sidebar.
- **Step 1.3**: Verify the 5 standard root accounting categories appear:
  - `1000 Assets` (Debit normal balance)
  - `2000 Liabilities` (Credit normal balance)
  - `3000 Equity` (Credit normal balance)
  - `4000 Expenses` (Debit normal balance)
  - `5000 Revenue` (Credit normal balance)
- **Step 1.4**: Add a new child account:
  - Click **Add Account**.
  - Code: `1130`
  - Name: `Meezan Bank - Main Operations`
  - Parent Account: `1000 Assets` (or `1100 Current Assets`)
  - Type: `Bank`
  - Debit Normal: Checked
- **Verification**: Ensure `1130` appears nested in Tree View, shows up in Search list, and has an initial balance of `Rs. 0.00`.

---

### Test Workflow 2: Capital Injection (Owner Equity)
- **Accounting Transaction**: Owner invests Rs. 500,000 cash into the company bank account.
  - **Debit**: `1120 Bank Accounts` (Asset increases) -> Rs. 500,000
  - **Credit**: `3000 Equity` (Equity increases) -> Rs. 500,000
- **Step 2.1**: Go to **Vouchers** -> **Receipt Voucher** (or **Journal Voucher**).
- **Step 2.2**: Fill voucher details:
  - Date: Today
  - Reference / Narration: `Initial Capital Injection by Owner`
  - Line 1: Account `1120 Bank Accounts`, Debit: `500000`, Credit: `0`
  - Line 2: Account `3000 Equity`, Debit: `0`, Credit: `50000` (Note: ensure debits equal credits: `500000`)
- **Step 2.3**: Click **Post / Save Voucher**.
- **Verification**: Voucher number is generated (e.g. `JV-2026-0001` or `RV-2026-0001`).

---

### Test Workflow 3: Cash & Credit Sales Workflows
- **Scenario 3A: Cash Sale**:
  - Customer buys goods/services for Rs. 75,000 cash.
  - **Debit**: `1110 Cash` -> Rs. 75,000
  - **Credit**: `5000 Revenue` -> Rs. 75,000
- **Scenario 3B: Credit Sale (Accounts Receivable)**:
  - Customer B is billed Rs. 120,000 on 30-day payment terms.
  - **Debit**: `1200 Accounts Receivable` -> Rs. 120,000
  - **Credit**: `5000 Revenue` -> Rs. 120,000
- **Verification**:
  - Check **Daybook**: Both transactions appear with correct line splits.
  - Check **Chart of Accounts**: `Cash` increased by 75,000; `Accounts Receivable` shows 120,000; `Revenue` shows 195,000.

---

### Test Workflow 4: Operating Expenses & Cash Flow
- **Accounting Transaction**: Office rent and utilities paid in cash.
  - **Debit**: `4000 Expenses` -> Rs. 35,000
  - **Credit**: `1110 Cash` -> Rs. 35,000
- **Step 4.1**: Go to **Payment Voucher**.
- **Step 4.2**: Debit `4000 Expenses` Rs. 35,000, Credit `1110 Cash` Rs. 35,000.
- **Verification**:
  - Check **Cashbook**: Shows receipt of 75,000, payment of 35,000, and net closing cash balance of `Rs. 40,000` (+ any previous opening balance).

---

### Test Workflow 5: Contra Voucher (Bank Deposit / Withdrawal)
- **Accounting Transaction**: Depositing Rs. 20,000 cash into the company bank account.
  - **Debit**: `1120 Bank Accounts` -> Rs. 20,000
  - **Credit**: `1110 Cash` -> Rs. 20,000
- **Verification**:
  - Neither Total Assets nor P&L change (internal asset transfer).
  - Cash decreases by 20,000; Bank increases by 20,000.

---

### Test Workflow 6: Customer Receivable Collection
- **Accounting Transaction**: Customer B pays Rs. 80,000 towards their outstanding bill into company bank account.
  - **Debit**: `1120 Bank Accounts` -> Rs. 80,000
  - **Credit**: `1200 Accounts Receivable` -> Rs. 80,000
- **Verification**:
  - `Accounts Receivable` balance drops from Rs. 120,000 to Rs. 40,000.
  - Open **General Ledger** -> Select `1200 Accounts Receivable`:
    - Row 1: Sales invoice (Debit Rs. 120,000 | Balance: Rs. 120,000)
    - Row 2: Customer payment (Credit Rs. 80,000 | Balance: Rs. 40,000)

---

### Test Workflow 7: Financial Statements Audit
Go to **Reports** and test all 4 core statements:

1. **Trial Balance**:
   - Verify Total Debits === Total Credits.
   - Verify status badge displays: `✓ Trial Balance matches (Debit = Credit)`.
2. **Profit & Loss Statement**:
   - Total Revenue = Rs. 195,000 (75k + 120k)
   - Total Expenses = Rs. 35,000
   - Net Profit = Rs. 160,000 (`Revenue - Expenses`)
3. **Balance Sheet**:
   - **Assets**: Cash + Bank + Accounts Receivable
   - **Liabilities**: Rs. 0
   - **Equity**: Owner Equity (Rs. 500,000) + Retained Earnings / Net Profit (Rs. 160,000)
   - Verify status displays: `Balance Sheet is balanced ✓`.

---

### Test Workflow 8: Custom Voucher Builder
- **Step 8.1**: Click **Voucher Builder** in the sidebar.
- **Step 8.2**: Create a custom voucher template (e.g. `Service Invoice`):
  - Add text field: `Client Reference`
  - Add number field: `Discount Rate %`
  - Add date field: `Due Date`
- **Step 8.3**: Save and verify that the custom voucher definition is listed under **Custom Voucher Types**.

---

### Test Workflow 9: Multi-Company Context Switching
- **Step 9.1**: Click the company switcher dropdown in the top-left corner.
- **Step 9.2**: Select **Kamal Express** (`KAMAL`).
- **Step 9.3**: Open **Chart of Accounts** and **Reports**:
  - Verify that the financial records and vouchers from `MAIN` company do NOT appear in `KAMAL`.
  - Switch back to `MAIN` company and confirm all original vouchers and balances remain intact.

---

## 3. Test Execution Sign-off Matrix

| # | Test Area | Status | Verified By | Notes |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Chart of Accounts Hierarchy & CRUD | [ ] | | |
| 2 | Capital Injection / Owner Equity | [ ] | | |
| 3 | Cash & Credit Sales Recording | [ ] | | |
| 4 | Expense Payments & Cashbook | [ ] | | |
| 5 | Contra Transfers (Cash <-> Bank) | [ ] | | |
| 6 | Receivable Recovery & Ledger Audit | [ ] | | |
| 7 | Trial Balance Equality (`Dr = Cr`) | [ ] | | |
| 8 | Profit & Loss Net Income Accuracy | [ ] | | |
| 9 | Balance Sheet Equilibrium | [ ] | | |
| 10 | Custom Voucher Builder Form Fields | [ ] | | |
| 11 | Multi-Company Isolation (`MAIN` vs `KAMAL`) | [ ] | | |
