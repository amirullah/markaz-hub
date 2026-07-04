<?php
// Parser laporan pesanan (CSV & XLSX), toleran terhadap variasi nama kolom dari
// Shopee / Tokopedia / TikTok Shop. Menghasilkan struktur pesanan
// ternormalisasi (lepas dari format asal) - mirip lapisan adapter di
// versi Next.js, sehingga mudah ditambah sumber lain (mis. API resmi).

require_once __DIR__ . '/xlsx.php';

// Normalisasi nama kolom: huruf kecil, buang non-alfanumerik.
function mp_norm_key(string $k): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($k));
}

// Ambil nilai pertama yang cocok dari daftar kandidat nama kolom.
function mp_pick(array $row, array $candidates): ?string
{
    foreach ($candidates as $c) {
        $key = mp_norm_key($c);
        if (isset($row[$key]) && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return null;
}

// Cocokkan kolom secara SEBAGIAN (substring ter-normalisasi) — untuk nama kolom
// panjang/varian supplier. Mengembalikan nilai kolom pertama yang mengandung needle.
function mp_pick_like(array $row, string $needle): ?string
{
    $n = mp_norm_key($needle);
    if ($n === '') return null;
    foreach ($row as $k => $v) {
        if ($v !== '' && $v !== null && str_contains((string) $k, $n)) {
            return $v;
        }
    }
    return null;
}

// Parse angka gaya Indonesia: koma = desimal, titik = pemisah ribuan.
// Contoh: "Rp1.250.000,50" -> 1250000.5 ; "30.347" -> 30347 ; "20.881" -> 20881.
// Catatan: ekspor Shopee/Dropship sering menulis rupiah sebagai TEKS dengan titik
// ribuan (mis. "187.271"), jadi titik tunggal berpola grup-3-digit = ribuan.
function mp_num(?string $v): float
{
    if ($v === null || $v === '') return 0.0;
    $s = preg_replace('/\s+/', '', str_ireplace('rp', '', $v));
    $hasComma = strpos($s, ',') !== false;
    $dots = substr_count($s, '.');
    if ($hasComma) {
        // Koma = desimal; titik = ribuan.
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($dots >= 1) {
        // Tanpa koma: anggap titik = ribuan bila ada >1 titik, atau berpola
        // grup 3 digit (mis. "30.347", "1.250.000"). Selain itu desimal biasa.
        if ($dots > 1 || preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }
    }
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    return is_numeric($s) ? (float) $s : 0.0;
}

function mp_int(?string $v): int
{
    $n = (int) round(mp_num($v));
    return $n > 0 ? $n : 1;
}

const MP_COLUMNS = [
    'externalNo' => ['order_no', 'nomor_pesanan', 'no_pesanan', 'no. pesanan', 'order id', 'order sn', 'ordersn', 'invoice', 'nomor invoice'],
    'orderDate'  => ['order_date', 'tanggal', 'tanggal pesanan', 'waktu pesanan dibuat', 'created time', 'order creation date'],
    'status'     => ['status', 'order status', 'status pesanan'],
    'returnReason' => ['cancel reason', 'alasan pembatalan', 'cancelation/return type', 'status pembatalan/ pengembalian', 'alasan'],
    'buyerName'  => ['buyer', 'buyer name', 'pembeli', 'username pembeli', 'username (pembeli)', 'nama pembeli'],
    'shippingChargedToBuyer' => ['shipping_charged', 'ongkir dibayar pembeli', 'ongkos kirim dibayar oleh pembeli', 'ongkos kirim dibayar pembeli', 'shipping fee paid by buyer'],
    'adminFee'   => ['admin_fee', 'biaya admin', 'biaya administrasi', 'biaya layanan', 'commission fee', 'transaction fee', 'platform fee'],
    'shippingCostSeller' => ['shipping_cost_seller', 'ongkir ditanggung penjual', 'subsidi ongkir', 'seller shipping fee'],
    'voucherSellerBorne' => ['voucher_seller', 'voucher ditanggung penjual', 'diskon dari penjual', 'diskon penjual', 'seller discount', 'seller voucher'],
    'otherIncome' => ['other_income', 'pendapatan lain'],
    'otherCost'  => ['other_cost', 'biaya lain'],
    'sku'        => ['sku', 'sku produk', 'seller sku', 'nomor referensi sku', 'sku induk'],
    'productName' => ['product_name', 'nama produk', 'product name'],
    'qty'        => ['qty', 'quantity', 'jumlah', 'kuantitas'],
    'unitPrice'  => ['unit_price', 'harga setelah diskon', 'sku unit original price', 'harga satuan', 'harga awal', 'original price', 'harga jual'],
];

// Kolom Laporan Penghasilan Tokopedia/TikTok (Seller Center gabungan),
// sheet "Detail pesanan". Biaya bertanda negatif; uang bersih = penyelesaian.
// Indonesia DAN Inggris (ekspor TikTok/Tokopedia bisa berbahasa Inggris).
const MP_TIKTOK_INCOME = [
    'externalNo'  => ['id pesanan/penyesuaian', 'order/adjustment id', 'order id', 'id pesanan'],
    'txType'      => ['jenis transaksi', 'type'],
    'orderDate'   => ['waktu pemesanan', 'waktu pembayaran pesanan', 'order created time', 'order settled time'],
    'revenue'     => ['total pendapatan', 'total revenue'],
    'net'         => ['jumlah penyelesaian pembayaran', 'jumlah penyelesaian pesanan', 'total settlement amount'],
    'totalFees'   => ['total biaya', 'total fees'],
    'origValue'   => ['subtotal setelah diskon penjual', 'subtotal sebelum diskon', 'subtotal after seller discounts', 'subtotal before discounts', 'customer payment'],
    'refund'      => ['pengembalian dana pembeli', 'subtotal pengembalian dana setelah diskon penjual', 'refund subtotal after seller discounts', 'customer refund'],
];

// Kolom khusus Laporan Penghasilan Shopee (sheet "Income"). Biaya bertanda
// negatif; nilai bersih akhir = "Total Penghasilan".
const MP_SHOPEE_INCOME = [
    'externalNo'      => ['no. pesanan'],
    'orderDate'       => ['waktu pesanan dibuat'],
    'buyerName'       => ['username (pembeli)'],
    'productRevenue'  => ['harga asli produk'],
    'totalIncome'     => ['total penghasilan'],
    'shippingToBuyer' => ['ongkir dibayar pembeli'],
    'refund'          => ['jumlah pengembalian dana ke pembeli', 'pengembalian dana ke pembeli'],
    // Potongan platform (biaya layanan marketplace).
    'platformFees' => [
        'biaya komisi ams', 'biaya administrasi', 'biaya layanan', 'biaya proses pesanan',
        'premi', 'biaya program hemat biaya kirim', 'biaya transaksi', 'biaya kampanye',
        'bea masuk, ppn & pph', 'biaya isi saldo otomatis (dari penghasilan)',
    ],
    // Diskon / voucher yang ditanggung penjual.
    'sellerDiscounts' => [
        'total diskon produk', 'voucher disponsor oleh penjual',
        'voucher co-fund disponsor oleh penjual', 'cashback koin disponsori penjual',
        'cashback koin co-fund disponsori penjual', 'promo gratis ongkir dari penjual',
    ],
];

// Baca file CSV menjadi array baris asosiatif (kunci sudah dinormalisasi).
function mp_read_csv(string $path): array
{
    $rows = [];
    if (($h = fopen($path, 'r')) === false) return $rows;
    $header = fgetcsv($h);
    if ($header === false) {
        fclose($h);
        return $rows;
    }
    // BOM cleanup pada kolom pertama
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $keys = array_map('mp_norm_key', $header);
    while (($data = fgetcsv($h)) !== false) {
        if (count($data) === 1 && trim((string) $data[0]) === '') continue;
        $row = [];
        foreach ($keys as $i => $k) {
            $row[$k] = isset($data[$i]) ? trim((string) $data[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($h);
    return $rows;
}

// Gabungkan baris menjadi pesanan (satu baris per item -> dikelompokkan
// berdasarkan nomor pesanan). Omzet dihitung dari penjumlahan item bila
// harga satuan tersedia (lebih andal pada pesanan multi-item).
function mp_rows_to_orders(array $rows): array
{
    $byOrder = [];
    foreach ($rows as $r) {
        $no = mp_pick($r, MP_COLUMNS['externalNo']);
        if (!$no) continue;
        if (!isset($byOrder[$no])) {
            $byOrder[$no] = [
                'externalNo' => $no,
                'orderDate'  => mp_pick($r, MP_COLUMNS['orderDate']),
                'status'     => mp_pick($r, MP_COLUMNS['status']),
                'buyerName'  => mp_pick($r, MP_COLUMNS['buyerName']),
                'shippingChargedToBuyer' => mp_num(mp_pick($r, MP_COLUMNS['shippingChargedToBuyer'])),
                'adminFee'   => mp_num(mp_pick($r, MP_COLUMNS['adminFee'])),
                'shippingCostSeller' => mp_num(mp_pick($r, MP_COLUMNS['shippingCostSeller'])),
                'voucherSellerBorne' => mp_num(mp_pick($r, MP_COLUMNS['voucherSellerBorne'])),
                'otherIncome' => mp_num(mp_pick($r, MP_COLUMNS['otherIncome'])),
                'otherCost'  => mp_num(mp_pick($r, MP_COLUMNS['otherCost'])),
                'productRevenue' => 0,
                // Alasan retur/batal (bila ada) -> disimpan ke catatan pesanan,
                // agar kondisi retur (tdk sampai / dikembalikan pembeli) terlihat.
                'note'       => mp_pick($r, MP_COLUMNS['returnReason']) ?: null,
                'items'      => [],
            ];
        }
        $name = mp_pick($r, MP_COLUMNS['productName']);
        if ($name) {
            $byOrder[$no]['items'][] = [
                'sku'       => mp_pick($r, MP_COLUMNS['sku']),
                'name'      => $name,
                'qty'       => mp_int(mp_pick($r, MP_COLUMNS['qty'])),
                'unitPrice' => mp_num(mp_pick($r, MP_COLUMNS['unitPrice'])),
            ];
        }
    }
    foreach ($byOrder as &$o) {
        $sum = 0;
        foreach ($o['items'] as $it) {
            $sum += $it['unitPrice'] * $it['qty'];
        }
        if ($sum > 0) $o['productRevenue'] = $sum;
    }
    return array_values($byOrder);
}

// Petakan teks status bebas ke enum internal.
function mp_map_status(?string $raw): string
{
    $s = strtolower((string) $raw);
    if (preg_match('/(batal|cancel)/', $s)) return 'CANCELLED';
    if (preg_match('/(retur|return|refund|kembali)/', $s)) return 'RETURNED';
    if (preg_match('/(selesai|complete|delivered|diterima)/', $s)) return 'COMPLETED';
    if (preg_match('/(kirim|ship|dikirim)/', $s)) return 'SHIPPED';
    if (preg_match('/(bayar|paid|lunas|baru)/', $s)) return 'PAID';
    if (preg_match('/(pending|menunggu|belum)/', $s)) return 'PENDING';
    return 'PAID';
}

function mp_parse_date(?string $raw): string
{
    if (!$raw) return date('Y-m-d H:i:s');
    $raw = trim($raw);
    // Angka murni 4-5 digit = tanggal SERIAL Excel (sel bertipe tanggal tersimpan sebagai angka,
    // mis. 45123 = 16 Jul 2023). Tanpa ini strtotime gagal → diam-diam jadi "hari ini".
    if (preg_match('/^\d{4,5}(\.\d+)?$/', $raw) && (float) $raw >= 20000 && (float) $raw < 80000) {
        return gmdate('Y-m-d H:i:s', (int) round(((float) $raw - 25569) * 86400)); // epoch Excel 1900
    }
    // Tokopedia/TikTok & Dropship menulis tanggal DD/MM/YYYY. strtotime menganggap
    // garis miring = M/D/Y (gaya AS), jadi salah baca. Ubah dulu ke Y-M-D.
    // (Format Y/M/D "2026/06/17" dan ISO "2026-06-14" tidak ikut terpola.)
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(.*)$#', $raw, $m)) {
        $raw = sprintf('%04d-%02d-%02d%s', (int) $m[3], (int) $m[2], (int) $m[1], $m[4]);
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
}

// ============================================================
// Pembaca XLSX + deteksi format otomatis (tanpa template khusus)
// ============================================================

// Cari indeks baris header di antara $scan baris pertama: baris yang memuat
// SEMUA kunci (sudah dinormalisasi) pada $mustHave. Kembalikan -1 jika tak ada.
function mp_header_index(array $rows, array $mustHave, int $scan = 20): int
{
    $limit = min($scan, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        $keys = array_map('mp_norm_key', array_map('strval', $rows[$i]));
        $set = array_flip($keys);
        $ok = true;
        foreach ($mustHave as $k) {
            if (!isset($set[mp_norm_key($k)])) { $ok = false; break; }
        }
        if ($ok) return $i;
    }
    return -1;
}

// Ubah baris mentah (array indeks) menjadi baris asosiatif dengan kunci
// ternormalisasi, memakai baris $headerIdx sebagai header.
function mp_assoc_rows(array $rows, int $headerIdx): array
{
    $keys = array_map('mp_norm_key', array_map('strval', $rows[$headerIdx]));
    $out = [];
    $n = count($rows);
    for ($i = $headerIdx + 1; $i < $n; $i++) {
        $data = $rows[$i];
        $blank = true;
        $assoc = [];
        foreach ($keys as $ci => $k) {
            if ($k === '') continue;
            $v = isset($data[$ci]) ? trim((string) $data[$ci]) : '';
            if ($v !== '') $blank = false;
            // jangan timpa kolom duplikat yang sudah berisi
            if (!isset($assoc[$k]) || $assoc[$k] === '') $assoc[$k] = $v;
        }
        if (!$blank) $out[] = $assoc;
    }
    return $out;
}

// Jumlahkan nilai absolut dari beberapa kolom kandidat (untuk biaya negatif).
function mp_abs_sum(array $row, array $candidates): float
{
    $sum = 0.0;
    foreach ($candidates as $c) {
        $k = mp_norm_key($c);
        if (isset($row[$k]) && $row[$k] !== '') $sum += abs(mp_num($row[$k]));
    }
    return $sum;
}

// ---------- Adapter: Master Produk Dropship ----------
// Kembalikan daftar produk [sku, name, cost] dari kolom Kode SKU / Nama / Harga.
function mp_catalog_products(array $assoc): array
{
    $out = [];
    foreach ($assoc as $r) {
        $sku = mp_pick($r, ['kode sku', 'sku']);
        if (!$sku) continue;
        // Kumpulkan semua kolom "Product ID ..." (ID produk per toko marketplace).
        $mpIds = [];
        foreach ($r as $k => $v) {
            if (strpos($k, 'productid') === 0 && $v !== '' && $v !== '-') $mpIds[] = $v;
        }
        $out[] = [
            'sku'       => $sku,
            'name'      => mp_pick($r, ['nama produk', 'product name']) ?: $sku,
            'cost'      => mp_num(mp_pick($r, ['harga', 'price', 'cost'])),
            'changedAt' => mp_pick($r, ['perubahan terakhir', 'last update', 'updated']),
            'mpIds'     => $mpIds,
        ];
    }
    return $out;
}

/**
 * Parse tanggal fleksibel → 'Y-m-d H:i:s' (atau null). Mendukung:
 *  - "21 Jun 26, 00:56" (singkatan bulan ID/EN)
 *  - ISO "2026-06-21" / "2026-06-21 09:30"
 *  - "21/06/2026" / "21-06-2026" (DD/MM/YYYY, gaya Indonesia)
 */
function mp_flex_date(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') return null;

    // Singkatan bulan: "21 Jun 26, 00:56"
    if (preg_match('/(\d{1,2})\s+([A-Za-z]{3,4})\s+(\d{2,4}),?\s+(\d{1,2}):(\d{2})/', $s, $m)) {
        $months = ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mei'=>5,'may'=>5,'jun'=>6,'jul'=>7,
            'agu'=>8,'ags'=>8,'agt'=>8,'aug'=>8,'sep'=>9,'okt'=>10,'oct'=>10,'nov'=>11,'des'=>12,'dec'=>12];
        $mon = $months[strtolower(substr($m[2], 0, 3))] ?? null;
        if ($mon) {
            $yr = (int) $m[3]; if ($yr < 100) $yr += 2000;
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $yr, $mon, (int) $m[1], (int) $m[4], (int) $m[5]);
        }
    }
    // ISO: 2026-06-21 [09:30[:00]]
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2}))?/', $s, $m)) {
        return sprintf('%04d-%02d-%02d %02d:%02d:00', (int) $m[1], (int) $m[2], (int) $m[3], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0));
    }
    // DD/MM/YYYY atau DD-MM-YYYY [HH:MM] (gaya Indonesia)
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{2,4})(?:\s+(\d{1,2}):(\d{2}))?#', $s, $m)) {
        $yr = (int) $m[3]; if ($yr < 100) $yr += 2000;
        return sprintf('%04d-%02d-%02d %02d:%02d:00', $yr, (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0));
    }
    return null;
}

/**
 * Parser KATALOG GENERIK (sumber apa pun) — CSV atau XLSX. Toleran nama kolom:
 * SKU, Nama, HPP/Harga/Modal/Cost, (opsional) Modal Dropship, (opsional) Tanggal.
 */
function mp_generic_catalog(string $path, string $name): array
{
    $ext = strtolower(pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'txt') {
        $assoc = mp_read_csv($path);
    } else {
        $assoc = [];
        foreach (xlsx_read($path) as $rows) {
            $hi = mp_header_index($rows, ['sku'], 8);
            if ($hi < 0) $hi = mp_header_index($rows, ['kode sku'], 8);
            if ($hi < 0) $hi = mp_header_index($rows, ['kode produk'], 8);
            if ($hi >= 0) { $assoc = mp_assoc_rows($rows, $hi); break; }
        }
    }
    $out = [];
    foreach ($assoc as $r) {
        $sku = mp_pick($r, ['kode sku', 'sku', 'kode produk', 'kode']);
        if (!$sku) continue;
        $out[] = [
            'sku'          => $sku,
            'name'         => mp_pick($r, ['nama produk', 'nama', 'product name', 'name']) ?: $sku,
            'cost'         => mp_num(mp_pick($r, ['hpp', 'harga modal', 'modal', 'harga', 'cost price', 'cost', 'price'])),
            'dropshipCost' => mp_num(mp_pick($r, ['modal dropship', 'biaya dropship', 'harga dropship', 'dropship cost'])),
            'changedAt'    => mp_pick($r, ['tanggal', 'tgl', 'perubahan terakhir', 'last update', 'updated', 'date']),
            'mpIds'        => [],
        ];
    }
    return $out;
}

/**
 * Parser BIAYA DROPSHIP MANUAL (CSV/XLSX) — daftar buatan seller sendiri.
 * Kolom: No. Pesanan (cocok ke external_no marketplace), Biaya Dropship (total).
 * Opsional: Modal Produk (untuk hitung "seolah packing sendiri"). Format peta = mp_dropship_report.
 */
function mp_generic_dropship(string $path, string $name): array
{
    $ext = strtolower(pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'txt') {
        $assoc = mp_read_csv($path);
    } else {
        $assoc = [];
        foreach (xlsx_read($path) as $rows) {
            foreach (['no. pesanan', 'no pesanan', 'nomor pesanan', 'kode invoice channel', 'order id', 'invoice'] as $col) {
                $hi = mp_header_index($rows, [$col], 8);
                if ($hi >= 0) { $assoc = mp_assoc_rows($rows, $hi); break 2; }
            }
        }
    }
    $map = [];
    foreach ($assoc as $r) {
        $inv = mp_pick($r, ['no. pesanan', 'no pesanan', 'nomor pesanan', 'kode invoice channel', 'order id', 'order sn', 'invoice', 'kode pesanan']);
        if (!$inv) continue;
        if (stripos($inv, 'HAPUS-BARIS') !== false) continue; // baris contoh dari template Unduh Format
        // "Biaya Dropship" = TOTAL bayar ke supplier (harga produk + biaya/ongkir). mp_pick_like agar
        // header panjang spt "Biaya Dropship (total bayar ke supplier)" tetap cocok.
        $total = mp_num(mp_pick_like($r, 'biaya dropship') ?? mp_pick($r, ['total dropship', 'total transaksi', 'total biaya', 'total', 'biaya']));
        // "Modal Produk" = harga produk SAJA (utk hitung "seandainya packing sendiri").
        $modal = mp_num(mp_pick_like($r, 'modal produk') ?? mp_pick($r, ['total harga produk', 'harga produk', 'modal', 'hpp']));
        if ($total <= 0 && $modal <= 0) continue;
        if ($total <= 0) $total = $modal;           // hanya modal diberikan
        if ($modal <= 0) $modal = $total;           // hanya total → dropship_modal = total (aman, tak ada penghematan palsu)
        if (isset($map[$inv])) {
            $map[$inv]['total'] += $total; $map[$inv]['productCost'] += $modal;
        } else {
            $map[$inv] = ['total' => $total, 'productCost' => $modal, 'partnerFee' => 0, 'additional' => 0, 'jakmallCode' => ''];
        }
    }
    return $map;
}

// ---------- Adapter: Laporan Pesanan Dropship (deteksi dropship + biaya) ----------
// Kembalikan peta: No. Pesanan channel (Shopee) => biaya dropship Dropship.
// "Kode Invoice Channel" = nomor pesanan marketplace. Total Transaksi sudah
// termasuk Biaya Mitra Dropship + Biaya Tambahan. Satu pesanan channel bisa
// punya >1 baris (digabung/dijumlahkan).
function mp_dropship_report(array $assoc): array
{
    $map = [];
    foreach ($assoc as $r) {
        $inv = mp_pick($r, ['kode invoice channel']);
        if (!$inv) continue;
        $product = mp_num(mp_pick($r, ['total harga produk']));
        $partner = mp_num(mp_pick_like($r, 'biayamitra') ?? mp_pick($r, ['biaya partner', 'biaya supplier']));
        $extra   = mp_num(mp_pick($r, ['biaya tambahan']));
        $total   = mp_num(mp_pick($r, ['total transaksi']));
        if ($total <= 0) $total = $product + $partner + $extra;
        if (isset($map[$inv])) {
            $map[$inv]['productCost'] += $product;
            $map[$inv]['partnerFee']  += $partner;
            $map[$inv]['additional']  += $extra;
            $map[$inv]['total']       += $total;
        } else {
            $map[$inv] = [
                'dropshipCode' => mp_pick($r, ['kode pesanan']),
                'store'       => mp_pick($r, ['nama toko']),
                'productCost' => $product,
                'partnerFee'  => $partner,
                'additional'  => $extra,
                'total'       => $total,
            ];
        }
    }
    return $map;
}

// ---------- Adapter: Laporan Penghasilan Shopee (sheet Income + Seller Fee) ----------
// Menghasilkan pesanan ternormalisasi dengan biaya yang sudah dipetakan ke
// ember (admin/voucher/ongkir) dan SELISIH direkonsiliasi ke other_cost agar
// laba bersih (sebelum HPP) persis = "Total Penghasilan".
function mp_income_to_orders(array $incomeAssoc, array $itemsByOrder = []): array
{
    $C = MP_SHOPEE_INCOME;
    $orders = [];
    foreach ($incomeAssoc as $r) {
        $no = mp_pick($r, $C['externalNo']);
        if (!$no) continue;

        $revenue = mp_num(mp_pick($r, $C['productRevenue']));
        $net = mp_num(mp_pick($r, $C['totalIncome']));
        $refund = mp_num(mp_pick($r, $C['refund']));
        // Pesanan retur/refund tetap DIDATA dengan status Dikembalikan (jangan dibuang).
        $isReturn = $refund != 0 || ($revenue <= 0 && $net <= 0);

        $admin   = mp_abs_sum($r, $C['platformFees']);
        $voucher = mp_abs_sum($r, $C['sellerDiscounts']);
        // Ongkir TIDAK dipecah terpisah: komponen ongkir (mis. "Ongkir Dibayar
        // Pembeli" yang diteruskan ke kurir) bersifat pass-through dan sudah
        // ternetto di "Total Penghasilan", jadi memecahnya hanya menimbulkan
        // angka +/− yang saling meniadakan. Sisa potongan jatuh ke other.
        $totalDeduction = $revenue - $net;
        $other = $totalDeduction - ($admin + $voucher);

        // Item dari Seller Fee hanya punya nama (tanpa harga/qty/SKU). Untuk
        // pesanan 1 item, isi harga = omzet agar tidak tampil Rp0 (kasus umum).
        // Detail lengkap (qty/SKU/harga per item) menyusul bila file Order
        // Completed diimpor untuk periode yang sama (lihat mp_merge_orders).
        $its = $itemsByOrder[$no] ?? [];
        if (count($its) === 1 && (float) $its[0]['unitPrice'] === 0.0) {
            $its[0]['unitPrice'] = $revenue;
        }

        $orders[$no] = [
            'externalNo' => $no,
            'orderDate'  => mp_pick($r, $C['orderDate']),
            'status'     => $isReturn ? 'RETURNED' : 'COMPLETED',
            'buyerName'  => mp_pick($r, $C['buyerName']),
            'shippingChargedToBuyer' => mp_num(mp_pick($r, $C['shippingToBuyer'])),
            'adminFee'   => $admin,
            'shippingCostSeller' => 0.0,
            'voucherSellerBorne' => $voucher,
            'otherIncome' => 0.0,
            'otherCost'  => $other,
            'productRevenue' => $revenue,
            'items'      => $its,
            '_hasIncome' => true,
        ];
    }
    return $orders;
}

// Sheet "Seller Fee" Shopee: baris berpasangan Order/Sku; baris "Sku" memuat
// Nama Produk & ID Produk per pesanan (tanpa SKU penjual / qty).
function mp_sellerfee_items(array $rows): array
{
    $hi = mp_header_index($rows, ['no. pesanan', 'nama produk'], 8);
    if ($hi < 0) return [];
    $assoc = mp_assoc_rows($rows, $hi);
    $byOrder = [];
    foreach ($assoc as $r) {
        $no = mp_pick($r, ['no. pesanan']);
        $name = mp_pick($r, ['nama produk']);
        if (!$no || !$name || $name === '-') continue;
        $pid = mp_pick($r, ['id produk', 'product id']);
        $byOrder[$no][] = [
            'sku'        => null,
            'shopeeId'   => ($pid && $pid !== '-') ? $pid : null,
            'name'       => $name,
            'qty'        => 1,
            'qtyAssumed' => true, // Laporan Penghasilan tak memuat qty -> asumsi 1
            'unitPrice'  => 0.0,
        ];
    }
    return $byOrder;
}

// ---------- Adapter: Laporan Penghasilan Tokopedia/TikTok (sheet Detail pesanan) ----------
// Satu baris = satu pesanan (tanpa item). Omzet = "Total Pendapatan", uang bersih
// = "Jumlah penyelesaian pembayaran"; biaya dilebur ke admin + direkonsiliasi agar
// laba (sebelum modal) persis = penyelesaian. Item (SKU/qty) menyusul dari CSV
// "Selesai pesanan".
function mp_tiktok_income_to_orders(array $assoc): array
{
    $C = MP_TIKTOK_INCOME;
    $orders = [];
    foreach ($assoc as $r) {
        $type = mp_pick($r, $C['txType']);
        // Lewati baris PENYESUAIAN/ADJUSTMENT; simpan baris pesanan ("Pesanan" ID / "Order" EN).
        if ($type !== null && (stripos($type, 'penyesuaian') !== false || stripos($type, 'adjustment') !== false)) continue;
        $no = mp_pick($r, $C['externalNo']);
        if (!$no) continue;
        $revenue = mp_num(mp_pick($r, $C['revenue']));
        $net = mp_num(mp_pick($r, $C['net']));
        $refund = mp_num(mp_pick($r, $C['refund']));
        // Pesanan retur/refund (ada pengembalian dana, atau omzet & net <= 0):
        // TETAP didata dengan status Dikembalikan, JANGAN dibuang.
        $isReturn = $refund != 0 || ($revenue <= 0 && $net <= 0);
        // Tampilkan omzet asli pesanan (sebelum refund) agar tidak Rp0/kosong.
        if ($revenue == 0) $revenue = mp_num(mp_pick($r, $C['origValue']));
        $admin = abs(mp_num(mp_pick($r, $C['totalFees'])));
        $other = ($revenue - $net) - $admin; // rekonsiliasi
        $orders[$no] = [
            'externalNo' => $no,
            'orderDate'  => mp_pick($r, $C['orderDate']),
            'status'     => $isReturn ? 'RETURNED' : 'COMPLETED',
            'buyerName'  => null,
            'shippingChargedToBuyer' => 0.0,
            'adminFee'   => $admin,
            'shippingCostSeller' => 0.0,
            'voucherSellerBorne' => 0.0,
            'otherIncome' => 0.0,
            'otherCost'  => $other,
            'productRevenue' => $revenue,
            'items'      => [],
            '_hasIncome' => true,
        ];
    }
    return array_values($orders);
}

// ---------- Dispatcher: baca satu file -> {type, payload} ----------
// type: 'catalog' | 'orders' (ternormalisasi siap merge) | 'unknown'
function mp_read_file(string $path, string $origName = ''): array
{
    $ext = strtolower(pathinfo($origName !== '' ? $origName : $path, PATHINFO_EXTENSION));

    if ($ext === 'csv' || $ext === 'txt') {
        $rows = mp_read_csv($path);
        // CSV "Pesanan Selesai" Tokopedia/TikTok punya kolom Order ID + Seller SKU.
        $mk = (count($rows) && isset($rows[0]['orderid']) && isset($rows[0]['sellersku'])) ? 'TIKTOKTOKO' : null;
        return ['type' => 'orders', 'orders' => mp_rows_to_orders($rows), 'source' => 'csv', 'marketplace' => $mk];
    }

    $sheets = xlsx_read($path);
    if (!$sheets) return ['type' => 'unknown'];

    // 1) Laporan Pesanan Dropship (deteksi dropship)?
    foreach ($sheets as $rows) {
        $hi = mp_header_index($rows, ['kode invoice channel', 'total transaksi'], 5);
        if ($hi >= 0) {
            return ['type' => 'dropship_report', 'dropship' => mp_dropship_report(mp_assoc_rows($rows, $hi)), 'source' => 'dropship_report'];
        }
    }

    // 2) Master Produk Dropship?
    foreach ($sheets as $rows) {
        $hi = mp_header_index($rows, ['kode sku', 'harga'], 5);
        if ($hi >= 0) {
            return ['type' => 'catalog', 'products' => mp_catalog_products(mp_assoc_rows($rows, $hi)), 'source' => 'catalog'];
        }
    }

    // 2) Laporan Penghasilan Shopee (Income + Seller Fee)?
    foreach ($sheets as $name => $rows) {
        $hi = mp_header_index($rows, ['no. pesanan', 'total penghasilan'], 12);
        if ($hi >= 0) {
            // Cari sheet rincian produk berdasarkan ISI (punya kolom No. Pesanan
            // + Nama Produk), bukan nama sheet — Shopee memakai nama berbeda
            // ("Seller Fee", "Order Processing Fee", dll), dan ada sheet "fee"
            // lain ("Service Fee Details") yang TIDAK memuat nama produk.
            $items = [];
            foreach ($sheets as $sn => $sr) {
                if (mp_header_index($sr, ['no. pesanan', 'nama produk'], 8) >= 0) {
                    $items = mp_sellerfee_items($sr);
                    if ($items) break;
                }
            }
            $orders = mp_income_to_orders(mp_assoc_rows($rows, $hi), $items);
            return ['type' => 'orders', 'orders' => array_values($orders), 'source' => 'shopee_income', 'marketplace' => 'SHOPEE'];
        }
    }

    // 3) Shopee Order Lengkap (punya No. Pesanan + Nomor Referensi SKU)?
    foreach ($sheets as $rows) {
        $hi = mp_header_index($rows, ['no. pesanan', 'nomor referensi sku'], 5);
        if ($hi >= 0) {
            return ['type' => 'orders', 'orders' => mp_rows_to_orders(mp_assoc_rows($rows, $hi)), 'source' => 'shopee_order', 'marketplace' => 'SHOPEE'];
        }
    }

    // 4) Laporan Penghasilan Tokopedia/TikTok (sheet "Detail pesanan" / "Order details") — ID & EN.
    foreach ($sheets as $rows) {
        $hi = mp_header_index($rows, ['id pesanan/penyesuaian', 'total pendapatan'], 5);
        if ($hi < 0) $hi = mp_header_index($rows, ['order/adjustment id', 'total revenue'], 5);
        if ($hi >= 0) {
            return ['type' => 'orders', 'orders' => mp_tiktok_income_to_orders(mp_assoc_rows($rows, $hi)), 'source' => 'tiktok_income', 'marketplace' => 'TIKTOKTOKO'];
        }
    }

    // 4) Generik: sheet pertama, cari baris header bernomor pesanan.
    foreach ($sheets as $rows) {
        $hi = mp_header_index($rows, ['no. pesanan'], 10);
        if ($hi < 0) $hi = mp_header_index($rows, ['order id'], 10);
        if ($hi >= 0) {
            return ['type' => 'orders', 'orders' => mp_rows_to_orders(mp_assoc_rows($rows, $hi)), 'source' => 'generic_xlsx'];
        }
    }

    return ['type' => 'unknown'];
}

// Gabungkan pesanan dari beberapa sumber berdasarkan nomor pesanan. Sumber yang
// mengandung biaya riil (Income) menang untuk angka finansial; sumber dengan
// item (Order Lengkap) menang untuk daftar item/SKU/qty.
/**
 * Gabungkan status saat re-import: MAJU & TERMINAL diterapkan; MUNDUR diabaikan
 * (lindungi dari file lama yang diunggah belakangan). Status terminal lama (batal/retur)
 * bersifat final — tak ditimpa. Mengembalikan status kanonik.
 * Urutan: PENDING < PAID < SHIPPED < COMPLETED; CANCELLED/RETURNED = terminal.
 */
function mp_merge_status(?string $old, ?string $new): string
{
    $oldC = mp_map_status((string) ($old !== null && $old !== '' ? $old : 'PAID'));
    $newRaw = trim((string) $new);
    if ($newRaw === '') return $oldC; // tak ada info status baru → pertahankan lama
    $newC = mp_map_status($newRaw);

    $terminal = ['CANCELLED' => true, 'RETURNED' => true];
    if (isset($terminal[$oldC])) return $oldC; // status terminal lama final, jangan ditimpa
    if (isset($terminal[$newC])) return $newC; // batal/retur baru selalu menang

    $rank = ['PENDING' => 1, 'PAID' => 2, 'SHIPPED' => 3, 'COMPLETED' => 4];
    return ($rank[$newC] ?? 0) > ($rank[$oldC] ?? 0) ? $newC : $oldC; // hanya maju
}

function mp_merge_orders(array $sources): array
{
    $merged = [];
    foreach ($sources as $orders) {
        foreach ($orders as $o) {
            $no = $o['externalNo'];
            if (!isset($merged[$no])) { $merged[$no] = $o; continue; }
            $cur = $merged[$no];

            // Item: pilih yang lebih kaya berdasar skor (SKU + qty pasti). Item dari
            // file pesanan (qty asli) mengalahkan item Laporan Penghasilan (qty asumsi).
            $os = mp_items_score($o['items']);
            $cs = mp_items_score($cur['items']);
            if ($os > $cs) {
                $cur['items'] = $o['items'];
            } elseif ($os === $cs && !mp_items_have_sku($cur['items']) && !empty($o['items'])) {
                // Sama-sama belum ber-SKU: pilih yang punya ID Produk (bisa diresolusi)
                // atau yang itemnya lebih banyak.
                $oHasId = mp_items_have_shopeeId($o['items']);
                $curHasId = mp_items_have_shopeeId($cur['items']);
                if (($oHasId && !$curHasId) || count($o['items']) > count($cur['items'])) {
                    $cur['items'] = $o['items'];
                }
            }

            // Finansial: sumber dengan Income menang.
            if (!empty($o['_hasIncome']) && empty($cur['_hasIncome'])) {
                foreach (['productRevenue', 'adminFee', 'shippingCostSeller', 'voucherSellerBorne',
                             'otherIncome', 'otherCost', 'shippingChargedToBuyer', '_hasIncome'] as $k) {
                    $cur[$k] = $o[$k] ?? ($cur[$k] ?? null);
                }
            }
            // Lengkapi field yang kosong dari sumber lain.
            foreach (['orderDate', 'buyerName'] as $k) {
                if (empty($cur[$k]) && !empty($o[$k])) $cur[$k] = $o[$k];
            }
            // Status: MAJU & TERMINAL diterapkan, MUNDUR diabaikan (lihat mp_merge_status).
            $cur['status'] = mp_merge_status($cur['status'] ?? null, $o['status'] ?? null);
            $merged[$no] = $cur;
        }
    }
    return array_values($merged);
}

function mp_items_have_sku(array $items): bool
{
    foreach ($items as $it) {
        if (!empty($it['sku'])) return true;
    }
    return false;
}

function mp_items_have_shopeeId(array $items): bool
{
    foreach ($items as $it) {
        if (!empty($it['shopeeId'])) return true;
    }
    return false;
}

function mp_items_qty_assumed(array $items): bool
{
    foreach ($items as $it) {
        if (!empty($it['qtyAssumed'])) return true;
    }
    return false;
}

// Skor kekayaan daftar item (makin tinggi makin dipercaya):
// +2 ada SKU, +1 qty pasti (bukan asumsi & ada item). Dipakai saat merge agar
// item dari file pesanan (SKU + qty asli) mengalahkan item dari Laporan
// Penghasilan (SKU hasil resolusi tapi qty diasumsikan 1).
function mp_items_score(array $items): int
{
    $s = 0;
    if (mp_items_have_sku($items)) $s += 2;
    if (!empty($items) && !mp_items_qty_assumed($items)) $s += 1;
    return $s;
}
