<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\CategoryClassifier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoKategori')
                ->label('Auto-pasang Kategori')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Pasang kategori otomatis')
                ->modalDescription('Sistem memilih kategori untuk semua produk yang BELUM berkategori, berdasarkan nama produk. Produk yang sudah punya kategori tidak diubah. Anda tetap bisa menyesuaikan tiap produk.')
                ->modalSubmitActionLabel('Pasang otomatis sekarang')
                ->action(function (): void {
                    $n = app(CategoryClassifier::class)->applyToOrg((int) auth()->user()->organization_id);
                    \App\Support\Bell::send(Notification::make()
                        ->title("Kategori terpasang untuk {$n} produk")
                        ->body($n === 0 ? 'Semua produk sudah berkategori.' : 'Cek & sesuaikan bila ada yang kurang pas.')
                        ->success());
                }),
            CreateAction::make()->label('Buat Produk'),
        ];
    }
}
