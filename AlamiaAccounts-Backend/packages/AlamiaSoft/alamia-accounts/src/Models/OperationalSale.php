<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalSale extends Model
{
    protected $table = 'operational_sales';

    protected $fillable = [
        'company_code',
        'client_reference_id',
        'idempotency_key',
        'created_by_user_id',
        'created_by_user_name',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_cnic_or_ntn',
        'customer_subledger_code',
        'issue_date',
        'due_date',
        'status',
        'workflow_mode',
        'gross_amount',
        'tax_amount',
        'discount_amount',
        'net_amount',
        'paid_amount',
        'balance_due',
        'sales_voucher_ref',
        'receipt_voucher_ref',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'gross_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OperationalSaleItem::class, 'operational_sale_id');
    }
}
