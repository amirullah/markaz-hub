<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // Pesanan hanya masuk lewat Import (canCreate=false) — tanpa tombol "Buat".
    protected function getHeaderActions(): array
    {
        return [];
    }
}
