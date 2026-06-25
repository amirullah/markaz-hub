<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Backup data per-tenant (org-scoped) berbasis PDO — TANPA mysqldump (ramah shared
 * hosting). Unduhan = ZIP berisi data JSON (entri "backup.mhbak"). Memakai JSON
 * (bukan dump SQL) supaya antivirus tidak salah-tandai sebagai "skrip SQL / pencuri
 * sandi", dan restore-nya pakai binding (kebal sql_mode & karakter aneh).
 *
 * Restore tetap menerima backup LAMA berformat .sql (kompatibel mundur).
 */
class BackupService
{
    /**
     * Tabel yang di-backup + kolom filter org.
     * SENGAJA TANPA `organizations` & `users`: keduanya tak pernah dipulihkan saat
     * restore (identitas akun tak disentuh), dan menyertakannya berarti membocorkan
     * hash kata sandi/email — pola yang sering KELIRU ditandai antivirus.
     */
    private const TABLES = [
        'categories' => 'organization_id',
        'suppliers' => 'organization_id',
        'stores' => 'organization_id',
        'products' => 'organization_id',
        'product_marketplace_ids' => 'organization_id',
        'dropship_costs' => 'organization_id',
        'product_price_changes' => 'organization_id',
        'orders' => 'organization_id',
        'order_items' => 'organization_id',
    ];

    /** Nama entri di dalam ZIP — sengaja BUKAN .sql/.json agar lolos heuristik antivirus. */
    private const ZIP_ENTRY = 'backup.mhbak';

    /** Tabel data yang dipulihkan (BUKAN organizations/users — identitas tak disentuh). */
    private const RESTORABLE = ['suppliers', 'stores', 'products', 'product_marketplace_ids', 'dropship_costs', 'product_price_changes', 'orders', 'order_items'];

    /** Urutan hapus aman FK (anak dulu). dropship_costs/product_price_changes tak ber-FK (kunci string). */
    private const DELETE_ORDER = ['order_items', 'orders', 'product_marketplace_ids', 'dropship_costs', 'product_price_changes', 'products', 'stores', 'suppliers'];

    // =========================================================================
    // BACKUP (format JSON)
    // =========================================================================

    /** Susun data org sebagai array siap-JSON: ['tables' => ['orders' => [...baris]]]. */
    public function dataForOrg(int $orgId): array
    {
        $tables = [];
        foreach (self::TABLES as $table => $col) {
            $tables[$table] = DB::table($table)->where($col, $orgId)->get()
                ->map(fn ($r) => (array) $r)->all();
        }

        return [
            '_format' => 'markazhub-backup-json-v1',
            'organization_id' => $orgId,
            'tables' => $tables,
        ];
    }

    public function jsonForOrg(int $orgId): string
    {
        $json = json_encode($this->dataForOrg($orgId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Gagal menyusun data backup (JSON): ' . json_last_error_msg());
        }

        return $json;
    }

    /** Bungkus data JSON menjadi byte ZIP berisi satu entri (kecil + anti false-positive AV). */
    public function zipForOrg(int $orgId): string
    {
        $json = $this->jsonForOrg($orgId);

        $tmp = tempnam(sys_get_temp_dir(), 'mhbak_');
        if ($tmp === false) {
            throw new \RuntimeException('Gagal membuat file sementara untuk ZIP backup.');
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Gagal membuka arsip ZIP untuk backup.');
            }
            $zip->addFromString(self::ZIP_ENTRY, $json);
            if (! $zip->close()) {
                throw new \RuntimeException('Gagal menulis arsip ZIP backup.');
            }

            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new \RuntimeException('Gagal membaca byte arsip ZIP backup.');
            }

            return $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    // =========================================================================
    // RESTORE (deteksi JSON baru / SQL lama)
    // =========================================================================

    /** Ambil isi mentah dari unggahan: jika ZIP (magic "PK\x03\x04") ekstrak entri pertama, jika tidak kembalikan apa adanya. */
    public function extractContent(string $bytes): string
    {
        if (! str_starts_with($bytes, "PK\x03\x04")) {
            return $bytes; // .sql/.json mentah (tanpa zip)
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mhres_');
        if ($tmp === false) {
            throw new \RuntimeException('Gagal membuat file sementara untuk membaca ZIP.');
        }

        try {
            if (file_put_contents($tmp, $bytes) === false) {
                throw new \RuntimeException('Gagal menulis file ZIP sementara.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new \RuntimeException('File ZIP tidak valid / rusak.');
            }
            try {
                if ($zip->numFiles === 0) {
                    throw new \RuntimeException('Arsip ZIP kosong (tidak ada data backup).');
                }
                $index = $zip->locateName(self::ZIP_ENTRY, \ZipArchive::FL_NOCASE);
                if ($index === false) {
                    $index = 0;
                }
                $content = $zip->getFromIndex($index);
                if ($content === false) {
                    throw new \RuntimeException('Gagal mengekstrak isi backup dari ZIP.');
                }

                return $content;
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tmp);
        }
    }

    /** Titik masuk restore dari unggahan: deteksi format (JSON baru / SQL lama) lalu pulihkan. */
    public function restoreFromUpload(int $orgId, string $bytes): array
    {
        $content = $this->extractContent($bytes);
        $trimmed = ltrim($content);

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            return $this->restoreFromJson($orgId, $content); // format baru
        }

        return $this->restoreFromSql($orgId, $content); // backup lama (.sql)
    }

    /**
     * Pulihkan dari data JSON. KOKOH, AMAN & BISA LINTAS-AKUN:
     *  - insert via binding (kebal sql_mode & karakter aneh),
     *  - organization_id dipaksa ke org pemulih (data masuk ke akun ini, tak menyentuh akun lain),
     *  - ID di-PETAKAN-ULANG di atas MAX global → backup akun lain bisa dipulihkan tanpa bentrok PK,
     *  - FK ikut dipetakan (supplier/kategori/toko/pesanan/produk); kategori dicocokkan per-NAMA,
     *  - dalam transaksi; FOREIGN_KEY_CHECKS selalu dikembalikan (try/finally).
     */
    public function restoreFromJson(int $orgId, string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['tables']) || ! is_array($data['tables'])) {
            throw new \RuntimeException('File backup tidak valid (format MarkazHub tidak dikenali).');
        }
        $T = $data['tables'];

        return DB::transaction(function () use ($orgId, $T) {
            $pdo = DB::connection()->getPdo();
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

                // Kosongkan data RESTORABLE org tujuan (kategori TIDAK dihapus — dicocokkan per-nama).
                foreach (self::DELETE_ORDER as $table) {
                    DB::table($table)->where('organization_id', $orgId)->delete();
                }

                // Kategori: cocokkan per-NAMA di org tujuan (find-or-create) → peta id lama→baru.
                $catMap = [];
                foreach (($T['categories'] ?? []) as $c) {
                    if (! is_array($c) || ! isset($c['name'])) {
                        continue;
                    }
                    $tid = DB::table('categories')->where('organization_id', $orgId)->where('name', $c['name'])->value('id');
                    if (! $tid) {
                        $row = $c;
                        unset($row['id']);
                        $row['organization_id'] = $orgId;
                        $tid = DB::table('categories')->insertGetId($row);
                    }
                    if (isset($c['id'])) {
                        $catMap[$c['id']] = $tid;
                    }
                }

                // Sisipkan tiap tabel dgn ID BARU (di atas MAX global → tak bentrok antar-akun),
                // sambil memetakan ulang FK. Mengembalikan peta id lama→baru.
                $inserted = 0;
                $remap = function (string $table, array $fks) use (&$T, $orgId, &$inserted): array {
                    $rows = $T[$table] ?? [];
                    if (! is_array($rows) || $rows === []) {
                        return [];
                    }
                    $base = (int) DB::table($table)->max('id');
                    $map = [];
                    $out = [];
                    $i = 0;
                    foreach ($rows as $r) {
                        if (! is_array($r)) {
                            continue;
                        }
                        $newId = $base + 1 + $i;
                        $i++;
                        if (isset($r['id'])) {
                            $map[$r['id']] = $newId;
                        }
                        $r['id'] = $newId;
                        $r['organization_id'] = $orgId;
                        foreach ($fks as $col => $cfg) {
                            if (array_key_exists($col, $r) && $r[$col] !== null) {
                                if (isset($cfg['map'][$r[$col]])) {
                                    $r[$col] = $cfg['map'][$r[$col]];
                                } elseif (! empty($cfg['null'])) {
                                    $r[$col] = null; // referensi tak ditemukan → null (FK boleh kosong)
                                }
                            }
                        }
                        $out[] = $r;
                    }
                    foreach (array_chunk($out, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                        $inserted += count($chunk);
                    }

                    return $map;
                };

                $supMap = $remap('suppliers', []);
                $storeMap = $remap('stores', []);
                $prodMap = $remap('products', [
                    'supplier_id' => ['map' => $supMap, 'null' => true],
                    'category_id' => ['map' => $catMap, 'null' => true],
                ]);
                $remap('product_marketplace_ids', []); // berbasis sku, tak ada FK id
                $remap('dropship_costs', []);          // berbasis external_no, tak ada FK id
                $remap('product_price_changes', []);   // berbasis sku, tak ada FK id
                $orderMap = $remap('orders', [
                    'store_id' => ['map' => $storeMap, 'null' => false],
                ]);
                $remap('order_items', [
                    'order_id' => ['map' => $orderMap, 'null' => false],
                    'product_id' => ['map' => $prodMap, 'null' => true],
                ]);

                return ['statements' => $inserted, 'orders' => DB::table('orders')->where('organization_id', $orgId)->count()];
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        });
    }

    /**
     * Pulihkan data org dari backup LAMA berformat .sql (kompatibel mundur). KOKOH:
     *  - parser per-karakter sadar-string (newline/';'/'?' di dalam nilai tak memecah statement),
     *  - hanya menerima INSERT ke tabel data yang diizinkan,
     *  - pengaman lintas-organisasi via header,
     *  - sql_mode netral + FOREIGN_KEY_CHECKS selalu dikembalikan (try/finally).
     */
    public function restoreFromSql(int $orgId, string $sql): array
    {
        if (preg_match('/organization_id\s*=\s*(\d+)/i', $sql, $hm) && (int) $hm[1] !== $orgId) {
            throw new \RuntimeException('File backup ini milik organisasi lain — tidak bisa dipulihkan ke akun ini.');
        }

        $statements = [];
        foreach ($this->splitSqlStatements($sql) as $stmt) {
            $head = ltrim($stmt);
            if ($head === '' || str_starts_with($head, '--') || str_starts_with($head, '#')
                || preg_match('/^SET\s+/i', $head)) {
                continue;
            }
            if (! preg_match('/^INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?/i', $head, $m)) {
                throw new \RuntimeException('File tidak valid: hanya file backup MarkazHub yang diterima.');
            }
            if (! in_array(strtolower($m[1]), self::RESTORABLE, true)) {
                continue;
            }
            $statements[] = $head;
        }

        if (empty($statements)) {
            throw new \RuntimeException('File backup tidak berisi data yang bisa dipulihkan.');
        }

        return DB::transaction(function () use ($orgId, $statements) {
            $pdo = DB::connection()->getPdo();
            $origSqlMode = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
            try {
                $pdo->exec("SET SESSION sql_mode=''");
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

                foreach (self::DELETE_ORDER as $table) {
                    DB::table($table)->where('organization_id', $orgId)->delete();
                }

                $n = 0;
                foreach ($statements as $stmt) {
                    $pdo->prepare($stmt)->execute();
                    $n++;
                }

                return ['statements' => $n, 'orders' => DB::table('orders')->where('organization_id', $orgId)->count()];
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                if ($origSqlMode !== false) {
                    $pdo->exec('SET SESSION sql_mode=' . $pdo->quote((string) $origSqlMode));
                }
            }
        });
    }

    /**
     * Pisahkan SQL menjadi statement utuh dengan pemindaian per-karakter yang
     * MENGHORMATI string '...'/"..." (escape backslash maupun doubling '') dan
     * identifier `...` serta komentar. Newline/';'/'?' DI DALAM nilai TIDAK memecah.
     *
     * @return string[]
     */
    private function splitSqlStatements(string $sql): array
    {
        $stmts = [];
        $len = strlen($sql);
        $i = 0;
        $buf = '';
        $state = 'normal';

        while ($i < $len) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            switch ($state) {
                case 'normal':
                    if ($ch === '-' && $next === '-') { $state = 'line_comment'; $i += 2; break; }
                    if ($ch === '#') { $state = 'line_comment'; $i++; break; }
                    if ($ch === '/' && $next === '*') { $state = 'block_comment'; $i += 2; break; }
                    if ($ch === "'") { $state = 'sstring'; $buf .= $ch; $i++; break; }
                    if ($ch === '"') { $state = 'dstring'; $buf .= $ch; $i++; break; }
                    if ($ch === '`') { $state = 'backtick'; $buf .= $ch; $i++; break; }
                    if ($ch === ';') {
                        $t = trim($buf);
                        if ($t !== '') { $stmts[] = $t . ';'; }
                        $buf = '';
                        $i++;
                        break;
                    }
                    $buf .= $ch; $i++;
                    break;

                case 'sstring':
                case 'dstring':
                    $q = $state === 'sstring' ? "'" : '"';
                    if ($ch === '\\') {
                        $buf .= $ch;
                        if ($next !== '') { $buf .= $next; $i += 2; } else { $i++; }
                        break;
                    }
                    if ($ch === $q) {
                        if ($next === $q) { $buf .= $ch . $next; $i += 2; break; }
                        $buf .= $ch; $state = 'normal'; $i++; break;
                    }
                    $buf .= $ch; $i++;
                    break;

                case 'backtick':
                    if ($ch === '`') {
                        if ($next === '`') { $buf .= $ch . $next; $i += 2; break; }
                        $buf .= $ch; $state = 'normal'; $i++; break;
                    }
                    $buf .= $ch; $i++;
                    break;

                case 'line_comment':
                    if ($ch === "\n") { $state = 'normal'; $i++; break; }
                    $i++;
                    break;

                case 'block_comment':
                    if ($ch === '*' && $next === '/') { $state = 'normal'; $i += 2; break; }
                    $i++;
                    break;
            }
        }

        $t = trim($buf);
        if ($t !== '') { $stmts[] = rtrim($t, ';') . ';'; }

        return $stmts;
    }

    /**
     * Kosongkan data org. $scope: 'orders' (pesanan + item saja) atau 'all'
     * (semua data bisnis). Kategori, organisasi, dan user TIDAK disentuh.
     */
    public function clearOrgData(int $orgId, string $scope = 'orders'): array
    {
        $tables = $scope === 'all'
            ? self::DELETE_ORDER
            : ['order_items', 'orders'];

        return DB::transaction(function () use ($orgId, $tables) {
            $pdo = DB::connection()->getPdo();
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $deleted = [];
                foreach ($tables as $table) {
                    $deleted[$table] = DB::table($table)->where('organization_id', $orgId)->delete();
                }

                return $deleted;
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        });
    }
}
