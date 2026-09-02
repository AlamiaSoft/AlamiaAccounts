# Alamia Accounts Software Manual & System Overview

Welcome to the **Alamia Accounts** technical and operational software documentation.

---

## 1. System Architecture

Alamia Accounts is an enterprise-grade, double-entry accounting software built with a modern decoupled stack:

- **Backend Core**: Laravel 12 on PHP 8.3 with the **Abivia Ledger** double-entry engine.
- **Frontend App**: Next.js 16 (React 19 + Tailwind CSS + Shadcn UI + Lucide Icons).
- **Database Engine**: Local-First SQLite (or MySQL for scaled multi-tenant clusters).
- **Domain Scoping**: Non-invasive pivot tables (`domain_ledger_accounts`, `domain_journal_entries`) allowing multi-company and multi-branch isolation within a single unified database.
- **Client Protocol**: Stateless REST API communicating with `Authorization: Bearer <token>` and `X-Company-Code: <domain_code>`.

---

## 2. Core Functional Modules

### A. Chart of Accounts (COA)
- Structured 5-level hierarchical tree following standard accounting principles:
  - `1000 Assets`
  - `2000 Liabilities`
  - `3000 Equity`
  - `4000 Expenses`
  - `5000 Revenue`
- Distinguishes between **Category accounts** (foldered parents for grouping) and **Posting accounts** (leaf accounts where journal entries land).
- Real-time balance calculation derived directly from verified ledger journal entries.

### B. Voucher Management (Double-Entry Engine)
Transactions are entered through structured vouchers:
- **Receipt Voucher**: Cash or bank inflows from sales, customer receivables, or capital.
- **Payment Voucher**: Cash or bank outflows for expenses, supplier payables, or asset purchases.
- **Sales Voucher**: Direct customer invoices (cash or credit).
- **Purchase Voucher**: Vendor bills and inventory acquisitions.
- **Contra Voucher**: Internal funds transfers (Cash to Bank, Bank to Cash, Bank to Bank).
- **Journal Voucher**: Adjustment entries, depreciation, accruals, and year-end closings.
- **Custom Vouchers**: Domain-tailored vouchers built dynamically via the **Voucher Builder**.

### C. Books of Prime Entry
- **Daybook**: Chronological ledger of all daily financial vouchers with debit/credit leg splits.
- **Cashbook**: Real-time cash journal showing cash receipts, payments, opening cash, and closing cash.

### D. Financial Reporting
- **Trial Balance**: Verifies mathematical integrity where `Total Debits === Total Credits`.
- **Profit & Loss (Income Statement)**: Calculates net operational income (`Total Revenue - Total Expenses`). Supports both T-Format (Income-Expense) and Corporate Vertical formats.
- **Balance Sheet**: Visualizes financial solvency satisfying `Assets = Liabilities + Equity + Retained Earnings`.
- **Account Ledger Statement**: Detailed ledger statement for any selected account with date filters, opening balance, running balance, and closing balance.

### E. Multi-Company & Tenancy
- Enables switching between multiple client businesses (e.g. `Main Company`, `Kamal Express`).
- Complete isolation: transactions and vouchers in one company never leak into another.

### F. Print & Export Engine
- PDF generation and browser thermal/A4 printing for vouchers and receipts.
- Customizable company header, contact details, and computer-generated footer notes.

---

## 3. Documentation Structure

All operational and architectural documentation is maintained under the `/docs` directory:

```text
docs/
├── manual/
│   ├── 01-software-overview.md                 <-- System architecture & modules overview
│   ├── 02-custom-voucher-builder.md            <-- Custom fields, validations, and formulas
│   ├── 03-chart-of-accounts-and-multi-tenancy.md <-- COA roles, folder/posting accounts, and multi-tenant setup
│   └── 04-financial-reporting-engine.md        <-- Trial balance, P&L, balance sheet specs
└── testing/
    └── accounting-workflows-verification-guide.md <-- End-to-end testing protocol
```
