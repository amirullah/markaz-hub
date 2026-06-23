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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->color('gray')
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
                Toggle::make('update_old_hpp')
                    ->label('Perbarui juga harga modal pesanan lama dengan harga baru ini')
                    ->helperText('Default: pesanan lama tetap memakai harga modal saat itu (riwayat terjaga).'),
            ])
            ->action(fn (array $data) => $this->runCatalogImport($data));
    }

    // ====== 3) Dropship (biaya dropship per pesanan) ======
    public function dropshipAction(): Action
    {
        return Action::make('dropship')
            ->label('Impor Dropship')
            ->icon(Heroicon::OutlinedTruck)
            ->color('warning')
            ->modalHeading('Impor biaya dropship (per pesanan)')
            ->modalSubmitActionLabel('Impor dropship')
            ->schema([
                FileUpload::make('file')
                    ->label('File dropship (Excel .xlsx atau CSV)')
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Boleh: laporan dari penyedia dropship, ATAU file isian dari "Unduh Format" (No. Pesanan + Biaya Dropship).')
                    ->rules($this->extRule()),
            ])
            ->action(fn (array $data) => $this->runDropshipImport($data));
    }

    /** Unduh template/format file dropship manual (CSV). */
    public function downloadTemplateAction(): Action
    {
        return Action::make('downloadTemplate')
            ->label('Unduh Format File')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->link()
            ->action(function (): StreamedResponse {
                $csv = "No. Pesanan,Biaya Dropship,Modal Produk\r\n"
                    . "GANTI-DENGAN-NO-PESANAN,50000,45000\r\n";
                return response()->streamDownload(function () use ($csv) {
                    echo "\xEF\xBB\xBF" . $csv; // BOM agar Excel rapi
                }, 'format-biaya-dropship.csv', ['Content-Type' => 'text/csv']);
            });
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

        $ok = collect($this->report)->where('ok', true)->count();
        $fail = collect($this->report)->where('ok', false)->count();
        $body = implode(' ', array_filter([$result['summary']['orders'] ?? null]));
        $body = $body !== '' ? $body : 'Cek detail per file di bawah.';

        $notif = Notification::make()
            ->title($fail ? "Selesai: {$ok} file diproses, {$fail} dilewati" : "Berhasil: {$ok} file diproses")
            ->body($body)
            ->icon('heroicon-o-arrow-down-tray')
            ->{$fail ? 'warning' : 'success'}();
        $notif->send();
        $notif->sendToDatabase(auth()->user());
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
        );

        if (! ($res['ok'] ?? false)) {
            Notification::make()->title('Impor daftar produk gagal')->body($res['reason'] ?? '')->danger()->send();
            return;
        }

        Notification::make()
            ->title("Daftar produk diimpor: {$res['ins']} produk baru, {$res['upd']} diperbarui")
            ->body("Supplier: {$res['supplier']}"
                . ($res['changes'] ? " · {$res['changes']} harga berubah" : '')
                . ($res['skipped'] ? " · {$res['skipped']} dilewati (tanggal di file lebih lama)" : ''))
            ->success()
            ->send();
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
            Notification::make()->title('Impor dropship gagal')->body($res['reason'] ?? '')->danger()->send();
            return;
        }

        Notification::make()
            ->title("Dropship diimpor: {$res['updated']} pesanan diperbarui")
            ->body("{$res['rows']} baris dibaca · {$res['matched']} pesanan cocok"
                . ($res['notfound'] ? " · {$res['notfound']} No. Pesanan tidak ditemukan" : ''))
            ->success()
            ->send();
    }
}
