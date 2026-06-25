<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tebak mode tiap toko dari riwayat (≥90% satu jenis) — HANYA toko yang belum
            // ditandai manual (mode 'both'), agar tanda manual tidak tertimpa.
            Action::make('deteksiMode')
                ->label('Tandai Mode Otomatis')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn (): bool => \App\Models\Store::query()->exists() && \App\Models\Organization::currentUsesDropship())
                ->requiresConfirmation()
                ->modalHeading('Tandai mode toko otomatis')
                ->modalDescription('Sistem menebak mode tiap toko (Dropship saja / Packing sendiri saja) dari riwayat pesanannya — hanya untuk toko yang masih "Keduanya". Toko yang sudah Anda tandai manual tidak diubah. Pesanan yang tak sesuai mode lalu ditandai "Janggal".')
                ->modalSubmitActionLabel('Tandai sekarang')
                ->action(function (): void {
                    $orgId = (int) auth()->user()->organization_id;
                    $diset = 0;
                    foreach (Store::query()->where('fulfillment_mode', 'both')->get() as $store) {
                        $mode = Store::detectModeFor((int) $store->id);
                        if ($mode !== 'both') {
                            $store->update(['fulfillment_mode' => $mode]);
                            $diset++;
                        }
                    }

                    Notification::make()
                        ->title($diset > 0 ? "{$diset} toko ditandai otomatis" : 'Tidak ada toko yang jelas satu jenis')
                        ->body($diset > 0
                            ? 'Mode toko di-set dari riwayat. Pesanan yang pemenuhannya tak sesuai kini ditandai "Janggal" (lihat Dashboard / kolom Janggal / filter Pesanan).'
                            : 'Semua toko bercampur dropship & packing sendiri (atau pesanan terlalu sedikit). Tandai manual bila perlu.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
