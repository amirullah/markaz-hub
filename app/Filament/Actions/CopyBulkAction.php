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
    public static function make(string $name, string $label, string|\Closure $extract, string $satuan, ?string $emptyHint = null): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, $livewire) use ($extract, $satuan, $emptyHint): void {
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

                    return;
                }

                $text = $vals->implode("\n");
                $js = '(function(t){try{if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t);}else{throw 0;}}'
                    . 'catch(e){var a=document.createElement("textarea");a.value=t;a.style.position="fixed";a.style.opacity="0";'
                    . 'document.body.appendChild(a);a.focus();a.select();try{document.execCommand("copy");}catch(_){}document.body.removeChild(a);}})('
                    . json_encode($text) . ')';
                $livewire->js($js);

                Notification::make()
                    ->title($vals->count() . ' ' . $satuan . ' disalin (dari ' . $records->count() . ' baris terpilih)')
                    ->body('Tempel (Ctrl+V) di Excel/teks — satu per baris.')
                    ->success()
                    ->send();
            });
    }
}
