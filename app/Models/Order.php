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
            ->logOnly(['status', 'fulfillment', 'product_revenue', 'cogs', 'admin_fee', 'dropship_cost', 'note', 'processing_status', 'failed_reason'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('pesanan');
    }

    // organization_id sengaja TIDAK fillable — diisi otomatis oleh BelongsToOrganization.
    protected $fillable = [
        'store_id', 'external_no', 'marketplace', 'status', 'fulfillment',
        'order_date', 'buyer_name', 'product_revenue', 'shipping_charged_to_buyer', 'other_income',
        'cogs', 'admin_fee', 'shipping_cost_seller', 'voucher_seller_borne', 'dropship_cost', 'dropship_modal',
        'other_cost', 'income_verified', 'note',
        'processing_status', 'tracking_number', 'courier', 'shipped_at', 'failed_reason',
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
            'shipped_at' => 'datetime',
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
     * Hal yang membuat LABA belum akurat/pasti (untuk Status Laba). Kosong = laba bisa
     * dihitung pasti. Pesanan batal dianggap lengkap. CATATAN: "tidak ada rincian item"
     * TIDAK termasuk di sini — untuk dropship laba dihitung dari biaya dropship (tak perlu
     * item), dan untuk packing-sendiri konsekuensinya sudah muncul sebagai "HPP belum ada".
     */
    public function incompleteness(): array
    {
        if (in_array($this->status, ['CANCELLED', 'RETURNED'], true)) {
            return []; // terminal: cogs/biaya 0 memang disengaja, bukan "kurang"
        }
        $gaps = [];

        if (! $this->income_verified) {
            $gaps[] = 'Biaya masih ESTIMASI — Laporan Penghasilan belum diimpor';
        }

        if ($this->fulfillment === 'SELF' && (float) $this->product_revenue > 0 && (float) $this->cogs <= 0) {
            $gaps[] = 'HPP/modal belum ada — produk belum dikenal/diimpor (Daftar Produk)';
        }

        if ($this->fulfillment === 'DROPSHIP' && (float) $this->dropship_cost <= 0) {
            $gaps[] = 'Biaya dropship belum ada (impor Dropship)';
        }

        // Settlement (uang bersih marketplace) belum cair: laporan MEMUAT pesanan ini tapi
        // penghasilannya masih 0 (mis. baru selesai, dana TikTok/Tokopedia ditahan dulu) →
        // laba belum final sampai dana keluar (impor ulang Laporan Penghasilan nanti).
        if ($this->income_verified && (float) $this->product_revenue > 0
            && app(ProfitService::class)->net($this) <= 0) {
            $gaps[] = 'Settlement belum cair dari marketplace — impor ulang Laporan Penghasilan setelah dana keluar';
        }

        return $gaps;
    }

    /** Rincian item produk belum tercatat (catatan, BUKAN penentu laba). */
    public function lacksItemDetail(): bool
    {
        return ! in_array($this->status, ['CANCELLED', 'RETURNED'], true) && $this->items->count() === 0;
    }

    /**
     * Pesanan yang LABANYA BELUM FINAL/akurat (SQL — cermin incompleteness()):
     * biaya masih estimasi, ATAU modal/HPP belum ada, ATAU biaya dropship belum ada.
     * Batal & retur dikecualikan (terminal). Dipakai metrik "Laba Belum Final".
     */
    public function scopeLabaBelumFinal($query)
    {
        return $query->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->where(function ($q) {
                $q->where('income_verified', false)
                    ->orWhere(fn ($q) => $q->where('fulfillment', 'SELF')->where('product_revenue', '>', 0)->where('cogs', '<=', 0))
                    ->orWhere(fn ($q) => $q->where('fulfillment', 'DROPSHIP')->where('dropship_cost', '<=', 0))
                    // Selesai/verified tapi settlement belum cair (net <= 0).
                    ->orWhere(fn ($q) => $q->where('income_verified', true)->where('product_revenue', '>', 0)->whereRaw(ProfitService::SQL_NET . ' <= 0'));
            });
    }

    /**
     * "Laba semu" (HPP kosong): pesanan SELF beromzet tapi modal/HPP masih 0 →
     * laba terlihat besar padahal belum nyata. SUMBER TUNGGAL (dipakai kartu, Insight, filter).
     */
    public function scopeLabaSemu($query)
    {
        return $query->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->where('fulfillment', 'SELF')->where('product_revenue', '>', 0)->where('cogs', '<=', 0);
    }

    /**
     * Pesanan JANGGAL (tak sinkron): biaya dropship-nya SUDAH ada (external_no
     * tercatat di dropship_costs) TAPI pesanannya belum jadi dropship — persis
     * kasus "data dropship sudah diupload tapi masih ada pesanan packing sendiri".
     * Sinyal PRESISI (datanya sendiri yang bilang harusnya dropship), tanpa salah-tuduh.
     */
    public function scopeJanggal($query)
    {
        // Batal/retur dikecualikan: pesanan terminal tak berdampak laba & catatan dropship-nya
        // bisa "basi" (dulu dropship, kini self) — bukan anomali yang perlu ditindak.
        return $query->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->where('fulfillment', '!=', 'DROPSHIP')
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('dropship_costs as dc')
                    ->whereColumn('dc.external_no', 'orders.external_no')
                    ->whereColumn('dc.organization_id', 'orders.organization_id');
            });
    }

    /**
     * "Jual di bawah modal": harga jual produk (product_revenue) lebih KECIL dari modal
     * — HPP (packing sendiri) atau biaya dropship (dropship, ikut toggle). Pertanda harga
     * keliru, salah supplier, atau biaya dropship salah input. Batal/retur & pesanan tanpa
     * omzet (belum cair) dikecualikan. SUMBER TUNGGAL (kartu Dashboard + filter Pesanan).
     */
    public function scopeBawahModal($query)
    {
        return $query->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->where('product_revenue', '>', 0)
            ->whereRaw('(cogs + ' . ProfitService::dropshipExpr() . ') > product_revenue');
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

    /** Scope: pesanan yang perlu diproses (belum dikirim dan tidak gagal). */
    public function scopePerluDiproses($query)
    {
        return $query->whereIn('processing_status', ['PENDING', 'PROCESSING', 'PACKED'])
            ->whereNotIn('status', ['CANCELLED', 'RETURNED']);
    }

    /** Scope: pesanan sudah dikirim. */
    public function scopeSudahDikirim($query)
    {
        return $query->where('processing_status', 'SHIPPED');
    }

    public static function processingStatusLabel(?string $s): string
    {
        return match ($s) {
            'PENDING' => 'Baru',
            'PROCESSING' => 'Diproses',
            'PACKED' => 'Dikemas',
            'SHIPPED' => 'Dikirim',
            'FAILED' => 'Gagal',
            default => '—',
        };
    }

    public static function processingStatusColor(?string $s): string
    {
        return match ($s) {
            'PENDING' => 'warning',
            'PROCESSING' => 'info',
            'PACKED' => 'primary',
            'SHIPPED' => 'success',
            'FAILED' => 'danger',
            default => 'gray',
        };
    }
}
