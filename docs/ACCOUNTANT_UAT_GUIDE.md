# Alamia Accounts — Accountant UAT & Operational Guide

**Welcome to Alamia Accounts!**  
This guide provides step-by-step instructions for accountants to set up a new company, establish opening balances, record everyday vouchers, perform month-end period closes, and correct transactions safely.

---

## 1. Setting Up a New Business Entity

1. Log in to the application at `http://localhost:3000`.
2. In the left navigation menu, click **Masters → Companies**.
3. Click the **Add Company** button.
4. Fill in:
   - **Company Name**: e.g., `Kamal Express Travel & Tours`
   - **Company Code**: A short unique identifier, e.g., `KAMAL`
   - **Base Currency**: `PKR` (or your operating currency)
   - **Industry**: e.g., `Travel & Tourism`
5. Click **Save Company**.
6. Switch into your new company using the **Company Switcher** at the top-left of the sidebar.
   - Your company starts with clean, isolated books (Balances: Rs. 0.00) while inheriting the standard Chart of Accounts.

---

## 2. Establishing Initial Balance Sheet (Opening Balances)

> [!IMPORTANT]
> Opening balances should be entered once at the inception of your company on the platform. The system enforces strict double-entry balance: Total Debits must equal Total Credits.

1. Navigate to **Masters → Chart of Accounts** (`?page=coa`).
2. Click the **Opening Balances** button in the top toolbar (next to *Add Group* and *Add Account*).
3. In the Opening Balance modal:
   - **Effective Date**: Choose your balance sheet cutoff date (e.g., `2026-01-01`).
   - Enter your opening amounts into the **Debit** or **Credit** column for each relevant account:
     - *Assets (Cash, Bank, Trade Debtors, Office Equipment, Vehicles)* → **Debit**
     - *Liabilities (Trade Creditors, Bank Loans, Taxes Payable)* → **Credit**
     - *Equity (Owner's Capital, Retained Earnings)* → **Credit**
   - Review the bottom **Reconciliation Bar**:
     - **Total Debits**: Rs. xxx
     - **Total Credits**: Rs. xxx
     - **Variance**: If your books have a difference (e.g. historical unallocated earnings), select whether to allocate the variance to **Owner's Capital (5100)** or **Retained Earnings (5200)**.
4. Click **Post Opening Balances**.
5. Navigate to **Financial Reports → Balance Sheet** (`?page=balance-sheet`):
   - Verify the green **Balanced (Diff: Rs. 0)** badge appears.

---

## 3. Daily Transactions & Voucher Workflows

### 3.1 Cash & Bank Receipts / Payments
- To record customer receipts, cash sales, vendor payments, or internal cash transfers:
  - Go to **Vouchers → Payment Voucher / Receipt Voucher / Contra Voucher** or use the **Voucher Builder** (`?page=voucher-journal`).
  - Enter the date, select the debit and credit accounts, input the amount, and enter a narration.
  - The system validates that `Total Debits === Total Credits` before allowing you to post.

### 3.2 Reviewing Transactions in the Daybook
- Go to **Transactions → Day Book** (`?page=daybook`).
- Toggle between single-day view or check **Show all dates** to view all vouchers.
- The Daybook displays voucher reference, voucher type, date, accounts, debits, credits, and status badges.

---

## 4. Month-End Close & Period Locking

To protect finalized accounting books from accidental backdating, alterations, or unauthorized entries after financial reports or tax filings:

1. Navigate to **Masters → Accounting Periods** (`?page=periods`).
2. Select the current **Fiscal Year** (e.g., `FY 2026`).
3. You will see 12 monthly accounting periods (e.g. `P-01: January 2026`).
4. To lock a completed month:
   - Click the **Lock Period** button on that month's row.
   - The status badge will change to **Locked** (red lock icon).
   - Any attempt to post a voucher dated within that month will be rejected by the system with:
     > *"Accounting period 'January 2026' is closed. Posting transactions into closed periods is not permitted."*
5. To reopen a period (for authorized year-end audit adjustments):
   - Click **Reopen**.
   - Provide a mandatory documented business reason (e.g. *"Audit adjustment approved by CFO"*).
   - The reopen action and reason are permanently logged in the audit trail.

---

## 5. Correcting Errors (Voucher Reversal)

> [!NOTE]
> In accordance with institutional accounting standards (GAAP/IFRS), posted accounting transactions cannot be deleted. History is preserved via **Reverse + Correct**.

If a voucher was posted with incorrect accounts or amounts:

1. Open **Transactions → Day Book** (`?page=daybook`).
2. Locate the voucher you wish to correct.
3. Click the red **Reverse** button on the right side of the row.
4. In the confirmation dialog:
   - Enter the reason for reversal (e.g. *"Invoiced incorrect customer; billing canceled"*).
   - Click **Confirm Reversal**.
5. The system will:
   - Generate an inverse compensating entry with reference `REV-[original-voucher-number]`.
   - Neutralize the balances in the general ledger.
   - Mark the original voucher with a **Reversed** status badge.
   - Record the user, timestamp, and reason in the permanent audit trail.
6. You can now post the correct replacement voucher.

---

## 6. Financial Reporting & Reconciliation

At any time, you can access real-time financial statements in **Reports → Financial Reports**:
- **Trial Balance**: Demonstrates double-entry mathematical balance across all accounts (`Dr = Cr`).
- **Profit & Loss**: Displays revenue, cost of goods sold, operating expenses, and net profit.
- **Balance Sheet**: Displays assets, liabilities, owner's equity, and retained earnings, with real-time equilibrium badges.
- **Ledger Book**: Displays running chronological debit, credit, and net balances for any selected account.
- **Receivables (AR) & Payables (AP)**: Detailed aged balances per customer and vendor.
