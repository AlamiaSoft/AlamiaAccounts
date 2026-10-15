<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Abivia\Ledger\Models\LedgerDomain;

class UnpostedIntegrationEntry extends Model
{
    protected $table = 'unposted_integration_entries';

    protected $fillable = [
        'domain_uuid',
        'company_code',
        'source_module',
        'source_reference',
        'idempotency_key',
        'event_type',
        'payload',
        'error_message',
        'status',
        'retry_count',
        'last_attempted_at',
        'resolved_at',
        'resolved_voucher_ref',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'last_attempted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domain_uuid', 'domainUuid');
    }

    /**
     * Record or update a failed integration attempt.
     */
    public static function recordFailure(
        string $domainUuid,
        string $companyCode,
        string $sourceModule,
        string $sourceReference,
        string $idempotencyKey,
        string $eventType,
        array $payload,
        string $errorMessage
    ): self {
        $existing = static::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            $existing->update([
                'domain_uuid' => $domainUuid,
                'company_code' => $companyCode,
                'source_module' => $sourceModule,
                'source_reference' => $sourceReference,
                'event_type' => $eventType,
                'payload' => $payload,
                'error_message' => $errorMessage,
                'status' => 'failed',
                'retry_count' => $existing->retry_count + 1,
                'last_attempted_at' => now(),
            ]);
            return $existing;
        }

        return static::create([
            'idempotency_key' => $idempotencyKey,
            'domain_uuid' => $domainUuid,
            'company_code' => $companyCode,
            'source_module' => $sourceModule,
            'source_reference' => $sourceReference,
            'event_type' => $eventType,
            'payload' => $payload,
            'error_message' => $errorMessage,
            'status' => 'failed',
            'retry_count' => 1,
            'last_attempted_at' => now(),
        ]);
    }

    /**
     * Mark an entry as successfully resolved.
     */
    public function markResolved(string $voucherRef): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_voucher_ref' => $voucherRef,
            'resolved_at' => now(),
            'error_message' => null,
        ]);
    }
}
