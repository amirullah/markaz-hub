<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // % biaya admin per channel (dari dokumentasi resmi marketplace; bisa disesuaikan)
            $table->decimal('fee_shopee', 5, 2)->default(0);
            $table->decimal('fee_tokotiktok', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'categories_org_name_uq');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('supplier_id')
                ->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('categories');
    }
};
