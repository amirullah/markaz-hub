<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('processing_status', 20)->default('PENDING')->after('status');
            $table->dateTime('shipped_at')->nullable()->after('processing_status');
            $table->string('tracking_number', 100)->nullable()->after('shipped_at');
            $table->string('courier', 100)->nullable()->after('tracking_number');
            $table->index(['organization_id', 'processing_status'], 'orders_org_proc_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_org_proc_idx');
            $table->dropColumn(['processing_status', 'tracking_number', 'courier', 'shipped_at']);
        });
    }
};
