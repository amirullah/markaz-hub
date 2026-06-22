<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\AdminFeeEstimator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // Pesanan masuk lewat Import (tanpa tombol "Buat"). Aksi: isi estimasi biaya admin.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('estimasiAdmin')
                ->label('Isi Estimasi Biaya Admin')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Isi estimasi biaya admin')
                ->modalDescription('Mengisi biaya admin (estimasi dari % kategori produk) untuk pesanan yang BELUM punya biaya admin & belum final. Pesanan dengan laba final tidak diubah. Pastikan produk sudah punya kategori.')
                ->modalSubmitActionLabel('Isi estimasi sekarang')
                ->action(function (): void {
                    $res = app(AdminFeeEstimator::class)->applyToOrg((int) auth()->user()->organization_id);
                    Notification::make()
                        ->title("Estimasi terisi untuk {$res['updated']} pesanan")
                        ->body('Total estimasi biaya admin: Rp ' . number_format($res['total'], 0, ',', '.')
                            . ($res['updated'] === 0 ? ' — pastikan produk sudah dipasangi kategori.' : ''))
                        ->success()
                        ->send();
                }),
        ];
    }
}
