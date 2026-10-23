<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerDomain;

/**
 * Pivot model for domain-journal entry associations.
 * 
 * Business Rule:
 * - One journal entry belongs to ONE domain only (`unique('journalEntryId')`).
 * - Within a company domain (`domainUuid`), `reference` must be non-empty and unique
 *   case-insensitively (`UNIQUE(domainUuid, reference)` + `UNIQUE(domainUuid, UPPER(reference))`).
 * - Different company domains may independently use the same voucher reference string.
 */
class DomainJournalEntry extends Model
{
    protected $fillable = ['domainUuid', 'journalEntryId', 'reference'];

    protected static function booted(): void
    {
        static::saving(function (self $entry) {
            if ($entry->reference !== null) {
                $entry->reference = trim((string) $entry->reference);
            }
        });
    }

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

    /**
     * Check whether a voucher reference already exists within the specified domain (case-insensitive).
     */
    public static function referenceExistsInDomain(string $domainUuid, string $reference): bool
    {
        $normalized = strtoupper(trim($reference));
        if ($normalized === '') {
            return false;
        }

        return static::where('domainUuid', $domainUuid)
            ->whereNotNull('reference')
            ->whereRaw('UPPER(TRIM(reference)) = ?', [$normalized])
            ->exists();
    }

    /**
     * Find journalEntryId values within a domain matching a reference (exact or substring, case-insensitive).
     */
    public static function findEntryIdsByReference(string $domainUuid, string $reference, bool $exact = false): array
    {
        $normalized = strtoupper(trim($reference));
        if ($normalized === '') {
            return [];
        }

        $query = static::where('domainUuid', $domainUuid)->whereNotNull('reference');

        if ($exact) {
            $query->whereRaw('UPPER(TRIM(reference)) = ?', [$normalized]);
        } else {
            $query->whereRaw('UPPER(reference) LIKE ?', ['%' . $normalized . '%']);
        }

        return $query->pluck('journalEntryId')->toArray();
    }

    /**
     * Deterministically backfill NULL/blank references and disambiguate any historical
     * duplicate `(domainUuid, UPPER(TRIM(reference)))` rows so database-level unique
     * constraints can be enforced without data loss.
     *
     * Rules:
     * 1. If `reference` is NULL or whitespace-only, inspect `journal_entries.extra` JSON
     *    (`reference` or `voucher_number`) or raw string; if still empty, assign `'LEGACY-' . $journalEntryId`.
     * 2. Within each `domainUuid` (ordered deterministically by `journalEntryId ASC, id ASC`),
     *    the first row claiming `UPPER(TRIM(reference))` keeps its canonical reference; any
     *    subsequent colliding row is disambiguated as `"{$baseRef}-DUP-{$journalEntryId}"`.
     *
     * @param string|null $domainUuid Optional domain filter
     * @return array{backfilled_count: int, deduplicated_count: int, updated_entry_ids: array<int>}
     */
    public static function reconcileAndBackfillReferences(?string $domainUuid = null): array
    {
        $query = DB::table('domain_journal_entries')
            ->orderBy('domainUuid')
            ->orderBy('journalEntryId')
            ->orderBy('id');

        if ($domainUuid !== null) {
            $query->where('domainUuid', $domainUuid);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [
                'backfilled_count' => 0,
                'deduplicated_count' => 0,
                'updated_entry_ids' => [],
            ];
        }

        $entryIds = $rows->pluck('journalEntryId')->unique()->values()->all();
        $extrasByEntryId = DB::table('journal_entries')
            ->whereIn('journalEntryId', $entryIds)
            ->pluck('extra', 'journalEntryId');

        $seenPerDomain = [];
        $backfilledCount = 0;
        $deduplicatedCount = 0;
        $updatedEntryIds = [];

        foreach ($rows as $row) {
            $dUuid = (string) $row->domainUuid;
            if (!isset($seenPerDomain[$dUuid])) {
                $seenPerDomain[$dUuid] = [];
            }

            $currentRef = trim((string) ($row->reference ?? ''));
            $wasBackfilled = false;

            if ($currentRef === '') {
                $extra = $extrasByEntryId[$row->journalEntryId] ?? null;
                if (is_string($extra) && trim($extra) !== '') {
                    $trimmedExtra = trim($extra);
                    if (str_starts_with($trimmedExtra, '{')) {
                        $decoded = json_decode($trimmedExtra, true);
                        if (is_array($decoded)) {
                            $currentRef = trim((string) ($decoded['reference'] ?? $decoded['voucher_number'] ?? ''));
                        }
                    } else {
                        $currentRef = $trimmedExtra;
                    }
                }

                if ($currentRef === '') {
                    $currentRef = 'LEGACY-' . $row->journalEntryId;
                }
                $wasBackfilled = true;
            }

            $normalizedKey = strtoupper($currentRef);
            $finalRef = $currentRef;
            $wasDeduplicated = false;

            if (isset($seenPerDomain[$dUuid][$normalizedKey])) {
                $candidate = "{$currentRef}-DUP-{$row->journalEntryId}";
                $suffixCounter = 1;
                while (isset($seenPerDomain[$dUuid][strtoupper($candidate)])) {
                    $suffixCounter++;
                    $candidate = "{$currentRef}-DUP-{$row->journalEntryId}-{$suffixCounter}";
                }
                $finalRef = $candidate;
                $normalizedKey = strtoupper($finalRef);
                $wasDeduplicated = true;
            }

            $seenPerDomain[$dUuid][$normalizedKey] = true;

            if ($wasBackfilled || $wasDeduplicated || $finalRef !== (string) ($row->reference ?? '')) {
                DB::table('domain_journal_entries')
                    ->where('id', $row->id)
                    ->update(['reference' => $finalRef]);

                if ($wasDeduplicated) {
                    $extra = $extrasByEntryId[$row->journalEntryId] ?? null;
                    if (is_string($extra) && str_starts_with(trim($extra), '{')) {
                        $decoded = json_decode(trim($extra), true);
                        if (is_array($decoded)) {
                            $decoded['legacy_duplicate_reference'] = $currentRef;
                            $decoded['reference'] = $finalRef;
                            DB::table('journal_entries')
                                ->where('journalEntryId', $row->journalEntryId)
                                ->update(['extra' => json_encode($decoded)]);
                        }
                    } elseif (is_string($extra) && trim($extra) !== '') {
                        DB::table('journal_entries')
                            ->where('journalEntryId', $row->journalEntryId)
                            ->update(['extra' => $finalRef]);
                    }
                }

                if ($wasBackfilled) {
                    $backfilledCount++;
                }
                if ($wasDeduplicated) {
                    $deduplicatedCount++;
                }
                $updatedEntryIds[] = (int) $row->journalEntryId;
            }
        }

        return [
            'backfilled_count' => $backfilledCount,
            'deduplicated_count' => $deduplicatedCount,
            'updated_entry_ids' => $updatedEntryIds,
        ];
    }
}

