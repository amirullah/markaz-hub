<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\ProfitService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use BelongsToOrganization, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'fulfillment', 'product_revenue', 'cogs', 'admin_fee', 'dropship_cost', 'note'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('pesanan');
    }

    // organization_id sengaja TIDAK fillable — diisi otomatis oleh BelongsToOrganization.
    protected $fillable = [
        'store_id', 'external_no', 'marketplace', 'status', 'fulfillment',
        'order_date', 'buyer_name', 'product_revenue', 'shipping_charged_to_buyer', 'other_income',
        'cogs', 'admin_fee', 'shipping_cost_seller', 'voucher_seller_borne', 'dropship_cost', 'dropship_modal',
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
            'dropship_modal' => 'decimal:2',
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

    /**
     * Daftar hal yang BELUM lengkap pada pesanan (untuk indikator/keterangan).
     * Kosong = lengkap. Pesanan batal dianggap lengkap (tak perlu data). Memakai relasi
     * items — eager-load di tabel agar tak N+1.
     */
    public function incompleteness(): array
    {
        if ($this->status === 'CANCELLED') {
            return [];
        }
        $gaps = [];

        if ($this->items->count() === 0) {
            $gaps[] = 'Belum ada rincian item produk (impor File/Laporan Pesanan)';
        }

        if (! $this->income_verified) {
            $gaps[] = 'Biaya masih ESTIMASI — Laporan Penghasilan belum diimpor';
        }

        if ($this->fulfillment === 'SELF' && (float) $this->product_revenue > 0 && (float) $this->cogs <= 0) {
            $gaps[] = 'HPP/modal belum ada — produk belum dikenal/diimpor (Daftar Produk)';
        }

        if ($this->fulfillment === 'DROPSHIP' && (float) $this->dropship_cost <= 0) {
            $gaps[] = 'Biaya dropship belum ada (impor Dropship)';
        }

        return $gaps;
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
