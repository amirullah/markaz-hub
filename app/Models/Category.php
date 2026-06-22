<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToOrganization;

    // organization_id otomatis diisi trait (tidak fillable).
    protected $fillable = ['name', 'fee_shopee', 'fee_tokotiktok'];

    protected function casts(): array
    {
        return [
            'fee_shopee' => 'decimal:2',
            'fee_tokotiktok' => 'decimal:2',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Tarif % biaya admin untuk channel pesanan (SHOPEE vs TOKOPEDIA/TIKTOK). */
    public function feeForMarketplace(string $marketplace): float
    {
        return $marketplace === 'SHOPEE'
            ? (float) $this->fee_shopee
            : (float) $this->fee_tokotiktok;
    }
}
