<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Abivia\Ledger\Models\LedgerDomain;
use Carbon\Carbon;

class AccountingPeriod extends Model
{
    protected $table = 'accounting_periods';

    protected $fillable = [
        'domain_uuid',
        'fiscal_year',
        'period_number',
        'period_name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domain_uuid', 'domainUuid');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if a specific date falls within this period.
     */
    public function containsDate(string $date): bool
    {
        $d = Carbon::parse($date)->toDateString();
        return $d >= $this->start_date->toDateString() && $d <= $this->end_date->toDateString();
    }
}
