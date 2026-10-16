<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerDomain;

class PostingIdempotencyRecord extends Model
{
    protected $table = 'posting_idempotency_records';

    protected $fillable = [
        'domain_uuid',
        'company_code',
        'idempotency_key',
        'source_module',
        'source_transaction_id',
        'operation',
        'version',
        'payload_hash',
        'request_payload',
        'status',
        'lease_token',
        'lease_generation',
        'lease_expires_at',
        'voucher_reference',
        'journal_entry_id',
        'response_data',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'lease_generation' => 'integer',
        'lease_expires_at' => 'datetime',
        'journal_entry_id' => 'integer',
    ];

    /**
     * Scope query to in-flight records.
     */
    public function scopeInFlight(Builder $query): Builder
    {
        return $query->where('status', 'in_flight');
    }

    /**
     * Scope query to committed records.
     */
    public function scopeCommitted(Builder $query): Builder
    {
        return $query->where('status', 'committed');
    }

    /**
     * Scope query to failed records.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope query to a specific domain UUID.
     */
    public function scopeForDomain(Builder $query, string $domainUuid): Builder
    {
        return $query->where('domain_uuid', $domainUuid);
    }

    /**
     * Check if the lease has expired.
     */
    public function isLeaseExpired(): bool
    {
        if (!$this->lease_expires_at) {
            return false;
        }
        return $this->lease_expires_at->isPast();
    }

    /**
     * Check if record is currently in-flight.
     */
    public function isInFlight(): bool
    {
        return $this->status === 'in_flight';
    }

    /**
     * Check if record has committed.
     */
    public function isCommitted(): bool
    {
        return $this->status === 'committed';
    }

    /**
     * Check if record has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Related Abivia JournalEntry.
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id', 'journalEntryId');
    }

    /**
     * Related Abivia LedgerDomain.
     */
    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domain_uuid', 'domainUuid');
    }
}
