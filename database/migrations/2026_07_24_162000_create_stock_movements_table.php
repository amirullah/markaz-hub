<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('type', ['IN', 'OUT', 'ADJUSTMENT'])->default('ADJUSTMENT');
            $table->integer('qty');
            $table->string('reference', 100)->nullable()->comment('Order external_no atau referensi lain');
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'product_id'], 'sm_org_prod_idx');
            $table->index(['organization_id', 'created_at'], 'sm_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
