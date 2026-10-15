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
];
