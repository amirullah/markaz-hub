<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen biaya marketplace TAMBAHAN (selain komisi/biaya admin per kategori) agar
 * estimasi laba lebih akurat. Dari struktur biaya nyata 2026:
 *  - Shopee: selain Biaya Administrasi (komisi kategori) ada "Biaya Layanan" (~10%, ada batas).
 *  - Tokopedia/TikTok: selain Komisi platform (kategori) ada "Komisi Dinamis" (~6,5%).
 * Disimpan per-organisasi agar tiap seller bisa menyesuaikan (ikut program/tidak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'fee_shopee_service_pct')) {
                $table->decimal('fee_shopee_service_pct', 5, 2)->default(10.00)->after('uses_dropship');
            }
            if (! Schema::hasColumn('organizations', 'fee_shopee_service_cap')) {
                $table->unsignedInteger('fee_shopee_service_cap')->default(10000)->after('fee_shopee_service_pct');
            }
            if (! Schema::hasColumn('organizations', 'fee_tokotiktok_dynamic_pct')) {
                $table->decimal('fee_tokotiktok_dynamic_pct', 5, 2)->default(6.50)->after('fee_shopee_service_cap');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['fee_shopee_service_pct', 'fee_shopee_service_cap', 'fee_tokotiktok_dynamic_pct']);
        });
    }
};
