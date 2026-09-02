<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherNumberSeries extends Model
{
    protected $fillable = [
        'voucher_type',
        'year',
        'month',
        'last_number',
    ];
        protected $casts = [
        'scope' => 'array',
        'last_reset_at' => 'datetime',
    ];

}
