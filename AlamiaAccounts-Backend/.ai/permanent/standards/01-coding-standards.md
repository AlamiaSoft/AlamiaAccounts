# Backend Coding Standards & Invariants

---

## 1. Multi-Domain & Isolation Invariants
1. **Always Scope Account Queries**:
   - Never query `Abivia\Ledger\Models\LedgerAccount` without scoping via `DomainLedgerAccount::getAccountUuidsForDomain()`.
2. **Always Use DomainContext**:
   - In controllers and services, resolve domain codes via `DomainContext::get()`.
   - Never hardcode domain codes in business services.
3. **No Direct Schema Alterations to Abivia Tables**:
   - Upstream Abivia tables (`ledger_domains`, `ledger_accounts`, `ledger_journal_entries`, etc.) must remain untouched.
   - All customizations must use separate tables (`domain_*`, `voucher_*`, `custom_*`).

---

## 2. Code Quality & PHP Conventions
- **PHP Version**: PHP 8.2+ with strict typing (`declare(strict_types=1);` when applicable).
- **PSR-12 Standard**: Clean, readable, PSR-12 compliant formatting.
- **Service Layer Pattern**: Keep controllers thin; place all transaction logic, entity reference building, and business validation inside `Services/`.
- **Database Transactions**: Wrap multi-table operations (such as journal posting + domain pivot association) inside `DB::transaction()`.

---

## 3. Testing Conventions
- All unit and integration tests reside in `packages/AlamiaSoft/alamia-accounts/tests/`.
- Verify domain isolation: tests must assert that operations in domain A cannot access accounts or ledgers of domain B.
- Run tests via `vendor/bin/phpunit` or `php artisan test`.
