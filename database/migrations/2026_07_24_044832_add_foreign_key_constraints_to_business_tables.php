<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_costs', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('product_price_changes', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dropship_costs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        Schema::table('product_price_changes', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
    }
};