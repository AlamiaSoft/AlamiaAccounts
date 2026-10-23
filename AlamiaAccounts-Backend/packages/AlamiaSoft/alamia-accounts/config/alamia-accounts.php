<?php

return [
    'default_currency' => env('ALAMIA_ACCOUNTS_CURRENCY', 'USD'),
    'default_domain' => env('ALAMIA_ACCOUNTS_DOMAIN', 'MAIN'),
    'multi_company' => env('ALAMIA_ACCOUNTS_MULTI_COMPANY', false),
    'strict_domain_isolation' => true,

    /*
     * JSON object mapping SHA-256 hashes of POS secrets to company codes.
     * Never put raw credentials in this setting or commit live credentials.
     */
    'pos_api_key_hashes' => json_decode(
        (string) env('ALAMIA_ACCOUNTS_POS_API_KEY_HASHES', '{}'),
        true
    ) ?: [],

    /*
     * Destructive bulk ledger reset feature flag.
     * Strictly disabled (false) by default. Must never be enabled in production environments.
     */
    'allow_destructive_reset' => (bool) env('ALAMIA_ACCOUNTS_ALLOW_DESTRUCTIVE_RESET', false),

    /*
     * Maker-checker approval threshold for high-value accounting transactions.
     * Transactions with total debit >= this threshold require substantive business
     * reason, exact amount re-entry, and approver distinct from maker when specified.
     */
    'maker_checker_threshold' => (float) env('ACCOUNTING_MAKER_CHECKER_THRESHOLD', env('COPILOT_MAKER_CHECKER_THRESHOLD', 100000.0)),

    /*
     * Enforce strict distinct-user separation of duties for maker-checker approvals.
     */
    'maker_checker_require_distinct_users' => (bool) env('ACCOUNTING_MAKER_CHECKER_DISTINCT_USERS', true),
];
