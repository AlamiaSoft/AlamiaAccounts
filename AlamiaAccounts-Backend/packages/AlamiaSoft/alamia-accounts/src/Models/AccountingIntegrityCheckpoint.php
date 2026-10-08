<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingIntegrityCheckpoint extends Model
{
    protected $table = 'accounting_integrity_checkpoints';

    protected $fillable = [
        'domain_uuid',
        'event_type',
        'voucher_reference',
        'voucher_type',
        'journal_entry_id',
        'as_of_date',
        'currency',
        'trial_balance_debit',
        'trial_balance_credit',
        'trial_balance_difference',
        'assets',
        'liabilities',
        'equity',
        'bs_difference',
        'invariants_passed',
        'transition_state',
        'violations',
        'metadata',
        'previous_checkpoint_id',
    ];

    protected $casts = [
        'as_of_date' => 'date',
        'trial_balance_debit' => 'float',
        'trial_balance_credit' => 'float',
        'trial_balance_difference' => 'float',
        'assets' => 'float',
        'liabilities' => 'float',
        'equity' => 'float',
        'bs_difference' => 'float',
        'invariants_passed' => 'boolean',
        'violations' => 'array',
        'metadata' => 'array',
    ];

    public function previousCheckpoint(): BelongsTo
    {
        return $this->belongsTo(AccountingIntegrityCheckpoint::class, 'previous_checkpoint_id');
    }
}
