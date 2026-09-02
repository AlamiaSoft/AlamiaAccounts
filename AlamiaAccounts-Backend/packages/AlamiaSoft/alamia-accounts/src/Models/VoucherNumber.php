<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherNumber extends Model
{
    protected $fillable = [
        'entry_uuid','voucher_type','voucher_no','context',
        'external_source','external_number',
    ];
}
