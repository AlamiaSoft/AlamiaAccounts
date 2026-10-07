<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerDomain;

/**
 * Domain Context Helper
 * 
 * Manages the current active domain for accounting operations.
 * This is a simple wrapper that stores the current domain code.
 */
class DomainContext
{
    protected static ?string $currentDomain = null;

    /**
     * Set the current domain context.
     *
     * @param string $code Domain code
     * @return void
     */
    public static function set(string $code): void
    {
        static::$currentDomain = $code;
    }

    public static function get(): ?string
    {
        if (function_exists('request') && request()) {
            $headerDomain = request()->header('X-Company-Code');
            if (!empty($headerDomain)) {
                static::$currentDomain = $headerDomain;
                return static::$currentDomain;
            }
        }

        if (!isset(static::$currentDomain)) {
            // Default to first domain if not set
            $domain = LedgerDomain::first();
            if ($domain) {
                static::$currentDomain = $domain->code;
            }
        }
        
        return static::$currentDomain;
    }

    /**
     * Get the current domain object.
     *
     * @return LedgerDomain|null Current domain object or null if not set
     */
    public static function getDomain(): ?LedgerDomain
    {
        $code = static::get();
        if (!$code) {
            return null;
        }
        
        return LedgerDomain::where('code', $code)->first();
    }

    /**
     * Execute a callback within a specific domain context.
     * 
     * @param string $domain
     * @param callable $callback
     * @return mixed
     */
    public static function scope(string $domain, callable $callback): mixed
    {
        $previousDomain = static::get();
        
        try {
            static::set($domain);
            return $callback();
        } finally {
            if ($previousDomain) {
                static::set($previousDomain);
            }
        }
    }

    /**
     * Get the configured default currency for a company domain or the current active domain.
     *
     * @param string|null $companyCode Optional company code
     * @return string ISO currency code (defaults to 'PKR')
     */
    public static function getDefaultCurrency(?string $companyCode = null): string
    {
        if (!empty($companyCode)) {
            $domain = LedgerDomain::where('code', strtoupper(trim($companyCode)))->first();
            if ($domain && !empty($domain->currencyDefault)) {
                return $domain->currencyDefault;
            }
        }

        $currentDomain = static::getDomain();
        if ($currentDomain && !empty($currentDomain->currencyDefault)) {
            return $currentDomain->currencyDefault;
        }

        return config('alamia-accounts.default_currency', 'PKR');
    }

    /**
     * Reset to default domain.
     */
    public static function reset(): void
    {
        static::$currentDomain = null;
    }
}
