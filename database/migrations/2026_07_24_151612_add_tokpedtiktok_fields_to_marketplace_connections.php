<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_connections', 'shop_cipher')) {
                $table->string('shop_cipher', 64)->nullable()->after('shop_id');
            }
            if (! Schema::hasColumn('marketplace_connections', 'refresh_token_expires_at')) {
                $table->timestamp('refresh_token_expires_at')->nullable()->after('access_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->dropColumn(['shop_cipher', 'refresh_token_expires_at']);
        });
    }
};