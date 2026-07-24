<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan: menambahkan kolom yang gagal di migration sebelumnya
 * (tracking_number, courier, shipped_at — error karena urutan after()).
 */
return new class extends Migration {
    public function up(): void
    {
        $hasShipped = Schema::hasColumn('orders', 'shipped_at');
        $hasTracking = Schema::hasColumn('orders', 'tracking_number');

        if (! $hasShipped) {
            Schema::table('orders', function ($table) {
                $table->dateTime('shipped_at')->nullable()->after('processing_status');
            });
        }
        if (! $hasTracking) {
            Schema::table('orders', function ($table) {
                $table->string('tracking_number', 100)->nullable()->after('shipped_at');
            });
        }
        if (! Schema::hasColumn('orders', 'courier')) {
            Schema::table('orders', function ($table) {
                $table->string('courier', 100)->nullable()->after('tracking_number');
            });
        }

        // Index mungkin sudah ada, pakai raw SQL untuk menghindari error.
        try {
            DB::statement('ALTER TABLE orders ADD INDEX orders_org_proc_idx (organization_id, processing_status)');
        } catch (\Throwable $e) {
            // Index sudah ada — abaikan.
        }
    }

    public function down(): void
    {
        // Tidak perlu rollback — biarkan kolom yang sudah ada.
    }
};
