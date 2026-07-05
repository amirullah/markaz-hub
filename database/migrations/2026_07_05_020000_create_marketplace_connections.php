<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koneksi API marketplace per-toko (Shopee dulu; TikTok/Tokopedia menyusul).
 * Token disimpan TERENKRIPSI (cast 'encrypted' di model) — jangan pernah polos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 20)->default('SHOPEE'); // SHOPEE | TIKTOKTOKO (nanti)
            $t->unsignedBigInteger('shop_id')->nullable();  // shop_id dari Shopee
            $t->text('access_token')->nullable();           // terenkripsi via cast model
            $t->text('refresh_token')->nullable();          // terenkripsi via cast model
            $t->timestamp('access_expires_at')->nullable();
            $t->timestamp('authorized_at')->nullable();
            $t->timestamp('last_synced_at')->nullable();    // watermark sinkron (update_time terakhir)
            $t->string('status', 20)->default('DISCONNECTED'); // CONNECTED | DISCONNECTED | ERROR
            $t->string('last_error', 500)->nullable();
            $t->timestamps();

            $t->unique(['store_id', 'platform']); // satu koneksi per toko per platform
            $t->index(['organization_id', 'platform']);
            $t->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connections');
    }
};
