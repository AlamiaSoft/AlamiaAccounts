# Alamia Accounts CI

The `Alamia Accounts CI` workflow runs when backend files change in the monorepo, when its workflow changes, or when manually dispatched. It validates and installs the backend Composer dependencies, checks PHP syntax, audits dependencies, runs the repository architecture checker, and executes all PHPUnit suites configured in `AlamiaAccounts-Backend/phpunit.xml` against SQLite and PostgreSQL.

Laravel Pint currently reports existing formatting issues across the host application, packages, and tests, including the voucher service and its test file. Pint is therefore not a blocking CI check yet; add it after the existing style backlog is resolved or after the team adopts a scoped formatting policy. Composer validation uses `--no-check-publish` because the monorepo intentionally uses `@dev` path dependencies before the package-release phase.

The PHPUnit source list includes both the Laravel host application's `app/` directory and the reusable `alamia-accounts` package source. This keeps any future coverage reports focused on the accounting code as well as its host integration.

Voucher posting now requires each referenced account to be linked to the active domain and rejects category/group accounts. Posting no longer silently attaches a global account to a tenant domain. The voucher tests cover those rules alongside rejection of unbalanced vouchers and duplicate references.

The workflow currently runs PHPUnit-discovered tests. Standalone scripts under `AlamiaAccounts-Backend/tests/integration/` are not part of `php artisan test`; review each script's database setup and cleanup before promoting it to an automated CI test. Browser E2E scripts remain a separate, explicit verification step.

Run the local PHPUnit suite from `AlamiaAccounts-Backend` with:

```sh
php artisan test
```

The suite was attempted during this change, but the local PHPUnit launcher stopped before discovery with `Cannot open bootstrap script ".../vendor/autoload.php"`. The changed PHP files pass `php -l`; PHPUnit still needs a successful run in CI or a repaired local runtime before this readiness task can be marked complete.

The database matrix is intended to catch SQLite-specific behavior before the backend is considered ready for release packaging. A passing workflow is a required baseline, not a substitute for accountant review of accounting rules and generated reports.
