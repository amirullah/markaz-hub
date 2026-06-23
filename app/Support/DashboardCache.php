<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache angka dashboard PER ORGANISASI (isolasi tenant: key WAJIB mengandung
 * organization_id). TTL pendek + dibatalkan (forget) saat data berubah (impor /
 * isi estimasi) agar tak basi. Memangkas query agregasi yang berulang tiap buka dashboard.
 */
class DashboardCache
{
    /** Umur cache (detik). Pendek; lagipula dibatalkan saat impor/estimasi. */
    public const TTL = 600;

    /** Semua sub-kunci dashboard + kartu total Pesanan (untuk pembatalan menyeluruh saat impor). */
    private const KEYS = ['stats', 'laba_bulan', 'channel', 'laba_channel', 'top_produk', 'orders_subheading'];

    /** Ambil dari cache (per org pengguna login) atau hitung lalu simpan. */
    public static function remember(string $key, \Closure $callback): mixed
    {
        $org = (int) (auth()->user()?->organization_id ?? 0);

        return Cache::remember(self::key($org, $key), self::TTL, $callback);
    }

    /** Batalkan SEMUA cache dashboard milik satu organisasi (panggil saat data berubah). */
    public static function forget(int $organizationId): void
    {
        foreach (self::KEYS as $k) {
            Cache::forget(self::key($organizationId, $k));
        }
    }

    private static function key(int $org, string $key): string
    {
        return "dash:{$org}:{$key}";
    }
}
