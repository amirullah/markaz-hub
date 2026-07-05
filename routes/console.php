<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Sinkron manual semua koneksi Shopee (bisa dipanggil langsung: php artisan shopee:sync).
 */
Artisan::command('shopee:sync {--catalog : ikut sinkron katalog produk}', function () {
    $conns = \App\Models\MarketplaceConnection::withoutGlobalScopes()
        ->where('platform', 'SHOPEE')->where('status', 'CONNECTED')->get();
    if ($conns->isEmpty()) {
        $this->info('Belum ada toko yang terhubung ke Shopee.');

        return;
    }
    $sync = app(\App\Services\Shopee\ShopeeSync::class);
    foreach ($conns as $c) {
        try {
            $r = $sync->syncStore($c);
            $this->info("Toko #{$c->store_id}: {$r['message']}");
            $e = $sync->retryPendingEscrow($c);
            if ($e['settled'] > 0) {
                $this->info("Toko #{$c->store_id}: {$e['settled']} settlement susulan cair.");
            }
            if ($this->option('catalog')) {
                $k = $sync->syncCatalog($c);
                $this->info("Toko #{$c->store_id}: katalog {$k['products']} produk.");
            }
        } catch (\Throwable $ex) {
            report($ex);
            $c->forceFill(['status' => 'ERROR', 'last_error' => mb_substr($ex->getMessage(), 0, 500)])->save();
            $this->error("Toko #{$c->store_id}: {$ex->getMessage()}");
        }
    }
})->purpose('Sinkron pesanan+settlement Shopee untuk semua toko terhubung');

/*
 * Reconciliation terjadwal (jaring pengaman push realtime + settlement susulan).
 * Aktif di produksi via cron Hostinger: jalankan `php artisan schedule:run` tiap menit.
 * Token Shopee juga ikut segar karena tiap panggilan me-refresh bila hampir kedaluwarsa
 * (refresh_token hangus bila >30 hari tak dipakai — jadwal ini mencegahnya).
 */
Schedule::command('shopee:sync')->everyFourHours()->withoutOverlapping();
Schedule::command('shopee:sync --catalog')->dailyAt('03:10')->withoutOverlapping();
