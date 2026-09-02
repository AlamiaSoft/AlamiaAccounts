# AlamiaAccounts

A robust accounting package for Laravel using `abivia/ledger` under the hood. Provides professional accounting features including Chart of Accounts, Vouchers, Financial Reports, and Multi-Company support.

## Installation

1. Add the package to your Laravel project:

```bash
composer require alamiasoft/alamia-accounts
```

2. The service provider will be auto-discovered. If needed, register it manually in `config/app.php`:

```php
'providers' => [
    AlamiaSoft\AlamiaAccounts\AlamiaAccountsServiceProvider::class,
],
```

3. Ensure `abivia/ledger` migrations are run:

```bash
php artisan migrate
```

## Features

- **Chart of Accounts Management**: Create and manage account hierarchies
- **Voucher System**: Support for all major voucher types (Sales, Purchase, Payment, Receipt, Journal)
- **Financial Reports**: Trial Balance, Profit & Loss, Balance Sheet
- **Multi-Company Support**: Manage multiple companies/departments using Domains
- **Built on abivia/ledger**: Leverages a proven double-entry accounting system

## Usage

### Account Management

```php
use AlamiaSoft\AlamiaAccounts\Services\AccountService;

$accountService = app(AccountService::class);

// Create an account
$account = $accountService->createAccount([
    'code' => 'CASH001',
    'name' => 'Cash in Hand',
    'debit' => true,
]);

// Create account with parent
$account = $accountService->createAccount([
    'code' => 'BANK001',
    'name' => 'Bank Account',
    'parent_code' => 'ASSETS',
    'debit' => true,
]);

// Get chart of accounts
$accounts = $accountService->getChartOfAccounts();
```

### Voucher Management

```php
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;

$voucherService = app(VoucherService::class);

// Create a sales voucher
$voucher = $voucherService->createSalesVoucher([
    'customer_account_code' => 'CUST001',
    'sales_account_code' => 'SALES',
    'total_amount' => 1000,
    'net_amount' => 900,
    'tax_amount' => 100,
    'tax_account_code' => 'TAX',
    'date' => '2025-01-01',
    'voucher_number' => 'SV001',
    'currency' => 'USD',
]);

// Create a payment voucher
$voucher = $voucherService->createPaymentVoucher([
    'payee_account_code' => 'SUPPLIER001',
    'bank_account_code' => 'BANK001',
    'amount' => 500,
    'date' => '2025-01-01',
    'voucher_number' => 'PV001',
]);
```

### Reports

```php
use AlamiaSoft\AlamiaAccounts\Services\ReportService;

$reportService = app(ReportService::class);

// Get trial balance
$trialBalance = $reportService->getTrialBalance('2025-01-31', 'USD');

// Get profit and loss
$pnl = $reportService->getProfitAndLoss('2025-01-01', '2025-01-31', 'USD');

// Get balance sheet
$balanceSheet = $reportService->getBalanceSheet('2025-01-31', 'USD');
```

## Multi-Level Organization Support

The package supports complex organizational structures including separate companies, departments, and branches using Abivia Ledger's Domains.

### Scenario 1: Accountant Managing Multiple Clients

Perfect for accountants managing completely separate legal entities:

```php
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;

$companyService = app(CompanyService::class);

// Create separate client companies (each is a different legal entity)
$companyService->createCompany('CLIENT_A', 'ABC Corporation', ['currency' => 'USD']);
$companyService->createCompany('CLIENT_B', 'XYZ Industries', ['currency' => 'USD']);
$companyService->createCompany('CLIENT_C', 'LMN Enterprises', ['currency' => 'EUR']);

// List all companies
$companies = $companyService->listCompanies();

// Work with a specific client
DomainContext::scope('CLIENT_A', function() use ($accountService) {
    // All operations for ABC Corporation
    $accountService->createAccount([...]);
});
```

### Scenario 2: Single Company with Departments

For businesses that need department-level accounting:

```php
// Create the company
$companyService->createCompany('MYCOMPANY', 'My Business Ltd');

// Create departments within the company
$companyService->createDepartment('MYCOMPANY', 'SALES', 'Sales Department');
$companyService->createDepartment('MYCOMPANY', 'MARKETING', 'Marketing Department');
$companyService->createDepartment('MYCOMPANY', 'HR', 'Human Resources');

// List all departments
$departments = $companyService->listDepartments('MYCOMPANY');

// Work within a specific department
DomainContext::scope('MYCOMPANY_SALES', function() use ($voucherService) {
    // Sales department transactions
    $voucherService->createSalesVoucher([...]);
});

// Get organization structure
$orgTree = $companyService->getOrganizationTree('MYCOMPANY');
```

### Scenario 3: Company with Multiple Branches

For businesses with head office, regional offices, or site locations:

```php
// Create the company
$companyService->createCompany('RETAIL_CO', 'Retail Company');

// Create branches
$companyService->createBranch('RETAIL_CO', 'HEAD_OFFICE', 'Head Office');
$companyService->createBranch('RETAIL_CO', 'NORTH_BRANCH', 'Northern Regional Office');
$companyService->createBranch('RETAIL_CO', 'SOUTH_BRANCH', 'Southern Regional Office');
$companyService->createBranch('RETAIL_CO', 'SITE_A', 'Construction Site A');

// List all branches
$branches = $companyService->listBranches('RETAIL_CO');

// Each branch maintains its own accounts
DomainContext::scope('RETAIL_CO_HEAD_OFFICE', function() use ($accountService) {
    $accountService->createAccount(['code' => 'CASH_HO', 'name' => 'Cash - Head Office']);
});

DomainContext::scope('RETAIL_CO_NORTH_BRANCH', function() use ($accountService) {
    $accountService->createAccount(['code' => 'CASH_NORTH', 'name' => 'Cash - North Branch']);
});
```

### Scenario 4: Complex Hierarchy (Departments + Branches)

Combine departments and branches for complex organizations:

```php
// Create company
$companyService->createCompany('BIGCORP', 'Big Corporation');

// Create departments
$companyService->createDepartment('BIGCORP', 'SALES', 'Sales');
$companyService->createDepartment('BIGCORP', 'OPERATIONS', 'Operations');

// Create branches
$companyService->createBranch('BIGCORP', 'HQ', 'Headquarters');
$companyService->createBranch('BIGCORP', 'REGIONAL_EAST', 'East Region');
$companyService->createBranch('BIGCORP', 'REGIONAL_WEST', 'West Region');
```

### Domain Context

```php
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

// Set active domain globally
DomainContext::set('CLIENT_A');

// All operations now use CLIENT_A
$accountService->createAccount([...]);

// Execute operations in a specific domain context
DomainContext::scope('CLIENT_B', function() use ($accountService) {
    $accountService->createAccount([...]);
    // This account is created in CLIENT_B
});

// Context automatically reverts after the callback
```

### Configuration

Enable multi-company mode in `config/alamia-accounts.php`:

```php
'multi_company' => true,
'default_domain' => 'MAIN',
'strict_domain_isolation' => true,
```

## Testing

```php
php artisan test packages/alamiasoft/alamia-accounts/tests
```

## License

MIT

