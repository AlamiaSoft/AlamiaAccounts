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
     * Mark record as committed with voucher reference, journal entry id, and response data.
     */
    public function markCommitted(string $voucherReference, int|string $journalEntryId, array $responseData = []): bool
    {
        return $this->update([
            'status' => 'committed',
            'voucher_reference' => $voucherReference,
            'journal_entry_id' => (int)$journalEntryId,
            'response_data' => $responseData,
        ]);
    }

    /**
     * Mark record as committed using optimistic lease fencing.
     * Throws IdempotencyLeaseFencingException if lease was preempted or lost.
     *
     * @throws \AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyLeaseFencingException
     */
    public function markCommittedFenced(string $voucherReference, int|string $journalEntryId, array $responseData = []): bool
    {
        $affected = static::whereKey($this->id)
            ->where('lease_token', $this->lease_token)
            ->where('status', 'in_flight')
            ->update([
                'status' => 'committed',
                'voucher_reference' => $voucherReference,
                'journal_entry_id' => (int)$journalEntryId,
                'response_data' => $responseData,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new \AlamiaSoft\AlamiaAccounts\Exceptions\IdempotencyLeaseFencingException(
                "Lease lost or preempted for idempotency record {$this->id} (token: {$this->lease_token}). Transaction aborted."
            );
        }

        $this->refresh();
        return true;
    }

    /**
     * Mark record as failed with an error message.
     * When a lease_token is present, fences the update on lease_token and in_flight status
     * so a rolled-back or preempted worker cannot overwrite another worker's lease or committed state.
     */
    public function markFailed(string $errorMessage): bool
    {
        if (!empty($this->lease_token)) {
            $affected = static::whereKey($this->id)
                ->where('lease_token', $this->lease_token)
                ->where('status', 'in_flight')
                ->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'updated_at' => now(),
                ]);

            if ($affected > 0) {
                $this->refresh();
                return true;
            }

            return false;
        }

        return $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
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
