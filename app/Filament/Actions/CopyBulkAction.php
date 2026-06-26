<?php

namespace App\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Bulk action "Salin ..." — menyalin satu kolom dari semua baris terpilih ke clipboard
 * (satu nilai per baris, siap tempel di Excel/teks). Dipakai di tabel Pesanan (No. Pesanan)
 * & Produk (SKU). Menyalin via Clipboard API, dengan fallback execCommand untuk peramban lama.
 */
class CopyBulkAction
{
    /**
     * @param  string|\Closure  $extract  Nama kolom (di-pluck) ATAU closure(Collection $records): iterable nilai.
     * @param  string|null  $emptyHint  Penjelasan saat tak ada nilai (mis. "impor File Pesanan dulu").
     */
    /** JS salin ke clipboard (Clipboard API + fallback execCommand utk peramban lama). */
    public static function clipboardJs(string $text): string
    {
        return '(function(t){try{if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t);}else{throw 0;}}'
            . 'catch(e){var a=document.createElement("textarea");a.value=t;a.style.position="fixed";a.style.opacity="0";'
            . 'document.body.appendChild(a);a.focus();a.select();try{document.execCommand("copy");}catch(_){}document.body.removeChild(a);}})('
            . json_encode($text) . ')';
    }

    public static function make(string $name, string $label, string|\Closure $extract, string $satuan, ?string $emptyHint = null): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, $livewire) use ($extract, $satuan, $emptyHint) {
                $vals = ($extract instanceof \Closure
                    ? Collection::wrap($extract($records))
                    : $records->pluck($extract))
                    ->filter()->unique()->values();

                if ($vals->isEmpty()) {
                    Notification::make()
                        ->title('Belum ada ' . $satuan . ' pada baris terpilih')
                        ->body($emptyHint ?? ('Tidak ada ' . $satuan . ' untuk disalin.'))
                        ->warning()
                        ->send();

                    return null;
                }

                // Terlalu banyak utk clipboard → unduh sebagai file .txt (selalu berhasil).
                if ($vals->count() > 2000) {
                    Notification::make()
                        ->title($vals->count() . ' ' . $satuan . ' — terlalu banyak untuk clipboard')
                        ->body('Diunduh sebagai file .txt (satu nilai per baris).')
                        ->info()
                        ->send();

                    return response()->streamDownload(function () use ($vals): void {
                        echo $vals->implode("\n");
                    }, \Illuminate\Support\Str::slug($satuan) . '-' . now()->format('Ymd-His') . '.txt', ['Content-Type' => 'text/plain; charset=utf-8']);
                }

                $livewire->js(self::clipboardJs($vals->implode("\n")));

                Notification::make()
                    ->title($vals->count() . ' ' . $satuan . ' disalin (dari ' . $records->count() . ' baris terpilih)')
                    ->body('Tempel (Ctrl+V) di Excel/teks — satu per baris.')
                    ->success()
                    ->send();

                return null;
            });
    }
}
