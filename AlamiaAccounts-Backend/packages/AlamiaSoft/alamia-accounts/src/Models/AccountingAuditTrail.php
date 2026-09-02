<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Abivia\Ledger\Models\LedgerDomain;

class AccountingAuditTrail extends Model
{
    protected $table = 'accounting_audit_trails';
    public $timestamps = false; // Only created_at column

    protected $fillable = [
        'domain_uuid',
        'user_id',
        'user_name',
        'action',
        'entity_type',
        'entity_reference',
        'details',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domain_uuid', 'domainUuid');
    }

    /**
     * Log an accounting audit event
     */
    public static function record(
        string $domainUuid,
        string $action,
        string $entityType,
        ?string $entityReference = null,
        ?array $details = null,
        ?string $userId = null,
        ?string $userName = null
    ): self {
        return static::create([
            'domain_uuid' => $domainUuid,
            'user_id' => $userId ?? (auth()->check() ? (string)auth()->id() : 'system'),
            'user_name' => $userName ?? (auth()->check() ? auth()->user()->name : 'Accountant / System'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_reference' => $entityReference,
            'details' => $details,
            'ip_address' => request() ? request()->ip() : null,
            'created_at' => now(),
        ]);
    }
}
