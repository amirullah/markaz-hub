<?php

namespace App\Models;

use App\Services\DefaultCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant: satu Organization = satu seller. Data bisnis terikat ke organisasi ini.
 */
class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'active', 'uses_dropship'];

    protected static function booted(): void
    {
        // Setiap organisasi baru langsung dapat kategori default (+ tarif resmi),
        // agar fitur kategori & estimasi biaya admin siap pakai untuk SEMUA seller.
        static::created(function (Organization $org): void {
            app(DefaultCategories::class)->seedForOrg((int) $org->id);
        });
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'uses_dropship' => 'boolean'];
    }

    /**
     * Apakah org user saat ini memakai Dropship (dropship)? Dipakai untuk menyembunyikan
     * UI dropship bila seller tidak pakai Dropship. Default true (aman bila kolom belum ada).
     */
    public static function currentUsesDropship(): bool
    {
        try {
            return (bool) (auth()->user()?->organization?->uses_dropship ?? true);
        } catch (\Throwable $e) {
            // Tanpa konteks auth (mis. unit test murni) → default Dropship aktif (perilaku v1).
            return true;
        }
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
