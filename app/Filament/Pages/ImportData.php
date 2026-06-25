<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Services\Import\OrderImporter;
use App\Support\SimpleXlsx;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ImportData extends Page
{
    protected string $view = 'filament.pages.import-data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Import';

    protected static ?string $title = 'Impor Data';

    protected static ?int $navigationSort = 4;

    /** Hasil import per file (ditampilkan di view). */
    public ?array $report = null;

    public ?array $summary = null;

    /** Aturan validasi ekstensi file (dibungkus outer closure utk Filament v5). */
    private function extRule(): array
    {
        return [
            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                $files = is_array($value) ? $value : [$value];
                foreach ($files as $file) {
                    if (! $file instanceof \Illuminate\Http\UploadedFile) {
                        continue;
                    }
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
                        $fail('Format file "' . $file->getClientOriginalName() . '" tidak didukung. Gunakan Excel (.xlsx, .xls) atau CSV.');
                    }
                }
            },
        ];
    }

    // ====== 1) Laporan Marketplace (pesanan & penghasilan) ======
    public function importAction(): Action
    {
        return Action::make('import')
            ->label('Impor Laporan Marketplace')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('primary')
            ->extraAttributes(['class' => 'justify-center', 'style' => 'min-width:16rem'])
            // Butuh toko tujuan. Bila belum ada toko, tombol dinonaktifkan + pemandu tampil di halaman.
            ->disabled(fn (): bool => ! Store::query()->exists())
            ->modalHeading('Impor laporan marketplace')
            ->modalSubmitActionLabel('Impor sekarang')
            ->schema([
                Select::make('store_id')
                    ->label('Toko tujuan')
                    ->options(fn () => Store::query()->orderBy('name')->get()->mapWithKeys(fn (Store $s) => [
                        $s->id => $s->name . ' — ' . (match ($s->marketplace) {
                            'SHOPEE' => 'Shopee',
                            'TIKTOKTOKO' => 'Tokopedia/TikTok',
                            'TOKOPEDIA' => 'Tokopedia',
                            'TIKTOK' => 'TikTok',
                            default => $s->marketplace,
                        }),
                    ]))
                    ->required()
                    ->native(false)
                    ->helperText('Pilih toko sesuai channel file. File beda channel otomatis dilewati.'),
                FileUpload::make('files')
                    ->label('File laporan dari marketplace (.xlsx / .csv) — boleh beberapa')
                    ->multiple()
                    ->storeFiles(false)
                    ->helperText('Laporan penjualan/pesanan & penghasilan dari Shopee / Tokopedia / TikTok.')
                    ->rules($this->extRule())
                    ->required(),
            ])
            ->action(fn (array $data) => $this->runImport($data));
    }

    // ====== 2) Daftar Produk (katalog + harga modal) ======
    public function catalogAction(): Action
    {
        return Action::make('catalog')
            ->label('Impor Daftar Produk')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('primary')
            ->extraAttributes(['class' => 'justify-center', 'style' => 'min-width:16rem'])
            ->modalHeading('Impor daftar produk (katalog)')
            ->modalSubmitActionLabel('Impor daftar produk')
            ->schema([
                FileUpload::make('file')
                    ->label('File daftar produk (Excel .xlsx atau CSV)')
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Kolom wajib: Kode Produk (SKU), Harga Modal. Opsional: Nama Produk, Tanggal.')
                    ->rules($this->extRule()),
                TextInput::make('supplier_name')
                    ->label('Produk ini dari mana? (nama supplier)')
                    ->default('Stok Sendiri')
                    ->required()
                    ->helperText('Mis. "Stok Sendiri", "Supplier A". Dibuat otomatis jika belum ada.'),
                Toggle::make('update_old_hpp_dated')
                    ->label('Sesuaikan HPP pesanan lama SESUAI TANGGAL pesanan (pakai riwayat harga)')
                    ->helperText('Pilih bila file memuat perubahan harga ber-tanggal LAMPAU (mundur/backdated): tiap pesanan lama dihitung ulang memakai harga modal yang BERLAKU saat tanggalnya. Paling akurat. (HPP yang diedit manual akan tertimpa.)'),
                Toggle::make('update_old_hpp')
                    ->label('Atau: samakan SEMUA HPP pesanan lama dengan harga modal TERBARU ini')
                    ->helperText('Timpa HPP semua pesanan lama dengan harga terkini, ABAIKAN tanggal. Jarang dibutuhkan. Default (keduanya mati): HPP pesanan lama tidak diubah, hanya yang belum terisi.'),
            ])
            ->action(fn (array $data) => $this->runCatalogImport($data));
    }

    // ====== 3) Dropship (biaya dropship per pesanan) ======
    public function dropshipAction(): Action
    {
        return Action::make('dropship')
            ->label('Impor Dropship')
            ->icon(Heroicon::OutlinedTruck)
            ->color('primary')
            ->extraAttributes(['class' => 'justify-center', 'style' => 'min-width:16rem'])
            ->modalHeading('Impor biaya dropship (per pesanan)')
            ->modalDescription('Biaya Dropship = TOTAL yang Anda bayar ke supplier untuk pesanan itu (sudah termasuk harga produk + ongkir/biaya) — bukan hanya selisih/biayanya. Kolom Modal Produk (harga produk saja) opsional. Urutan impor BEBAS: bila pesanannya belum ada, biayanya tetap tersimpan & otomatis terpasang saat pesanan itu diimpor.')
            ->modalSubmitActionLabel('Impor dropship')
            ->schema([
                FileUpload::make('file')
                    ->label('File dropship (Excel .xlsx atau CSV)')
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Boleh: laporan dari penyedia dropship, ATAU file isian dari "Unduh Format File" (No. Pesanan + Biaya Dropship).')
                    ->rules($this->extRule()),
            ])
            ->action(fn (array $data) => $this->runDropshipImport($data));
    }

    /** Unduh template/format file dropship manual (Excel .xlsx, kolom No. Pesanan = TEKS). */
    public function downloadTemplateAction(): Action
    {
        return Action::make('downloadTemplate')
            ->label('Unduh Format File')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->link()
            ->action(fn () => $this->xlsxTemplate(
                'format-biaya-dropship.xlsx',
                ['No. Pesanan', 'Biaya Dropship (total bayar ke supplier)', 'Modal Produk (harga produk saja, opsional)'],
                [['CONTOH-HAPUS-BARIS-INI', 50000, 45000]],
                [1], // No. Pesanan dipaksa TEKS (angka panjang tak rusak di Excel)
            ));
    }

    /** Unduh template/format daftar produk (Excel .xlsx, kolom Kode Produk = TEKS). */
    public function downloadCatalogTemplateAction(): Action
    {
        return Action::make('downloadCatalogTemplate')
            ->label('Unduh Format File')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->link()
            ->action(fn () => $this->xlsxTemplate(
                'format-daftar-produk.xlsx',
                ['Kode Produk', 'Nama Produk', 'Harga Modal', 'Tanggal'],
                [['CONTOH-HAPUS-BARIS-INI', 'Nama Produk Contoh', 25000, '2026-06-23']],
                [1], // Kode Produk dipaksa TEKS
            ));
    }

    /** Bangun file .xlsx template sementara lalu kirim sebagai unduhan. */
    private function xlsxTemplate(string $filename, array $headers, array $rows, array $textCols)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mkztpl') . '.xlsx';
        SimpleXlsx::write($tmp, $headers, $rows, $textCols);

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    protected function runImport(array $data): void
    {
        $store = Store::findOrFail($data['store_id']);
        $files = collect($data['files'] ?? [])
            ->map(fn ($tmp) => ['name' => $tmp->getClientOriginalName(), 'path' => $tmp->getRealPath()])
            ->filter(fn ($f) => $f['path'])->values()->all();

        if (! $files) {
            Notification::make()->title('Belum ada file dipilih')->warning()->send();
            return;
        }

        $result = (new OrderImporter((int) $store->organization_id))->importFiles($files, (int) $store->id, $store->marketplace);
        $this->report = $result['report'];
        $this->summary = $result['summary'];
        \App\Support\DashboardCache::forget((int) $store->organization_id); // data berubah → dashboard segar

        $ok = collect($this->report)->where('ok', true)->count();
        $fail = collect($this->report)->where('ok', false)->count();
        $orders = $result['summary']['orders'] ?? null; // "Pesanan: X baru, Y diperbarui, Z tetap."
        $skips = collect($this->report)->where('ok', false)
            ->map(fn ($r) => $r['name'] . ' — ' . ($r['reason'] ?? ''))->values()->all();

        $lines = [];
        if ($ok > 0) {
            $lines[] = '✅ ' . ($orders ?: "{$ok} file diproses.");
        } elseif ($fail > 0) {
            $lines[] = '❌ Tidak ada laporan pesanan yang diproses. File yang diunggah sepertinya bukan laporan marketplace.';
        }
        if ($skips) {
            $lines[] = '⏭️ ' . count($skips) . ' file dilewati:';
            foreach (array_slice($skips, 0, 4) as $s) {
                $lines[] = '• ' . $s;
            }
            if (count($skips) > 4) {
                $lines[] = '… dan ' . (count($skips) - 4) . ' lainnya (lihat detail per file di halaman).';
            }
        }

        $title = match (true) {
            $ok > 0 && $fail === 0 => "Berhasil — {$ok} file diproses",
            $ok > 0 && $fail > 0 => "Sebagian berhasil — {$ok} diproses, {$fail} dilewati",
            default => "Gagal — {$fail} file dilewati",
        };
        $type = $ok > 0 ? ($fail ? 'warning' : 'success') : 'danger';

        $this->notify($title, $lines, $type);
    }

    protected function runCatalogImport(array $data): void
    {
        $file = $data['file'] ?? null;
        if (! $file || ! $file->getRealPath()) {
            Notification::make()->title('Belum ada file dipilih')->warning()->send();
            return;
        }

        $res = (new OrderImporter((int) auth()->user()->organization_id))->importCatalogFile(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            (string) ($data['supplier_name'] ?? ''),
            false, // bukan dropship — dropship punya menu sendiri
            (bool) ($data['update_old_hpp'] ?? false),
            (bool) ($data['update_old_hpp_dated'] ?? false),
        );

        if (! ($res['ok'] ?? false)) {
            $this->notify('Impor daftar produk gagal', ['Penyebab: ' . ($res['reason'] ?? 'Format file tidak dikenali.')], 'danger');
            return;
        }
        \App\Support\DashboardCache::forget((int) auth()->user()->organization_id); // HPP berubah → dashboard segar

        $lines = ["✅ {$res['ins']} produk baru, {$res['upd']} diperbarui.", "Supplier: {$res['supplier']}."];
        if ($res['changes']) {
            $lines[] = "{$res['changes']} produk berubah harga modal.";
        }
        if ($res['skipped']) {
            $lines[] = "{$res['skipped']} produk dilewati — tanggal di file lebih lama dari data tersimpan (harga baru tidak ditimpa).";
        }
        if (($res['ins'] ?? 0) === 0 && ($res['upd'] ?? 0) === 0 && ! $res['skipped']) {
            $lines[] = 'Catatan: tidak ada produk yang masuk. Pastikan file punya kolom Kode Produk (SKU) & Harga Modal.';
        }

        $this->notify('Daftar produk berhasil diimpor', $lines, 'success');
    }

    protected function runDropshipImport(array $data): void
    {
        $file = $data['file'] ?? null;
        if (! $file || ! $file->getRealPath()) {
            Notification::make()->title('Belum ada file dipilih')->warning()->send();
            return;
        }

        $res = (new OrderImporter((int) auth()->user()->organization_id))
            ->importDropshipFile($file->getRealPath(), $file->getClientOriginalName());

        if (! ($res['ok'] ?? false)) {
            $this->notify('Impor dropship gagal', ['Penyebab: ' . ($res['reason'] ?? 'Format file tidak dikenali.')], 'danger');
            return;
        }
        \App\Support\DashboardCache::forget((int) auth()->user()->organization_id); // biaya dropship berubah → dashboard segar

        $lines = [
            "✅ {$res['rows']} baris biaya dropship TERSIMPAN permanen.",
            "{$res['matched']} cocok dengan pesanan yang sudah ada — {$res['updated']} pesanan ditandai dropship & biayanya terisi.",
        ];
        if ($res['notfound']) {
            $lines[] = "ℹ️ {$res['notfound']} No. Pesanan belum ada pesanannya — biayanya TETAP TERSIMPAN dan otomatis terpasang begitu pesanan itu diimpor lewat \"Laporan Marketplace\". Urutan impor bebas.";
        }
        if (($res['updated'] ?? 0) === 0 && ($res['matched'] ?? 0) > 0) {
            $lines[] = 'Semua pesanan yang cocok sudah sesuai (tidak ada perubahan).';
        }

        $this->notify('Biaya dropship berhasil diimpor', $lines, 'success');
    }

    /** Kirim notifikasi (toast + lonceng) dgn body multi-baris yg jelas. */
    private function notify(string $title, array $lines, string $type): void
    {
        \App\Support\Bell::send(Notification::make()
            ->title($title)
            ->body(new HtmlString(implode('<br>', array_map('e', $lines))))
            ->{$type}());
    }
}
