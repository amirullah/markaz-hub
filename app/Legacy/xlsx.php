<?php
// Pembaca file .xlsx tanpa library eksternal (cukup ZipArchive + parsing string),
// supaya jalan di shared hosting biasa. Mengembalikan tiap sheet sebagai array
// baris; tiap baris array nilai sel terindeks kolom (mulai 0). Nilai dikembalikan
// sebagai string apa adanya (tanggal Shopee/Dropship memang disimpan sebagai teks).

// Ubah huruf kolom Excel (A, B, ..., AA) menjadi indeks 0-based.
function xlsx_col_index(string $ref): int
{
    if (!preg_match('/^([A-Z]+)/', $ref, $m)) return 0;
    $letters = $m[1];
    $n = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

// Ekstrak teks dari satu blok <si> shared string (gabung semua <t>, dukung rich text).
function xlsx_si_text(string $si): string
{
    if (!preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $m)) {
        return '';
    }
    return html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Baca shared strings menjadi array terindeks.
function xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) return [];
    $out = [];
    // Pisahkan per <si> ... </si> (juga bentuk kosong <si/>).
    if (preg_match_all('/<si>(.*?)<\/si>|<si\/>/s', $xml, $m, PREG_SET_ORDER)) {
        foreach ($m as $one) {
            $out[] = isset($one[1]) ? xlsx_si_text($one[1]) : '';
        }
    }
    return $out;
}

// Petakan nama sheet -> path file XML (lewat workbook.xml + rels), karena urutan
// nama file (sheet1/2/3) tidak selalu sama dengan urutan tampilan.
function xlsx_sheet_targets(ZipArchive $zip): array
{
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb === false) return [];
    $ridToTarget = [];
    if ($rels !== false && preg_match_all('/<Relationship\b[^>]*>/', $rels, $rm)) {
        foreach ($rm[0] as $tag) {
            if (preg_match('/Id="([^"]+)"/', $tag, $a) && preg_match('/Target="([^"]+)"/', $tag, $b)) {
                $ridToTarget[$a[1]] = $b[1];
            }
        }
    }
    $targets = [];
    if (preg_match_all('/<sheet\b[^>]*>/', $wb, $sm)) {
        foreach ($sm[0] as $tag) {
            if (!preg_match('/name="([^"]+)"/', $tag, $nm)) continue;
            $name = html_entity_decode($nm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $rid = preg_match('/r:id="([^"]+)"/', $tag, $rm2) ? $rm2[1] : '';
            $target = $ridToTarget[$rid] ?? '';
            if ($target === '') continue;
            $target = ltrim($target, '/');
            if (strpos($target, 'xl/') !== 0) $target = 'xl/' . $target;
            $targets[$name] = $target;
        }
    }
    return $targets;
}

// Parse satu sheet XML menjadi array baris (hemat memori: per-baris via regex).
function xlsx_parse_sheet(string $xml, array $shared): array
{
    $rows = [];
    if (!preg_match_all('/<row\b[^>]*>.*?<\/row>|<row\b[^>]*\/>/s', $xml, $rm)) {
        return $rows;
    }
    foreach ($rm[0] as $rowXml) {
        $cells = [];
        $max = -1;
        if (preg_match_all('/<c\b([^>]*)(?:\/>|>(.*?)<\/c>)/s', $rowXml, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                $attr = $c[1];
                $inner = $c[2] ?? '';
                $ref = preg_match('/r="([A-Z]+\d+)"/', $attr, $a) ? $a[1] : null;
                $type = preg_match('/t="([^"]+)"/', $attr, $t) ? $t[1] : '';
                $idx = $ref !== null ? xlsx_col_index($ref) : count($cells);

                $val = '';
                if ($type === 'inlineStr') {
                    if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $inner, $im)) {
                        $val = html_entity_decode(implode('', $im[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vm)) {
                    $raw = $vm[1];
                    if ($type === 's') {
                        $si = (int) $raw;
                        $val = $shared[$si] ?? '';
                    } else {
                        $val = html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                }
                $cells[$idx] = $val;
                if ($idx > $max) $max = $idx;
            }
        }
        // Normalisasi jadi array berurutan 0..max (sel kosong = '').
        $row = [];
        for ($i = 0; $i <= $max; $i++) {
            $row[$i] = $cells[$i] ?? '';
        }
        $rows[] = $row;
    }
    return $rows;
}

// API utama: baca file .xlsx -> ['NamaSheet' => [ [sel,...], ... ], ...].
// $only (opsional) membatasi sheet yang dibaca demi hemat memori.
function xlsx_read(string $path, ?array $only = null): array
{
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];
    // Pagar zip-bomb: laporan marketplace asli jauh di bawah batas ini setelah dekompresi.
    // File dengan isi terdekompresi tak wajar ditolak (dianggap bukan laporan) — mencegah
    // kehabisan memori saat getFromName memuat XML raksasa.
    $totalUncompressed = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        if ($st !== false) $totalUncompressed += (int) ($st['size'] ?? 0);
    }
    if ($totalUncompressed > 300 * 1024 * 1024) { $zip->close(); return []; }
    $shared = xlsx_shared_strings($zip);
    $targets = xlsx_sheet_targets($zip);
    $sheets = [];
    foreach ($targets as $name => $target) {
        if ($only !== null && !in_array($name, $only, true)) continue;
        $xml = $zip->getFromName($target);
        if ($xml === false) continue;
        $sheets[$name] = xlsx_parse_sheet($xml, $shared);
    }
    $zip->close();
    return $sheets;
}
