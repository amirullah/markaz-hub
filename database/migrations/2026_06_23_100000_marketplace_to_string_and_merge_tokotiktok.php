<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM -> VARCHAR agar bisa menampung nilai gabungan 'TIKTOKTOKO'
        // (Tokopedia & TikTok = satu seller center) dan channel baru di masa depan.
        Schema::table('stores', function (Blueprint $table) {
            $table->string('marketplace', 20)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('marketplace', 20)->change();
        });

        // Gabungkan Tokopedia & TikTok menjadi satu channel.
        DB::table('stores')->whereIn('marketplace', ['TOKOPEDIA', 'TIKTOK'])->update(['marketplace' => 'TIKTOKTOKO']);
        DB::table('orders')->whereIn('marketplace', ['TOKOPEDIA', 'TIKTOK'])->update(['marketplace' => 'TIKTOKTOKO']);
    }

    public function down(): void
    {
        DB::table('stores')->where('marketplace', 'TIKTOKTOKO')->update(['marketplace' => 'TOKOPEDIA']);
        DB::table('orders')->where('marketplace', 'TIKTOKTOKO')->update(['marketplace' => 'TOKOPEDIA']);

        Schema::table('stores', function (Blueprint $table) {
            $table->enum('marketplace', ['SHOPEE', 'TOKOPEDIA', 'TIKTOK'])->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('marketplace', ['SHOPEE', 'TOKOPEDIA', 'TIKTOK'])->change();
        });
    }
};
