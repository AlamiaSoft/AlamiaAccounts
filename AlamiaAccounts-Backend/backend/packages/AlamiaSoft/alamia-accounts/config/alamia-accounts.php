<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency to use for accounting operations.
    |
    */
    'default_currency' => env('ALAMIA_ACCOUNTS_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Default Domain
    |--------------------------------------------------------------------------
    |
    | The default ledger domain to use. In multi-company scenarios, this is
    | the domain that will be used when no explicit domain is specified.
    |
    */
    'default_domain' => env('ALAMIA_ACCOUNTS_DOMAIN', 'MAIN'),

    /*
    |--------------------------------------------------------------------------
    | Multi-Company Mode
    |--------------------------------------------------------------------------
    |
    | Enable multi-company mode to support multiple companies/business units.
    | When disabled, all operations use the default domain.
    |
    */
    'multi_company' => env('ALAMIA_ACCOUNTS_MULTI_COMPANY', false),

    /*
    |--------------------------------------------------------------------------
    | Strict Domain Isolation
    |--------------------------------------------------------------------------
    |
    | When enabled, prevents accidental cross-domain operations.
    |
    */
    'strict_domain_isolation' => true,
];
