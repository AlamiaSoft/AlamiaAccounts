# Alamia Accounts — Package Integration & Installation Guide

This guide provides step-by-step instructions for installing and integrating **`alamiasoft/alamia-accounts`** into any Laravel 10/11+ application (both fresh installations and existing projects).

---

## 1. Overview & Architecture

`alamiasoft/alamia-accounts` is a standalone, enterprise-grade accounting package built on top of `abivia/ledger`. It encapsulates full double-entry ledger capabilities, multi-tenant company isolation, customizable voucher builders, fiscal accounting period controls, automated integrity checkpoints, financial reporting (Trial Balance, P&L, Balance Sheet, Ledgers), POS/Sales synchronization APIs, and AI-powered accounting copilot integration.

---

## 2. System Requirements

- **PHP**: `>= 8.2`
- **Laravel**: `>= 10.0` (Laravel 11 recommended)
- **Database**: SQLite, MySQL, or PostgreSQL
- **Key PHP Extensions**: `pdo`, `mbstring`, `openssl`, `bcmath`

---

## 3. Installation

### A. Installing via Local Path Repository (Development / Monorepo)
In your host application's `composer.json`, add the local package path under the `repositories` key:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/AlamiaSoft/alamia-accounts"
        }
    ],
    "require": {
        "alamiasoft/alamia-accounts": "@dev"
    }
}
```

Then run:
```bash
composer require alamiasoft/alamia-accounts:@dev
```

### B. Installing via Private VCS / Packagist (Production)
```bash
composer require alamiasoft/alamia-accounts
```

---

## 4. Package Discovery & Configuration

The package registers `AlamiaAccountsServiceProvider` automatically via Laravel package discovery.

### Publish Configuration, Migrations, and Views
Publish the package configuration, migrations, and Blade templates using Artisan:

```bash
# Publish configuration
php artisan vendor:publish --tag="alamia-accounts-config"

# Publish migrations (optional - package auto-loads them)
php artisan vendor:publish --tag="alamia-accounts-migrations"

# Publish thermal receipt and voucher printing views
php artisan vendor:publish --tag="alamia-accounts-views"
```

The published configuration will be available at `config/alamia-accounts.php`.

---

## 5. Database Setup & Migrations

Run database migrations to initialize both `abivia/ledger` core tables and Alamia domain tables:

```bash
php artisan migrate
```

### Initializing Standard Chart of Accounts & Seeders
To seed the standard multi-level Chart of Accounts and default company profiles (e.g. `MAIN`, `KAMAL_EXPRESS`):

```bash
php artisan db:seed --class="AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder"
```

---

## 6. Authentication & Middleware Setup

The package integrates with Laravel Sanctum for API token authentication and provides a dual-mode middleware for POS and external sales gateways (`AuthenticateSalesOrSanctum`):

```php
// In bootstrap/app.php (Laravel 11) or app/Http/Kernel.php (Laravel 10):
$middleware->alias([
    'sales.auth' => \AlamiaSoft\AlamiaAccounts\Http\Middleware\AuthenticateSalesOrSanctum::class,
]);
```

### Multi-Tenant Company Scope
All API requests and service operations are tenant-scoped. Provide the company code in the HTTP header:
```http
X-Company-Code: MAIN
```
Or set the tenant context programmatically in PHP:
```php
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

DomainContext::setDomain('KAMAL_EXPRESS');
```

---

## 7. Using Domain Services in PHP

All accounting operations must go through Alamia Domain Services rather than low-level database queries:

### A. Account Management (`AccountService`)
```php
use AlamiaSoft\AlamiaAccounts\Services\AccountService;

$accountService = app(AccountService::class);

// Create a posting account under Current Assets
$account = $accountService->createAccount([
    'code' => '1115',
    'name' => 'Petty Cash - Main Office',
    'parent_code' => '1100', // Current Assets folder
    'debit' => true,
    'category' => false,
]);

// Retrieve hierarchical Chart of Accounts
$coa = $accountService->getChartOfAccounts();
```

### B. Creating Balanced Vouchers (`VoucherService`)
```php
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;

$voucherService = app(VoucherService::class);

$voucher = $voucherService->createVoucher([
    'date' => '2026-10-08',
    'description' => 'Office Supplies Expense',
    'lines' => [
        [
            'account_code' => '4200', // Operating Expenses
            'debit' => 2500.00,
            'credit' => 0.00,
            'memo' => 'Stationery and supplies',
        ],
        [
            'account_code' => '1110', // Cash in Hand
            'debit' => 0.00,
            'credit' => 2500.00,
            'memo' => 'Paid from cash drawer',
        ],
    ],
]);
```

### C. Financial Reporting (`ReportService`)
```php
use AlamiaSoft\AlamiaAccounts\Services\ReportService;

$reportService = app(ReportService::class);

// Generate real-time Trial Balance (Debit === Credit invariant)
$trialBalance = $reportService->getTrialBalance();

// Generate Balance Sheet (Assets = Liabilities + Equity)
$balanceSheet = $reportService->getBalanceSheet();

// Generate Profit and Loss (Income vs Expenses)
$profitAndLoss = $reportService->getProfitAndLoss();
```

### D. POS Sales Ingestion & Real-Time Approval (`SalesIntegrationService`)
```php
use AlamiaSoft\AlamiaAccounts\Services\SalesIntegrationService;

$salesService = app(SalesIntegrationService::class);

// Ingest sale from POS machine
$sale = $salesService->ingestSale([
    'company_code' => 'KAMAL_EXPRESS',
    'customer_name' => 'Walk-in Customer',
    'subtotal' => 1500.00,
    'tax_amount' => 0.00,
    'discount_amount' => 0.00,
    'total_amount' => 1500.00,
    'payment_method' => 'cash',
    'items' => [
        [
            'item_description' => 'Bus Ticket - LHR to ISB',
            'quantity' => 1,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
        ]
    ]
]);

// Approve and post to general ledger automatically
$result = $salesService->approveAndPostSale($sale->id);
```

---

## 8. REST API Endpoints Overview

The package automatically mounts REST endpoints under the `/api` prefix:

| Endpoint | Method | Description |
| :--- | :---: | :--- |
| `/api/accounts` | `GET`, `POST` | Chart of Accounts hierarchy & account creation |
| `/api/vouchers` | `GET`, `POST` | Double-entry journal voucher listing and creation |
| `/api/vouchers/{ref}/reverse` | `POST` | GAAP compliant voucher reversal |
| `/api/vouchers/{ref}` | `DELETE` | **BLOCKED (422)**: Preserves historical ledger immutability |
| `/api/reports/trial-balance` | `GET` | Trial Balance with Dr/Cr totals |
| `/api/reports/balance-sheet` | `GET` | Balance Sheet with Retained Earnings balance |
| `/api/reports/profit-loss` | `GET` | Income Statement / Profit and Loss |
| `/api/v1/sales` | `GET`, `POST` | POS sales listing and raw ticket ingestion |
| `/api/v1/sales/{id}/approve` | `POST` | Approve POS sale and generate balanced ledger voucher |
| `/api/v1/sales/reconcile-shift` | `GET` | Cashier shift handover reconciliation |
| `/api/v1/receipts/{id}/print` | `GET` | Thermal ESC/POS receipt Blade template |
| `/api/copilot/chat` | `POST` | Taliya AI natural language accounting copilot |

---

## 9. Verification & Testing

To verify package integration in your project, run the pre-packaged verification suites:

```bash
# Verify backend sales integration API
php tests/Feature/verify_sales_integration_api.php

# Verify accounting integrity checkpoints
php artisan test --filter=AccountingIntegrityCheckpointTest

# Verify architectural conformance
node scripts/verify-architecture.js
```
