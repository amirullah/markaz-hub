<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\ProfitService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'store_id', 'external_no', 'marketplace', 'status', 'fulfillment',
        'order_date', 'buyer_name', 'product_revenue', 'shipping_charged_to_buyer', 'other_income',
        'cogs', 'admin_fee', 'shipping_cost_seller', 'voucher_seller_borne', 'dropship_cost',
        'other_cost', 'income_verified', 'note',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'income_verified' => 'boolean',
            'product_revenue' => 'decimal:2',
            'shipping_charged_to_buyer' => 'decimal:2',
            'other_income' => 'decimal:2',
            'cogs' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'shipping_cost_seller' => 'decimal:2',
            'voucher_seller_borne' => 'decimal:2',
            'dropship_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Laba bersih (pakai sumber kebenaran tunggal ProfitService). */
    public function getProfitAttribute(): float
    {
        return app(ProfitService::class)->profit($this);
    }

    /** Uang bersih marketplace (sebelum modal). */
    public function getNetAttribute(): float
    {
        return app(ProfitService::class)->net($this);
    }
}
