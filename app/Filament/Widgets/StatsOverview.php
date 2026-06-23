<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\ProfitService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $ops = Order::query()->whereNotIn('status', ['CANCELLED', 'RETURNED']);

        $omzet = (clone $ops)->sum(DB::raw('product_revenue + other_income'));
        $laba = (clone $ops)->sum(DB::raw(ProfitService::SQL_PROFIT));
        $jumlah = (clone $ops)->count();
        $aov = $jumlah > 0 ? $omzet / $jumlah : 0;
        $margin = $omzet > 0 ? ($laba / $omzet * 100) : 0;
        $returBatal = Order::query()->whereIn('status', ['CANCELLED', 'RETURNED'])->count();
        $pesananRugi = Order::query()->where('status', 'COMPLETED')->whereRaw(ProfitService::SQL_PROFIT . ' < 0')->count();

        // Sparkline 6 bulan terakhir
        $months = $this->monthly(6);
        $sparkLaba = array_values(array_map(fn ($m) => round($m['laba']), $months));
        $sparkOmzet = array_values(array_map(fn ($m) => round($m['omzet']), $months));

        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        return [
            Stat::make('Omzet (operasional)', $rp($omzet))
                ->description($jumlah . ' pesanan selesai/dikirim')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($sparkOmzet ?: [0, 0])
                ->color('primary'),
            Stat::make('Laba Bersih', $rp($laba))
                ->description('setelah modal & biaya marketplace')
                ->descriptionIcon($laba < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->chart($sparkLaba ?: [0, 0])
                ->color($laba < 0 ? 'danger' : 'success'),
            Stat::make('Margin Laba', number_format($margin, 1, ',', '.') . '%')
                ->description('laba dibanding omzet')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($margin < 0 ? 'danger' : ($margin < 5 ? 'warning' : 'success')),
            Stat::make('Rata-rata / Pesanan', $rp($aov))
                ->description('nilai rata-rata per pesanan')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
            Stat::make('Pesanan Rugi', number_format($pesananRugi, 0, ',', '.'))
                ->description('pesanan selesai dengan laba minus')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pesananRugi > 0 ? 'danger' : 'success'),
            Stat::make('Retur + Batal', number_format($returBatal, 0, ',', '.'))
                ->description('tidak dihitung di laba')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('warning'),
        ];
    }

    /** Laba & omzet per bulan (N bulan terakhir), org-scoped via global scope. */
    private function monthly(int $n): array
    {
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') ym")
            ->selectRaw('SUM(product_revenue + other_income) omzet')
            ->selectRaw('SUM(' . ProfitService::SQL_PROFIT . ') laba')
            ->groupBy('ym')->orderBy('ym')
            ->get()->keyBy('ym');

        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $ym = now()->subMonths($i)->format('Y-m');
            $r = $rows->get($ym);
            $out[$ym] = ['omzet' => (float) ($r->omzet ?? 0), 'laba' => (float) ($r->laba ?? 0)];
        }
        return $out;
    }
}
