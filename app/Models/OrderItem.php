<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToOrganization;

    public $timestamps = false; // ikut skema v1; item dibangun ulang saat import

    protected $fillable = [
        'organization_id', 'order_id', 'product_id', 'sku', 'name', 'qty',
        'qty_assumed', 'unit_price', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'qty_assumed' => 'boolean',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
