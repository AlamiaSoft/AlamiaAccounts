<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Abivia\Ledger\Models\LedgerDomain;

class OpeningBalanceBatch extends Model
{
    protected $table = 'opening_balance_batches';

    protected $fillable = [
        'domain_uuid',
        'reference',
        'balance_date',
        'total_debit',
        'total_credit',
        'balancing_account_code',
        'balancing_amount',
        'status',
        'created_by',
    ];

    protected $casts = [
        'balance_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'balancing_amount' => 'decimal:2',
    ];

    public function domain()
    {
        return $this->belongsTo(LedgerDomain::class, 'domain_uuid', 'domainUuid');
    }
}
