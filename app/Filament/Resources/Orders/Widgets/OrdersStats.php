<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Services\ProfitService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Kartu ringkasan total di ATAS tabel Pesanan. Mengikuti filter & pencarian tabel
 * (InteractsWithPageTable) — mis. filter "Minggu ini" → kartu menampilkan total minggu itu.
 */
class OrdersStats extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        $base = $this->getPageTableQuery();

        $count = (clone $base)->count();
        $omzet = (float) (clone $base)->sum('product_revenue');
        $laba = (float) (clone $base)->sum(DB::raw(ProfitService::SQL_PROFIT));

        $rp = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');

        return [
            Stat::make('Jumlah Pesanan', number_format($count, 0, ',', '.'))
                ->description('sesuai filter aktif')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
            Stat::make('Total Omzet', $rp($omzet))
                ->description('penjualan produk')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
            Stat::make('Total Laba', $rp($laba))
                ->description('setelah biaya & modal')
                ->descriptionIcon($laba < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($laba < 0 ? 'danger' : 'success'),
        ];
    }
}
