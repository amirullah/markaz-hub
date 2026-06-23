<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_price_changes')) {
            Schema::create('product_price_changes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->string('sku');
                $table->decimal('old_price', 14, 2)->nullable();
                $table->decimal('new_price', 14, 2);
                $table->timestamp('changed_at')->nullable(); // dari kolom "Perubahan Terakhir" master
                $table->timestamps();
                $table->index(['organization_id', 'sku']);
            });
        }

        if (! Schema::hasColumn('products', 'cost_changed_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('cost_changed_at')->nullable()->after('dropship_cost');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_changes');
        if (Schema::hasColumn('products', 'cost_changed_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('cost_changed_at');
            });
        }
    }
};
