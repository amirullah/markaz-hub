<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'sku', 'name', 'cost_price', 'dropship_cost', 'supplier_id', 'active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'dropship_cost' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
