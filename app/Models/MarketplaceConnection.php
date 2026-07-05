<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koneksi API marketplace milik satu toko (Shopee dulu).
 * Token akses/refresh TERENKRIPSI di kolom (cast 'encrypted').
 */
class MarketplaceConnection extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'store_id', 'platform', 'shop_id', 'access_token', 'refresh_token',
        'access_expires_at', 'authorized_at', 'last_synced_at', 'status', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'CONNECTED' && $this->shop_id && $this->access_token;
    }

    /** Token akses hampir kedaluwarsa (perlu refresh sebelum dipakai)? */
    public function tokenStale(): bool
    {
        return ! $this->access_expires_at || now()->addMinutes(5)->gte($this->access_expires_at);
    }
}
