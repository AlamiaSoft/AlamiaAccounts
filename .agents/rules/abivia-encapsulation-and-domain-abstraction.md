# Architectural Abstraction & Abivia Kernel Encapsulation Rule

## Mandate & Directive
All AI agents and developers operating on this repository must strictly obey these architectural boundaries:

1. **Zero Direct Abivia Calls from HTTP Controllers & Feature Layers**:
   - **NEVER** query or instantiate `\Abivia\Ledger\Models\...` (such as `LedgerDomain`, `LedgerAccount`, `JournalEntry`) directly in HTTP Controllers, Middleware, UI routes, or frontline business logic.
   - **ALWAYS** access ledger functionality through designated Alamia domain services:
     - `VoucherService` for vouchers and journal transactions.
     - `CompanyService` / `DomainContext` for multi-tenant domain and company configurations.
     - `AccountService` for chart of accounts and subledger lookups.
     - `ReportService` for trial balance, P&L, balance sheet, and ledgers.
     - `PeriodService` for fiscal year and accounting period locks.
     - `OpeningBalanceService` for compound opening balances.

2. **Centralized Domain & Company Helpers (DRY Principle)**:
   - **NEVER** write scattered, ad-hoc database lookups for company settings (e.g. querying currency, domain codes, UUIDs) across multiple files.
   - **ALWAYS** use centralized context and domain helpers:
     - `DomainContext::getDefaultCurrency(?string $companyCode = null)` / `CompanyService::getDefaultCurrency()`
     - `DomainContext::get()` / `DomainContext::getDomain()` / `DomainContext::set()`
     - `DomainContext::scope($companyCode, fn() => ...)`

3. **Authentication & Authorization Boundary**:
   - Authentication and tenant boundary checks belong strictly at the HTTP middleware pipeline (`AuthenticateSalesOrSanctum`, `auth:sanctum`), never embedded inside core accounting domain services.
