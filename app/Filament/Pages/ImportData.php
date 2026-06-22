<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Services\Import\OrderImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ImportData extends Page
{
    protected string $view = 'filament.pages.import-data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Import Laporan';

    protected static ?string $title = 'Import Laporan';

    protected static ?int $navigationSort = 4;

    /** Hasil import per file (ditampilkan di view). */
    public ?array $report = null;

    public ?array $summary = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Unggah & Import')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Import file ekspor')
                ->modalSubmitActionLabel('Import sekarang')
                ->schema([
                    Select::make('store_id')
                        ->label('Toko tujuan')
                        ->options(fn () => Store::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->native(false)
                        ->helperText('File Shopee ke toko Shopee, file Tokopedia/TikTok ke toko Tokopedia/TikTok. File beda channel otomatis dilewati.'),
                    FileUpload::make('files')
                        ->label('File ekspor (.xlsx / .csv) — boleh beberapa sekaligus')
                        ->multiple()
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel', 'text/csv', 'application/csv', 'text/plain',
                            'application/octet-stream',
                        ])
                        ->required(),
                    Toggle::make('update_old_hpp')
                        ->label('Perbarui HPP pesanan lama dengan harga baru (saat unggah Master)')
                        ->helperText('Default: pesanan lama tetap pakai HPP saat itu (histori harga terjaga).'),
                    DatePicker::make('hpp_since')
                        ->label('Jika di atas dicentang: hanya pesanan sejak tanggal (opsional)')
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->runImport($data)),
        ];
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
        $result = $importer->importFiles(
            $files,
            (int) $store->id,
            $store->marketplace,
            (bool) ($data['update_old_hpp'] ?? false),
            $data['hpp_since'] ?? null,
        );

        $this->report = $result['report'];
        $this->summary = $result['summary'];

        $ok = collect($this->report)->where('ok', true)->count();
        $fail = collect($this->report)->where('ok', false)->count();
        Notification::make()
            ->title("Import selesai: {$ok} berhasil, {$fail} gagal")
            ->body(implode(' ', array_filter([$result['summary']['jakmall'] ?? null, $result['summary']['orders'] ?? null, $result['summary']['dropship'] ?? null])))
            ->success()
            ->send();
    }
}
