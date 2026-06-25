<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Mode pemenuhan toko: 'both' (default) | 'dropship' (hanya dropship) | 'self' (hanya packing sendiri).
            // Dipakai untuk menandai pesanan JANGGAL yang pemenuhannya bertentangan dgn mode toko.
            $table->string('fulfillment_mode', 16)->default('both')->after('marketplace');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('fulfillment_mode');
        });
    }
};
