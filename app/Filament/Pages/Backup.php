<?php

namespace App\Filament\Pages;

use App\Services\BackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Backup extends Page
{
    protected string $view = 'filament.pages.backup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Backup';

    protected static ?string $title = 'Backup & Pemulihan Data';

    protected static ?int $navigationSort = 9;

    public function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Unduh Backup (.zip)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->extraAttributes(['class' => 'justify-center', 'style' => 'min-width:16rem'])
            ->action(fn (): StreamedResponse => $this->downloadBackup());
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Pulihkan dari Backup')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('warning')
            ->extraAttributes(['class' => 'justify-center', 'style' => 'min-width:16rem'])
            ->modalHeading('Pulihkan data dari file backup')
                ->modalDescription('PERHATIAN: data Anda saat ini (toko, produk, pesanan) akan DIGANTI dengan isi file backup. Pastikan file benar.')
                ->modalSubmitActionLabel('Ganti & pulihkan sekarang')
                ->schema([
                    FileUpload::make('file')
                        ->label('File backup MarkazHub (.json / .zip / .sql)')
                        ->storeFiles(false)
                        ->required()
                        ->acceptedFileTypes([
                            'application/json',
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/sql',
                            'text/plain',
                            'application/octet-stream',
                        ])
                        ->helperText('File .json (baru), atau .zip / .sql dari unduhan lama menu ini.')
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                $f = is_array($value) ? ($value[0] ?? null) : $value;
                                if ($f instanceof \Illuminate\Http\UploadedFile
                                    && ! in_array(strtolower($f->getClientOriginalExtension()), ['json', 'zip', 'sql'], true)) {
                                    $fail('File harus .json, .zip, atau .sql (hasil backup MarkazHub).');
                                }
                            },
                        ]),
                    TextInput::make('konfirmasi')
                        ->label('Ketik PULIHKAN untuk melanjutkan')
                        ->required()
                        ->rule('in:PULIHKAN')
                        ->validationMessages(['in' => 'Ketik persis: PULIHKAN']),
                ])
                ->action(fn (array $data) => $this->runRestore($data));
    }

    public function clearAction(): Action
    {
        return Action::make('clear')
            ->label('Kosongkan Data')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->extraAttributes(['class' => 'justify-center', 'style' => 'min-width:16rem'])
            ->modalHeading('Kosongkan data')
                ->modalDescription('PERHATIAN: data akan DIHAPUS PERMANEN dan tidak bisa dibatalkan. Sangat disarankan "Unduh Backup" dulu sebelum melanjutkan.')
                ->modalSubmitActionLabel('Kosongkan sekarang')
                ->modalIcon(Heroicon::OutlinedTrash)
                ->schema([
                    Select::make('scope')
                        ->label('Yang dikosongkan')
                        ->options([
                            'orders' => 'Hanya Pesanan (pesanan + itemnya) — produk, toko, kategori tetap',
                            'all' => 'Semua data bisnis (pesanan, produk, toko, supplier) — kategori tetap',
                        ])
                        ->default('orders')
                        ->required()
                        ->native(false),
                    TextInput::make('konfirmasi')
                        ->label('Ketik KOSONGKAN untuk melanjutkan')
                        ->required()
                        ->rule('in:KOSONGKAN')
                        ->validationMessages(['in' => 'Ketik persis: KOSONGKAN']),
                ])
                ->action(fn (array $data) => $this->runClear($data));
    }

    protected function downloadBackup(): StreamedResponse
    {
        $orgId = (int) auth()->user()->organization_id;
        // ZIP (isi JSON) = format paling kecil (~15% ukuran). Catatan: antivirus reputasi
        // (mis. McAfee GTI) menandai file unduhan unik apa pun secara reputasi — format
        // tak memengaruhinya — jadi pilih yang paling ringkas untuk disimpan.
        $zip = app(BackupService::class)->zipForOrg($orgId);
        $name = 'markazhub-backup-' . now()->format('Ymd-His') . '.zip';

        return response()->streamDownload(function () use ($zip) {
            echo $zip;
        }, $name, [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) strlen($zip),
        ]);
    }

    protected function runRestore(array $data): void
    {
        $upload = $data['file'] ?? null;
        $file = is_array($upload) ? ($upload[0] ?? null) : $upload;

        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            Notification::make()->title('File tidak ditemukan')->danger()->send();
            return;
        }

        try {
            // Terima .zip (JSON baru) maupun .sql (lama) — format dideteksi otomatis.
            $result = app(BackupService::class)->restoreFromUpload(
                (int) auth()->user()->organization_id,
                (string) file_get_contents($file->getRealPath()),
            );
            \App\Support\Bell::send(Notification::make()
                ->title('Data berhasil dipulihkan')
                ->body("{$result['orders']} pesanan aktif setelah pemulihan.")
                ->success());
        } catch (\Throwable $e) {
            \App\Support\Bell::send(Notification::make()
                ->title('Pemulihan gagal')
                ->body($e->getMessage())
                ->danger());
        }
    }

    protected function runClear(array $data): void
    {
        try {
            $deleted = app(BackupService::class)->clearOrgData(
                (int) auth()->user()->organization_id,
                $data['scope'] ?? 'orders',
            );
            $total = array_sum($deleted);
            $rincian = collect($deleted)
                ->map(fn ($n, $t) => number_format((int) $n, 0, ',', '.') . ' ' . $t)
                ->implode(', ');

            \App\Support\Bell::send(Notification::make()
                ->title('Data berhasil dikosongkan')
                ->body("Total {$total} baris dihapus ({$rincian}).")
                ->success());
        } catch (\Throwable $e) {
            \App\Support\Bell::send(Notification::make()
                ->title('Gagal mengosongkan data')
                ->body($e->getMessage())
                ->danger());
        }
    }
}
