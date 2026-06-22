<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ProfitService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class Insight extends Page
{
    protected string $view = 'filament.pages.insight';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Insight';

    protected static ?string $title = 'Insight & Produk Merugi';

    protected static ?int $navigationSort = 5;

    public function getViewData(): array
    {
        $profit = ProfitService::SQL_PROFIT;

        // Pesanan SELESAI yang RUGI (laba < 0) — kandidat dievaluasi.
        $pesananRugi = Order::query()
            ->where('status', 'COMPLETED')
            ->whereRaw("($profit) < -1")
            ->orderByRaw("($profit) asc")
            ->limit(25)->get();

        // Item terjual DI BAWAH MODAL (harga jual < HPP) — rugi di level produk.
        $bawahModal = OrderItem::query()
            ->whereColumn('unit_price', '<', 'unit_cost')
            ->where('unit_cost', '>', 0)
            ->where('unit_price', '>', 0) // harga jual tercatat (bukan data kosong)
            ->whereNotNull('sku')
            ->selectRaw('sku, MAX(name) AS name, COUNT(*) AS n, ROUND(AVG(unit_price)) AS avg_jual, ROUND(AVG(unit_cost)) AS avg_modal')
            ->groupBy('sku')
            ->orderByRaw('AVG(unit_price - unit_cost) asc')
            ->limit(20)->get();

        // Produk terlaris (qty terjual, pesanan selesai).
        $terlaris = OrderItem::query()
            ->whereNotNull('sku')
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED'))
            ->selectRaw('sku, MAX(name) AS name, SUM(qty) AS total_qty')
            ->groupBy('sku')
            ->orderByDesc('total_qty')
            ->limit(20)->get();

        // Statistik
        $jmlRugi = Order::query()->where('status', 'COMPLETED')->whereRaw("($profit) < 0")->count();
        $nilaiRugi = (float) Order::query()->where('status', 'COMPLETED')->whereRaw("($profit) < 0")->sum(DB::raw($profit));
        $totalPesanan = Order::query()->count();
        $jmlRetur = Order::query()->where('status', 'RETURNED')->count();
        $jmlBatal = Order::query()->where('status', 'CANCELLED')->count();
        $rasioRetur = $totalPesanan > 0 ? round($jmlRetur / $totalPesanan * 100, 1) : 0;

        return compact('pesananRugi', 'bawahModal', 'terlaris', 'jmlRugi', 'nilaiRugi', 'jmlRetur', 'jmlBatal', 'rasioRetur', 'totalPesanan');
    }
}
