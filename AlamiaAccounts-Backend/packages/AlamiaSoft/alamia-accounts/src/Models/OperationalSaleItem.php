<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalSaleItem extends Model
{
    protected $table = 'operational_sale_items';

    protected $fillable = [
        'operational_sale_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'revenue_account_code',
        'tax_rate_percent',
        'tax_amount',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax_rate_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(OperationalSale::class, 'operational_sale_id');
    }
}
