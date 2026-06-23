<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Services\Import\OrderImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ImportData extends Page
{
    protected string $view = 'filament.pages.import-data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Import';

    protected static ?string $title = 'Import Laporan';

    protected static ?int $navigationSort = 4;

    /** Hasil import per file (ditampilkan di view). */
    public ?array $report = null;

    public ?array $summary = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Impor Pesanan & Laporan')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->modalHeading('Impor pesanan & laporan dari marketplace')
                ->modalDescription('Untuk file yang Anda DOWNLOAD dari Shopee / Tokopedia / TikTok: laporan pesanan, laporan penghasilan, atau laporan dropship. BUKAN untuk daftar produk Anda — itu pakai tombol "Impor Daftar Produk".')
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
                        ->helperText('Pilih toko sesuai channel file (nama toko + channel ditampilkan). File beda channel otomatis dilewati.'),
                    FileUpload::make('files')
                        ->label('File laporan dari marketplace (.xlsx / .csv) — boleh beberapa sekaligus')
                        ->multiple()
                        ->storeFiles(false)
                        ->helperText('File hasil download dari Shopee / Tokopedia / TikTok (Excel atau CSV). File yang tidak cocok dengan toko otomatis dilewati — tidak menggagalkan yang lain.')
                        // Validasi berdasarkan EKSTENSI (pasti) — bukan MIME browser yang rapuh
                        // (Windows sering melaporkan .xlsx sebagai application/x-zip-compressed).
                        // Validasi ekstensi. Dibungkus outer closure (fn () => ...) karena
                        // Filament v5 meng-evaluate closure rule; tanpa ini $attribute tak
                        // ter-resolve dan import gagal.
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                $files = is_array($value) ? $value : [$value];
                                foreach ($files as $file) {
                                    if (! $file instanceof \Illuminate\Http\UploadedFile) {
                                        continue;
                                    }
                                    $ext = strtolower($file->getClientOriginalExtension());
                                    if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
                                        $fail('Format file "' . $file->getClientOriginalName() . '" tidak didukung (.' . $ext . '). Gunakan Excel (.xlsx, .xls) atau CSV.');
                                    }
                                }
                            },
                        ])
                        ->required(),
                ])
                ->action(fn (array $data) => $this->runImport($data)),

            Action::make('catalog')
                ->label('Impor Daftar Produk')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('gray')
                ->modalHeading('Impor daftar produk (katalog Anda)')
                ->modalDescription('Untuk DAFTAR PRODUK Anda sendiri / dari supplier (BUKAN file dari marketplace). File cukup berisi: Kode Produk (SKU) & Harga Modal. Boleh ditambah: Nama Produk, Tanggal.')
                ->modalSubmitActionLabel('Impor daftar produk')
                ->schema([
                    FileUpload::make('file')
                        ->label('File daftar produk (Excel .xlsx atau CSV)')
                        ->storeFiles(false)
                        ->required()
                        ->helperText('Kolom wajib: Kode Produk (SKU), Harga Modal. Opsional: Nama Produk, Modal Dropship, Tanggal.')
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                $f = is_array($value) ? ($value[0] ?? null) : $value;
                                if ($f instanceof \Illuminate\Http\UploadedFile
                                    && ! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xls', 'csv', 'txt'], true)) {
                                    $fail('Format harus Excel (.xlsx, .xls) atau CSV.');
                                }
                            },
                        ]),
                    TextInput::make('supplier_name')
                        ->label('Produk ini dari mana? (nama supplier)')
                        ->default('Stok Sendiri')
                        ->required()
                        ->helperText('Mis. "Stok Sendiri", "Supplier A", "Toko Grosir B". Dibuat otomatis jika belum ada.'),
                    Toggle::make('is_dropship')
                        ->label('Produk ini DROPSHIP (dikirim supplier, bukan stok Anda)')
                        ->helperText('Aktif: harga di file = biaya yang Anda bayar ke supplier. Nonaktif: stok sendiri, harga di file = modal beli Anda (HPP).'),
                    Toggle::make('update_old_hpp')
                        ->label('Perbarui juga harga modal pesanan lama dengan harga baru ini')
                        ->helperText('Default: pesanan lama tetap memakai harga modal saat itu (riwayat terjaga). Aktifkan hanya bila ingin menimpa.'),
                ])
                ->action(fn (array $data) => $this->runCatalogImport($data)),

            Action::make('dropship')
                ->label('Impor Biaya Dropship')
                ->icon(Heroicon::OutlinedTruck)
                ->color('gray')
                ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship())
                ->modalHeading('Impor biaya dropship manual (per pesanan)')
                ->modalDescription('Untuk dropship dari sumber yang TIDAK punya laporan otomatis (mis. supplier / seller lain). File berisi: No. Pesanan (dari marketplace) & Biaya Dropship. Pesanan yang cocok otomatis ditandai Dropship & biayanya terisi.')
                ->modalSubmitActionLabel('Impor biaya dropship')
                ->schema([
                    FileUpload::make('file')
                        ->label('File biaya dropship (Excel .xlsx atau CSV)')
                        ->storeFiles(false)
                        ->required()
                        ->helperText('Kolom wajib: No. Pesanan, Biaya Dropship. Opsional: Modal Produk (untuk hitung "seandainya packing sendiri").')
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                $f = is_array($value) ? ($value[0] ?? null) : $value;
                                if ($f instanceof \Illuminate\Http\UploadedFile
                                    && ! in_array(strtolower($f->getClientOriginalExtension()), ['xlsx', 'xls', 'csv', 'txt'], true)) {
                                    $fail('Format harus Excel (.xlsx, .xls) atau CSV.');
                                }
                            },
                        ]),
                ])
                ->action(fn (array $data) => $this->runDropshipImport($data)),
        ];
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
            Notification::make()->title('Impor biaya dropship gagal')->body($res['reason'] ?? '')->danger()->send();
            return;
        }

        Notification::make()
            ->title("Biaya dropship diimpor: {$res['updated']} pesanan diperbarui")
            ->body("{$res['rows']} baris dibaca · {$res['matched']} pesanan cocok"
                . ($res['notfound'] ? " · {$res['notfound']} No. Pesanan tidak ditemukan di sistem" : ''))
            ->success()
            ->send();
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
            (bool) ($data['is_dropship'] ?? false),
            (bool) ($data['update_old_hpp'] ?? false),
        );

        if (! ($res['ok'] ?? false)) {
            Notification::make()->title('Impor daftar produk gagal')->body($res['reason'] ?? '')->danger()->send();
            return;
        }

        Notification::make()
            ->title("Daftar produk diimpor: {$res['ins']} produk baru, {$res['upd']} diperbarui")
            ->body("Supplier: {$res['supplier']}"
                . ($res['changes'] ? " · {$res['changes']} harga berubah" : '')
                . ($res['skipped'] ? " · {$res['skipped']} dilewati karena tanggal di file lebih lama (harga baru tidak ditimpa)" : ''))
            ->success()
            ->send();
    }

    protected function runImport(array $data): void
    {
        $store = Store::findOrFail($data['store_id']);

        $files = collect($data['files'] ?? [])
            ->map(fn ($tmp) => ['name' => $tmp->getClientOriginalName(), 'path' => $tmp->getRealPath()])
            ->filter(fn ($f) => $f['path'])
            ->values()
            ->all();

        if (! $files) {
            Notification::make()->title('Belum ada file dipilih')->warning()->send();
            return;
        }

        $importer = new OrderImporter((int) $store->organization_id);
        $result = $importer->importFiles($files, (int) $store->id, $store->marketplace);

        $this->report = $result['report'];
        $this->summary = $result['summary'];

        $ok = collect($this->report)->where('ok', true)->count();
        $fail = collect($this->report)->where('ok', false)->count();
        $body = implode(' ', array_filter([$result['summary']['orders'] ?? null, $result['summary']['dropship'] ?? null]));
        $body = $body !== '' ? $body : 'Cek detail per file di halaman Import.';

        $notif = Notification::make()
            ->title($fail ? "Selesai: {$ok} file diproses, {$fail} dilewati" : "Berhasil: {$ok} file diproses")
            ->body($body)
            ->icon('heroicon-o-arrow-down-tray')
            ->{$fail ? 'warning' : 'success'}();
        $notif->send();                              // toast sekarang
        $notif->sendToDatabase(auth()->user());      // tersimpan di lonceng
    }
}
