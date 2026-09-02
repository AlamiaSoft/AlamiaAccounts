# Backend Glossary: Terms & Invariants

* **`DomainContext`**: The request-scoped manager (`AlamiaSoft\AlamiaAccounts\Services\DomainContext`) controlling active tenant context.
* **`domain_ledger_accounts`**: Pivot table associating account UUIDs with domain UUIDs without modifying standard Abivia tables.
* **`domain_journal_entries`**: Pivot table associating journal entry UUIDs with domain UUIDs.
* **`voucher_number_series`**: Database table controlling auto-incrementing serials with domain prefix and fiscal year formatting.
* **`Abivia Ledger`**: Vendor double-entry accounting engine (`abivia/ledger`).
* **`Trial Balance`**: Balance summary across all accounts in the active domain.
* **`Account Ledger`**: Running debit/credit timeline with running balance for a specific account.
