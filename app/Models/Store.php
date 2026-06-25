<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Store extends Model
{
    use BelongsToOrganization, LogsActivity;

    // organization_id sengaja TIDAK fillable — diisi otomatis oleh BelongsToOrganization.
    protected $fillable = ['name', 'marketplace', 'fulfillment_mode', 'active', 'note'];

    /** Mode pemenuhan toko → pemenuhan pesanan yang diharapkan. */
    public const MODES = [
        'both' => 'Keduanya (dropship & packing sendiri)',
        'dropship' => 'Dropship saja',
        'self' => 'Packing sendiri saja',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('toko');
    }

    // Saat toko berubah/terhapus, segarkan cache dashboard (mis. kartu "Pesanan Janggal").
    protected static function booted(): void
    {
        $forget = fn (Store $store) => \App\Support\DashboardCache::forget((int) $store->organization_id);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Tebak mode dari riwayat pesanan: 'dropship'/'self' jika ≥90% satu jenis
     * (min. 10 pesanan), selain itu 'both'. Dipakai aksi "Tandai Mode Otomatis".
     */
    public static function detectModeFor(int $storeId): string
    {
        $orgId = (int) \Illuminate\Support\Facades\DB::table('stores')->where('id', $storeId)->value('organization_id');
        $a = \Illuminate\Support\Facades\DB::table('orders')
            ->where('store_id', $storeId)->where('organization_id', $orgId) // pertahanan isolasi tenant
            ->selectRaw("COUNT(*) total, SUM(fulfillment='DROPSHIP') d, SUM(fulfillment='SELF') s")->first();
        $total = (int) ($a->total ?? 0);
        if ($total < 10) {
            return 'both';
        }
        if ((int) $a->d / $total >= 0.9) {
            return 'dropship';
        }
        if ((int) $a->s / $total >= 0.9) {
            return 'self';
        }

        return 'both';
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
