<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerDomain;

/**
 * Pivot model for domain-journal entry associations.
 * 
 * Business Rule: One journal entry belongs to ONE domain only.
 * This table exists for performance - allows direct domain → entries queries.
 */
class DomainJournalEntry extends Model
{
    protected $fillable = ['domainUuid', 'journalEntryId'];

    /**
     * Get the domain that owns this association.
     */
    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domainUuid', 'domainUuid');
    }

    /**
     * Get the journal entry in this association.
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journalEntryId', 'journalEntryId');
    }

    /**
     * Get all journal entry IDs for a given domain.
     */
    public static function getEntryIdsForDomain(string $domainUuid): array
    {
        return static::where('domainUuid', $domainUuid)
            ->pluck('journalEntryId')
            ->toArray();
    }

    /**
     * Get domain UUID for a given journal entry.
     */
    public static function getDomainForEntry(int $journalEntryId): ?string
    {
        return static::where('journalEntryId', $journalEntryId)
            ->value('domainUuid');
    }

    /**
     * Check if a journal entry belongs to a domain.
     */
    public static function entryBelongsToDomain(int $journalEntryId, string $domainUuid): bool
    {
        return static::where('journalEntryId', $journalEntryId)
            ->where('domainUuid', $domainUuid)
            ->exists();
    }
}
