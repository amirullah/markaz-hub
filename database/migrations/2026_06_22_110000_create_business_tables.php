<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel bisnis MarkazHub (port dari v1 schema.sql) + multi-tenant (organization_id)
 * + index performa untuk data besar. Semua nilai uang DECIMAL(14,2) (Rupiah).
 */
return new class extends Migration {
    public function up(): void
    {
        // Supplier (Jakmall / stok sendiri)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->enum('type', ['SELF', 'JAKMALL', 'OTHER'])->default('SELF');
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'suppliers_org_name_uq');
        });

        // Toko per marketplace
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->enum('marketplace', ['SHOPEE', 'TOKOPEDIA', 'TIKTOK']);
            $table->boolean('active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'marketplace', 'name'], 'stores_org_mp_name_uq');
            $table->index(['organization_id'], 'stores_org_idx');
        });

        // Produk + HPP/modal (dicocokkan via SKU)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 190);
            $table->string('name', 255);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('dropship_cost', 14, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'sku'], 'products_org_sku_uq');
        });

        // Pemetaan ID Produk marketplace -> SKU (dari Master Jakmall)
        Schema::create('product_marketplace_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace_product_id', 64);
            $table->string('sku', 190);
            $table->timestamps();
            $table->unique(['organization_id', 'marketplace_product_id'], 'pmi_org_mpid_uq');
            $table->index(['organization_id', 'sku'], 'pmi_org_sku_idx');
        });

        // Pesanan
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('external_no', 190);
            $table->enum('marketplace', ['SHOPEE', 'TOKOPEDIA', 'TIKTOK']);
            $table->enum('status', ['PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED', 'RETURNED'])->default('PAID');
            $table->enum('fulfillment', ['SELF', 'DROPSHIP'])->default('SELF');
            $table->dateTime('order_date')->useCurrent();
            $table->string('buyer_name', 190)->nullable();
            // pendapatan
            $table->decimal('product_revenue', 14, 2)->default(0);
            $table->decimal('shipping_charged_to_buyer', 14, 2)->default(0);
            $table->decimal('other_income', 14, 2)->default(0);
            // biaya
            $table->decimal('cogs', 14, 2)->default(0);
            $table->decimal('admin_fee', 14, 2)->default(0);
            $table->decimal('shipping_cost_seller', 14, 2)->default(0);
            $table->decimal('voucher_seller_borne', 14, 2)->default(0);
            $table->decimal('dropship_cost', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);
            $table->boolean('income_verified')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes(); // soft delete: pesanan bisa dipulihkan (anti kehilangan data)
            // dedup external_no per tenant (lintas toko)
            $table->unique(['organization_id', 'external_no'], 'orders_org_extno_uq');
            // index performa data besar
            $table->index(['organization_id', 'order_date'], 'orders_org_date_idx');
            $table->index(['organization_id', 'status'], 'orders_org_status_idx');
            $table->index(['organization_id', 'marketplace', 'order_date'], 'orders_org_mp_date_idx');
            $table->index(['organization_id', 'fulfillment'], 'orders_org_ful_idx');
        });

        // Item pesanan
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('sku', 190)->nullable();
            $table->string('name', 255);
            $table->integer('qty')->default(1);
            $table->boolean('qty_assumed')->default(false);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->index(['order_id'], 'items_order_idx');
            $table->index(['organization_id', 'sku'], 'items_org_sku_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_marketplace_ids');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('suppliers');
    }
};
