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

    // Tampil bersama halaman (bukan lazy/pop-in bertahap) — datanya sudah di-cache jadi cepat.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Angka di-cache per org (TTL pendek, dibatalkan saat impor/estimasi) — hemat query berat.
        $d = \App\Support\DashboardCache::remember('stats', function (): array {
            $ops = Order::query()->whereNotIn('status', ['CANCELLED', 'RETURNED']);
            $months = $this->monthly(6);

            return [
                'omzet' => (float) (clone $ops)->sum(DB::raw('product_revenue + other_income')),
                'laba' => (float) (clone $ops)->sum(DB::raw(ProfitService::sqlProfit())),
                'jumlah' => (clone $ops)->count(),
                'batal' => Order::query()->where('status', 'CANCELLED')->count(),
                'retur' => Order::query()->where('status', 'RETURNED')->count(),
                'belumFinal' => Order::query()->labaBelumFinal()->count(),
                'pesananRugi' => Order::query()->where('status', 'COMPLETED')->whereRaw(ProfitService::sqlProfit() . ' < 0')->count(),
                'labaSemu' => Order::query()->labaSemu()->count(),
                'janggal' => Order::query()->janggal()->count(),
                'bawahModal' => Order::query()->bawahModal()->count(),
                'sparkLaba' => array_values(array_map(fn ($m) => round($m['laba']), $months)),
                'sparkOmzet' => array_values(array_map(fn ($m) => round($m['omzet']), $months)),
            ];
        });

        $omzet = $d['omzet'];
        $laba = $d['laba'];
        $jumlah = $d['jumlah'];
        $batal = $d['batal'];
        $retur = $d['retur'];
        $belumFinal = $d['belumFinal'];
        $pesananRugi = $d['pesananRugi'];
        $labaSemu = $d['labaSemu'] ?? 0;
        $janggal = $d['janggal'] ?? 0;
        $bawahModal = $d['bawahModal'] ?? 0;
        $sparkLaba = $d['sparkLaba'];
        $sparkOmzet = $d['sparkOmzet'];
        $aov = $jumlah > 0 ? $omzet / $jumlah : 0;
        $margin = $omzet > 0 ? ($laba / $omzet * 100) : 0;

        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        // Kartu bisa diklik → buka data terkait yang sudah terfilter.
        $ordersUrl = fn (array $filters = []): string => \App\Filament\Resources\Orders\OrderResource::getUrl('index')
            . ($filters ? '?' . http_build_query(['filters' => $filters]) : '');
        $insightUrl = \App\Filament\Pages\Insight::getUrl();

        return [
            Stat::make('Omzet (operasional)', $rp($omzet))
                ->description($jumlah . ' pesanan selesai/dikirim — lihat')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($sparkOmzet ?: [0, 0])
                ->color('primary')
                ->url($ordersUrl()),
            Stat::make('Laba Bersih', $rp($laba))
                ->description('setelah modal & biaya marketplace')
                ->descriptionIcon($laba < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->chart($sparkLaba ?: [0, 0])
                ->color($laba < 0 ? 'danger' : 'success')
                ->url($insightUrl),
            Stat::make('Margin Laba', number_format($margin, 1, ',', '.') . '%')
                ->description('laba dibanding omzet — analisa')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($margin < 0 ? 'danger' : ($margin < 5 ? 'warning' : 'success'))
                ->url($insightUrl),
            Stat::make('Rata-rata / Pesanan', $rp($aov))
                ->description('nilai rata-rata per pesanan')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info')
                ->url($ordersUrl()),
            Stat::make('Pesanan Rugi', number_format($pesananRugi, 0, ',', '.'))
                ->description('selesai dengan laba minus — lihat daftar')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pesananRugi > 0 ? 'danger' : 'success')
                ->url($ordersUrl(['hasil_laba' => ['value' => 'rugi']])),
            Stat::make('Pesanan Batal', number_format($batal, 0, ',', '.'))
                ->description($retur > 0 ? '+ ' . number_format($retur, 0, ',', '.') . ' dikembalikan/retur' : 'tidak dihitung di laba')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('warning')
                ->url($ordersUrl(['status' => ['values' => ['CANCELLED']]])),
            Stat::make('Laba Semu (HPP kosong)', number_format($labaSemu, 0, ',', '.'))
                ->description($labaSemu > 0 ? 'omzet ada tapi modal 0 → laba terlihat besar, belum nyata' : 'semua HPP sudah terisi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($labaSemu > 0 ? 'danger' : 'success')
                ->url($ordersUrl(['status_laba' => ['value' => 'laba_semu']])),
            Stat::make('Jual di Bawah Modal', number_format($bawahModal, 0, ',', '.'))
                ->description($bawahModal > 0 ? 'harga jual < modal (HPP/dropship) — cek harga/supplier' : 'tak ada yang dijual di bawah modal')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($bawahModal > 0 ? 'danger' : 'success')
                ->url($ordersUrl(['bawah_modal' => ['value' => 'bawah']])),
            // Kartu Janggal hanya tampil bila berjualan dropship (sesuai toggle Pengaturan).
            ...(\App\Models\Organization::currentUsesDropship() ? [
                Stat::make('Pesanan Janggal', number_format($janggal, 0, ',', '.'))
                    ->description($janggal > 0 ? 'biaya dropship ada tapi belum jadi dropship — perlu disinkronkan' : 'data dropship sudah sinkron')
                    ->descriptionIcon('heroicon-m-shield-exclamation')
                    ->color($janggal > 0 ? 'danger' : 'success')
                    ->url($ordersUrl(['janggal' => ['value' => 'janggal']])),
            ] : []),
            Stat::make('Laba Belum Final', number_format($belumFinal, 0, ',', '.'))
                ->description($belumFinal > 0 ? 'biaya estimasi / HPP-modal belum ada — laba belum pasti' : 'semua laba sudah final')
                ->descriptionIcon('heroicon-m-clock')
                ->color($belumFinal > 0 ? 'warning' : 'success')
                ->url($ordersUrl(['status_laba' => ['value' => 'belum_final']])),
            // === PIPELINE PROSES ===
            Stat::make('Perlu Diproses', number_format(Order::query()->where('processing_status', 'PENDING')->count(), 0, ',', '.'))
                ->description('pesanan baru menunggu diproses')
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning')
                ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('index') . '?tab=baru'),
            Stat::make('Sedang Diproses', number_format(
                Order::query()->whereIn('processing_status', ['PROCESSING', 'PACKED'])->count(), 0, ',', '.'))
                ->description('diproses & dikemas')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('index') . '?tab=diproses'),
            Stat::make('Gagal Diproses', number_format(Order::query()->where('processing_status', 'FAILED')->count(), 0, ',', '.'))
                ->description('perlu retry atau perbaiki stok')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color(Order::query()->where('processing_status', 'FAILED')->count() > 0 ? 'danger' : 'success')
                ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('index') . '?tab=gagal'),
        ];
    }

    /** Laba & omzet per bulan (N bulan terakhir), org-scoped via global scope. */
    private function monthly(int $n): array
    {
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') ym")
            ->selectRaw('SUM(product_revenue + other_income) omzet')
            ->selectRaw('SUM(' . ProfitService::sqlProfit() . ') laba')
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
