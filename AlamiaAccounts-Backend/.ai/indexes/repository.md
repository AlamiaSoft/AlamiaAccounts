# Backend Concept-to-Code Repository Index

This document maps all backend features, models, services, controllers, and migrations to their file paths in `AlamiaAccounts-Backend`.

---

## 1. Directory Structure

```
AlamiaAccounts-Backend/
├── app/                                    # Host Application core (Auth, Providers, Models)
├── config/                                 # Laravel configurations (sanctum, permission, database)
├── database/                               # Host database migrations and seeders
├── packages/                               # Local package developmental workspace
│   └── AlamiaSoft/
│       └── alamia-accounts/                # Standalone Accounting Engine Package
│           ├── config/alamia-accounts.php  # Package configuration
│           ├── database/migrations/        # Domain pivot & numbering migrations
│           ├── database/seeders/           # Default CoA and Ledger initialization seeders
│           ├── routes/api.php              # RESTful accounting API routes
│           ├── src/
│           │   ├── AlamiaAccountsServiceProvider.php
│           │   ├── Console/Commands/       # CLI commands (SetupLedger, TestLedger)
│           │   ├── Http/Controllers/Api/   # API Controllers
│           │   ├── Models/                 # Domain pivot & numbering models
│           │   └── Services/               # Core business services
│           └── tests/                      # Package test suite
└── routes/                                 # Host routes
```

---

## 2. Component & File Mapping

### Controllers (`packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/`)
* **[`AuthController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/AuthController.php)**: User login, logout, current user context (`/login`, `/logout`, `/me`).
* **[`CompanyController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/CompanyController.php)**: Domain switching and company/branch/department CRUD (`/companies`, `/companies/{code}/switch`).
* **[`AccountController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/AccountController.php)**: Chart of Accounts CRUD, hierarchy navigation (`/accounts`).
* **[`VoucherController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/VoucherController.php)**: Journal entry and voucher submission (`/vouchers`).
* **[`ReportController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/ReportController.php)**: Trial balance, P&L, balance sheet, and account ledger endpoints (`/reports/*`).
* **[`SearchController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/SearchController.php)**: Global search across vouchers, accounts, and ledgers (`/search/*`).
* **[`PrintController.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Http/Controllers/Api/PrintController.php)**: Voucher PDF generation, template customization, and logo upload (`/print/*`).

### Services (`packages/AlamiaSoft/alamia-accounts/src/Services/`)
* **[`DomainContext.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/DomainContext.php)**: Active domain code/UUID tracking and execution scoping.
* **[`AccountService.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountService.php)**: Account hierarchy builder, balances, domain pivot bindings.
* **[`VoucherService.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php)**: Double-entry voucher posting, domain validation, split creation.
* **[`VoucherNumberingService.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherNumberingService.php)**: Domain and fiscal year sequence numbering.
* **[`ReportService.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ReportService.php)**: Financial statement calculation engine.
* **[`CompanyService.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/CompanyService.php)**: Multi-level domain management.

### Models (`packages/AlamiaSoft/alamia-accounts/src/Models/`)
* **[`DomainLedgerAccount.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainLedgerAccount.php)**: Maps domain UUIDs to account UUIDs.
* **[`DomainJournalEntry.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainJournalEntry.php)**: Maps domain UUIDs to journal entry UUIDs.
* **[`VoucherNumberSeries.php`](file:///d:/MyApps/Alamia/AlamiaAccounts/AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/VoucherNumberSeries.php)**: Numbering series configuration per domain/type.
