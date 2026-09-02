# Frontend Concept-to-Code Repository Index

This document maps all frontend components, pages, custom hooks, and API endpoints to their source files in `AlamiaAccounts-Frontend`.

---

## 1. Directory Structure

```
AlamiaAccounts-Frontend/
├── app/                                    # Next.js App Router
│   ├── globals.css                         # Global CSS & Tailwind variables
│   ├── layout.tsx                          # Root HTML & metadata shell
│   ├── providers.tsx                       # TanStack Query & Theme Providers
│   ├── login/page.tsx                      # Login authentication page
│   └── page.tsx                            # Main application layout / workspace
├── components/                             # Feature & View Components
│   ├── account-tree-view.tsx               # Hierarchical CoA interactive tree
│   ├── chart-of-accounts.tsx               # Chart of Accounts management container
│   ├── company-switcher.tsx                # Active domain/company selector dropdown
│   ├── daybook.tsx                         # Daily transaction register
│   ├── cashbook.tsx                        # Cash & Bank transaction register
│   ├── financial-reports.tsx               # P&L, Balance Sheet, Trial Balance reports
│   ├── ledger-detail-view.tsx              # Granular account ledger statement
│   ├── voucher-entry.tsx                   # Multi-entry voucher posting form
│   ├── voucher-line-items.tsx              # Dynamic split debit/credit table
│   └── ui/                                 # Radix UI / Tailwind primitives
├── hooks/                                  # React Query custom hooks
│   ├── use-accounts.ts                     # Account fetching & creation mutations
│   ├── use-vouchers.ts                     # Voucher posting & daybook queries
│   ├── use-reports.ts                      # Financial reports & ledger queries
│   └── use-companies.ts                    # Domain list & domain switching
└── lib/                                    # API integration & utilities
    ├── api/index.ts                        # Typed API endpoint definitions
    ├── api-client.ts                       # Axios client instance with auth interceptor
    └── utils.ts                            # Tailwind merge & formatters
```

---

## 2. Feature & Component Mapping

| Feature | Key Component | Hooks / API |
| :--- | :--- | :--- |
| **Authentication** | [`login/page.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/app/login/page.tsx) | `authApi.login()`, `authApi.me()` |
| **Company Switcher** | [`company-switcher.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/company-switcher.tsx) | `useCompanies()`, `companyApi.switch()` |
| **Chart of Accounts** | [`chart-of-accounts.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/chart-of-accounts.tsx), [`account-tree-view.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/account-tree-view.tsx) | `useAccounts()`, `accountApi.getAll()` |
| **Voucher Entry** | [`voucher-entry.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/voucher-entry.tsx), [`voucher-line-items.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/voucher-line-items.tsx) | `useVouchers()`, `voucherApi.create()` |
| **Daybook & Cashbook** | [`daybook.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/daybook.tsx), [`cashbook.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/cashbook.tsx) | `useDaybook()`, `useCashbook()` |
| **Financial Reports** | [`financial-reports.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/financial-reports.tsx), [`ledger-detail-view.tsx`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Frontend/components/ledger-detail-view.tsx) | `useReports.ts`, `reportApi.*` |
