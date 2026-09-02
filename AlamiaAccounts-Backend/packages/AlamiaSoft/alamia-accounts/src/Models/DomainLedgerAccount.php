<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;

/**
 * Pivot model for domain-account associations.
 * 
 * Business Rule: One account belongs to ONE domain only.
 */
class DomainLedgerAccount extends Model
{
    protected $fillable = ['domainUuid', 'ledgerUuid'];

    /**
     * Get the domain that owns this association.
     */
    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domainUuid', 'domainUuid');
    }

    /**
     * Get the ledger account in this association.
     */
    public function account()
    {
        return $this->belongsTo(LedgerAccount::class, 'ledgerUuid', 'ledgerUuid');
    }

    /**
     * Get all account UUIDs for a given domain.
     */
    public static function getAccountUuidsForDomain(string $domainUuid): array
    {
        return static::where('domainUuid', $domainUuid)
            ->pluck('ledgerUuid')
            ->toArray();
    }

    /**
     * Get domain UUID for a given account.
     */
    public static function getDomainForAccount(string $ledgerUuid): ?string
    {
        return static::where('ledgerUuid', $ledgerUuid)
            ->value('domainUuid');
    }

    /**
     * Check if an account belongs to a domain.
     */
    public static function accountBelongsToDomain(string $ledgerUuid, string $domainUuid): bool
    {
        return static::where('ledgerUuid', $ledgerUuid)
            ->where('domainUuid', $domainUuid)
            ->exists();
    }
}
