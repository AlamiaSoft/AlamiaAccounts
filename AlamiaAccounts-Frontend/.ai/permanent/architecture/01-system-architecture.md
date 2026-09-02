# Frontend Architecture & UI Design

## 1. Architectural Intent
`AlamiaAccounts-Frontend` is an independent, API-driven web client for Alamia Accounts built using **Next.js 14 App Router, React, Tailwind CSS, and TanStack React Query**.

```
+-------------------------------------------------------------------------+
|                        Next.js App Router                               |
|          (Root Layout, Theme Provider, Providers, Login, Main Page)     |
+-------------------------------------------------------------------------+
                                     │
                                     ▼
+-------------------------------------------------------------------------+
|                        Interactive Accounting UI                        |
|  - Dashboard: Analytics, KPIs, Recent Vouchers                         |
|  - Chart of Accounts: Tree View, Group Form, Master Form                |
|  - Voucher Management: Voucher Entry, Line Items Grid, Summary         |
|  - Daybook & Cashbook: Filtering, Running Balances, Date Range Scoping  |
|  - Financial Reports: Trial Balance, P&L, Balance Sheet, Ledger View    |
|  - Settings & Switchers: Company Switcher, Print Templates             |
+-------------------------------------------------------------------------+
                                     │
                                     ▼ (State Management & Caching)
+-------------------------------------------------------------------------+
|                     TanStack React Query Custom Hooks                   |
|       (useAccounts, useVouchers, useReports, useCompanies, useLedger)   |
+-------------------------------------------------------------------------+
                                     │
                                     ▼ (Axios HTTP Client / Bearer Token)
+-------------------------------------------------------------------------+
|                     Alamia Accounts Backend REST API                    |
+-------------------------------------------------------------------------+
```

---

## 2. Core Architectural Invariants

1. **Decoupled API Consumer**:
   - The frontend maintains zero direct database access. All data interactions occur strictly via authenticated JSON REST API calls.
   - Bearer authentication tokens are persisted in secure local storage or cookies and automatically attached to outgoing requests by [`api-client.ts`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/lib/api-client.ts).

2. **Optimistic & Cached State via React Query**:
   - Server state caching, deduplication, and background refetching are centralized in TanStack React Query hooks (`hooks/`).
   - Mutations (e.g. creating vouchers, creating accounts) automatically invalidate relevant query keys to trigger seamless UI updates without full page reloads.

3. **Double-Entry Balance Enforcement at UI Boundary**:
   - The [`voucher-entry.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/voucher-entry.tsx) component strictly enforces that `totalDebit === totalCredit` and `totalDebit > 0` before enabling form submission or firing API mutations.

---

## 3. UI Component Organization
* **Primitives**: Reusable headless primitives built on Radix UI (`components/ui/`).
* **Feature Views**: Domain-level interactive views (`components/chart-of-accounts.tsx`, `components/voucher-entry.tsx`, `components/financial-reports.tsx`, `components/daybook.tsx`, `components/cashbook.tsx`).
* **API Bridge**: Strongly typed Axios endpoints in `lib/api/` matching backend OpenAPI specifications.
