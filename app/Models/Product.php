<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\CategoryClassifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected static function booted(): void
    {
        // Setiap produk WAJIB punya kategori: bila kosong, sistem memilih otomatis
        // dari nama produk. User tetap bisa mengubahnya.
        static::saving(function (Product $product): void {
            if (empty($product->category_id) && ! empty($product->name)) {
                $orgId = (int) ($product->organization_id ?: (auth()->user()->organization_id ?? 0));
                if ($orgId > 0) {
                    $product->category_id = app(CategoryClassifier::class)->categoryIdFor((string) $product->name, $orgId);
                }
            }
        });
    }

    // organization_id sengaja TIDAK fillable — diisi otomatis oleh BelongsToOrganization.
    protected $fillable = [
        'sku', 'name', 'cost_price', 'dropship_cost', 'supplier_id', 'category_id', 'active',
        'stock', 'min_stock',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('produk');
    }

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'dropship_cost' => 'decimal:2',
            'cost_changed_at' => 'datetime',
            'active' => 'boolean',
            'stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Stok menipis (di bawah minimum). */
    public function scopeStockMenipis($query): void
    {
        $query->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock');
    }

    /** Stok habis. */
    public function scopeStockHabis($query): void
    {
        $query->where('stock', '<=', 0);
    }
}
