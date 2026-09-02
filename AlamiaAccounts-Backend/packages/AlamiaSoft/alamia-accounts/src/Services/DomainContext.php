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

    /**
     * Get the current domain code.
     *
     * @return string|null Current domain code or null if not set
     */
    public static function get(): ?string
    {
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
     * Reset to default domain.
     */
    public static function reset(): void
    {
        static::$currentDomain = null;
    }
}
