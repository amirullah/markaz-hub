<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Backup data per-tenant (org-scoped) berbasis PDO — TANPA mysqldump (ramah shared
 * hosting). Menghasilkan file .sql berisi INSERT data milik organisasi tsb saja
 * (tidak membocorkan data tenant lain).
 */
class BackupService
{
    /** Tabel yang di-backup + kolom filter org. */
    private const TABLES = [
        'organizations' => 'id',
        'users' => 'organization_id',
        'suppliers' => 'organization_id',
        'stores' => 'organization_id',
        'products' => 'organization_id',
        'product_marketplace_ids' => 'organization_id',
        'orders' => 'organization_id',
        'order_items' => 'organization_id',
    ];

    public function sqlForOrg(int $orgId): string
    {
        $pdo = DB::connection()->getPdo();
        $out = "-- MarkazHub backup data (organization_id={$orgId})\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n";

        foreach (self::TABLES as $table => $col) {
            $rows = DB::table($table)->where($col, $orgId)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $cols = array_keys((array) $rows->first());
            $colList = '`' . implode('`,`', $cols) . '`';
            $out .= "\n-- {$table} ({$rows->count()} baris)\n";
            foreach ($rows->chunk(200) as $chunk) {
                $valuesSql = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ((array) $row as $v) {
                        $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }
                    $valuesSql[] = '(' . implode(',', $vals) . ')';
                }
                $out .= "INSERT INTO `{$table}` ({$colList}) VALUES " . implode(',', $valuesSql) . ";\n";
            }
        }

        $out .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    /** Tabel data yang dipulihkan (BUKAN organizations/users — identitas tak disentuh). */
    private const RESTORABLE = ['suppliers', 'stores', 'products', 'product_marketplace_ids', 'orders', 'order_items'];

    /** Urutan hapus aman FK (anak dulu). */
    private const DELETE_ORDER = ['order_items', 'orders', 'product_marketplace_ids', 'products', 'stores', 'suppliers'];

    /**
     * Pulihkan data org dari isi file .sql hasil backup. AMAN:
     *  - hanya menerima statement INSERT ke tabel data yang diizinkan (tolak perintah lain),
     *  - dieksekusi sbg prepared statement (tak bisa multi-statement / DROP/UPDATE selundupan),
     *  - dalam transaksi: hapus data org saat ini lalu insert dari backup.
     */
    public function restoreFromSql(int $orgId, string $sql): array
    {
        $statements = [];
        foreach (preg_split('/\r?\n/', $sql) as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '--') || preg_match('/^SET\s+FOREIGN_KEY_CHECKS/i', $t)) {
                continue;
            }
            if (! preg_match('/^INSERT INTO `([a-z_]+)`/i', $t, $m)) {
                throw new \RuntimeException('File tidak valid: hanya file backup MarkazHub (.sql) yang diterima.');
            }
            if (! in_array($m[1], self::RESTORABLE, true)) {
                continue; // lewati organizations/users dll
            }
            $statements[] = $t;
        }

        if (empty($statements)) {
            throw new \RuntimeException('File backup tidak berisi data yang bisa dipulihkan.');
        }

        return DB::transaction(function () use ($orgId, $statements) {
            $pdo = DB::connection()->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (self::DELETE_ORDER as $table) {
                DB::table($table)->where('organization_id', $orgId)->delete();
            }
            $n = 0;
            foreach ($statements as $stmt) {
                $pdo->prepare($stmt)->execute(); // prepared → menolak multi-statement
                $n++;
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            return ['statements' => $n, 'orders' => DB::table('orders')->where('organization_id', $orgId)->count()];
        });
    }

    /**
     * Kosongkan data org. $scope: 'orders' (pesanan + item saja) atau 'all'
     * (semua data bisnis: pesanan, produk, toko, supplier). Kategori, organisasi,
     * dan user TIDAK disentuh. Org-scoped + dalam transaksi.
     */
    public function clearOrgData(int $orgId, string $scope = 'orders'): array
    {
        $tables = $scope === 'all'
            ? self::DELETE_ORDER
            : ['order_items', 'orders'];

        return DB::transaction(function () use ($orgId, $tables) {
            $pdo = DB::connection()->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $deleted = [];
            foreach ($tables as $table) {
                $deleted[$table] = DB::table($table)->where('organization_id', $orgId)->delete();
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            return $deleted;
        });
    }
}
