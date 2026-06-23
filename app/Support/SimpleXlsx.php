<?php

namespace App\Support;

use ZipArchive;

/**
 * Penulis .xlsx MINIMAL (tanpa dependency) untuk membuat file FORMAT/template impor.
 *
 * Kenapa bukan CSV: No. Pesanan marketplace = angka 16–18 digit. Bila template CSV
 * dibuka di Excel, angka sepanjang itu berubah jadi notasi ilmiah (mis. 5,79E+17)
 * dan presisinya HILANG → gagal dicocokkan. File .xlsx ini menandai kolom tertentu
 * (mis. No. Pesanan / Kode Produk) sebagai TEKS di tingkat KOLOM (`<col style>`),
 * sehingga angka panjang yang diketik/tempel pengguna tetap utuh.
 *
 * Pakai sharedStrings (bukan inlineStr) agar kompatibel dengan pembaca apa pun,
 * termასuk reader internal app (xlsx_read) bila file diunggah kembali tanpa diubah.
 */
class SimpleXlsx
{
    /**
     * @param string $path     Tujuan file .xlsx.
     * @param array  $headers  Baris judul (array string).
     * @param array  $rows     Baris data (array of array).
     * @param int[]  $textCols Indeks kolom (1-based) yang dipaksa format TEKS.
     */
    public static function write(string $path, array $headers, array $rows, array $textCols = []): void
    {
        $textCols = array_flip($textCols);
        $allRows = array_merge([$headers], $rows);

        // Kumpulkan shared strings (semua nilai non-numerik, atau numerik di kolom teks).
        $strings = [];
        $index = [];
        $addString = function (string $s) use (&$strings, &$index): int {
            if (! array_key_exists($s, $index)) {
                $index[$s] = count($strings);
                $strings[] = $s;
            }
            return $index[$s];
        };

        $sheetData = '';
        $r = 0;
        foreach ($allRows as $row) {
            $r++;
            $cells = '';
            $c = 0;
            foreach ($row as $val) {
                $c++;
                $ref = self::colLetter($c) . $r;
                $isTextCol = isset($textCols[$c]);
                $sAttr = $isTextCol ? ' s="1"' : '';
                $numeric = is_int($val) || is_float($val) || (is_string($val) && $val !== '' && is_numeric($val));

                if ($r > 1 && ! $isTextCol && $numeric) {
                    $cells .= '<c r="' . $ref . '"' . $sAttr . '><v>' . $val . '</v></c>';
                } else {
                    $si = $addString((string) $val);
                    $cells .= '<c r="' . $ref . '"' . $sAttr . ' t="s"><v>' . $si . '</v></c>';
                }
            }
            $sheetData .= '<row r="' . $r . '">' . $cells . '</row>';
        }

        // <cols>: kolom teks diberi style="1" (format Teks tingkat kolom) + lebar nyaman.
        $cols = '';
        for ($i = 1; $i <= count($headers); $i++) {
            $isTextCol = isset($textCols[$i]);
            $cols .= '<col min="' . $i . '" max="' . $i . '" width="' . ($isTextCol ? 28 : 18) . '" customWidth="1"'
                . ($isTextCol ? ' style="1"' : '') . '/>';
        }

        $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $sst .= '<si><t xml:space="preserve">' . self::esc($s) . '</t></si>';
        }
        $sst .= '</sst>';

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>' . $cols . '</cols><sheetData>' . $sheetData . '</sheetData></worksheet>';

        @unlink($path);
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::RELS);
        $zip->addFromString('xl/workbook.xml', self::WORKBOOK);
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->addFromString('xl/sharedStrings.xml', $sst);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    private static function colLetter(int $i): string // 1-based → A, B, ... Z, AA
    {
        $s = '';
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '</Types>';

    private const RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    private const WORKBOOK = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Format" sheetId="1" r:id="rId1"/></sheets></workbook>';

    private const WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '</Relationships>';

    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}
