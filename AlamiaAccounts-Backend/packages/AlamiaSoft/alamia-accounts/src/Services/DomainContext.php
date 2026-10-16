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
    public static function set(?string $code): void
    {
        static::$currentDomain = $code !== null ? strtoupper(trim($code)) : null;
        if (function_exists('request') && request()) {
            if (static::$currentDomain !== null) {
                request()->attributes->set('tenant_company_code', static::$currentDomain);
            } else {
                request()->attributes->remove('tenant_company_code');
            }
        }
    }

    /**
     * Get the current active domain code.
     * Returns null if no authorized company context is set. Never falls back to first domain or MAIN.
     */
    public static function get(): ?string
    {
        if (function_exists('request') && request()) {
            $attributeDomain = request()->attributes->get('tenant_company_code');
            if (!empty($attributeDomain)) {
                return strtoupper(trim((string) $attributeDomain));
            }
        }

        if (function_exists('tenant') && tenant()) {
            $tenantKey = tenant()->getTenantKey();
            if (!empty($tenantKey)) {
                return strtoupper(trim((string) $tenantKey));
            }
        }

        if (!empty(static::$currentDomain)) {
            return static::$currentDomain;
        }

        return null;
    }

    /**
     * Check if an active company domain is set.
     */
    public static function has(): bool
    {
        return static::get() !== null;
    }

    /**
     * Require an active domain code, failing closed if missing.
     *
     * @throws \RuntimeException
     */
    public static function require(): string
    {
        $code = static::get();
        if ($code === null || $code === '') {
            throw new \RuntimeException('Accounting company context is required but none was provided or authorized.');
        }

        return $code;
    }

    /**
     * Get the current domain object or null if not set / not found.
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
     * Require the active domain object, failing closed if missing or not found.
     *
     * @throws \RuntimeException
     */
    public static function requireDomain(): LedgerDomain
    {
        $code = static::require();
        $domain = LedgerDomain::where('code', $code)->first();
        if (!$domain) {
            throw new \RuntimeException("Accounting domain '{$code}' could not be found.");
        }

        return $domain;
    }

    /**
     * Execute a callback within a specific domain context, resetting in finally.
     * 
     * @param string|null $domain
     * @param callable $callback
     * @return mixed
     */
    public static function scope(?string $domain, callable $callback): mixed
    {
        $previousDomain = static::$currentDomain;
        $previousRequestAttr = null;
        if (function_exists('request') && request()) {
            $previousRequestAttr = request()->attributes->get('tenant_company_code');
        }
        
        try {
            static::set($domain);
            return $callback();
        } finally {
            static::$currentDomain = $previousDomain;
            if (function_exists('request') && request()) {
                if ($previousRequestAttr !== null) {
                    request()->attributes->set('tenant_company_code', $previousRequestAttr);
                } else {
                    request()->attributes->remove('tenant_company_code');
                }
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
     * Reset domain context completely.
     */
    public static function reset(): void
    {
        static::$currentDomain = null;
        if (function_exists('request') && request()) {
            request()->attributes->remove('tenant_company_code');
        }
    }
}
