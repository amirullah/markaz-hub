<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM kaku ('SELF','OTHER',+tipe lama) → VARCHAR agar mendukung tipe supplier
        // dropship generik (sumber apa pun), bukan terkunci ke satu penyedia.
        DB::statement("ALTER TABLE suppliers MODIFY type VARCHAR(20) NOT NULL DEFAULT 'SELF'");
        // Tipe lama selain SELF/OTHER (supplier dropship lama) → 'DROPSHIP'.
        DB::statement("UPDATE suppliers SET type = 'DROPSHIP' WHERE type NOT IN ('SELF', 'OTHER', 'DROPSHIP')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY type VARCHAR(20) NOT NULL DEFAULT 'SELF'");
    }
};
