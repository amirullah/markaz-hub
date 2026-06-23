<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\ProfitService;
use Filament\Widgets\ChartWidget;

class LabaPerBulanChart extends ChartWidget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return 'Laba & Omzet per Bulan (12 bulan terakhir)';
    }

    protected function getData(): array
    {
        return \App\Support\DashboardCache::remember('laba_bulan', fn (): array => $this->buildData());
    }

    private function buildData(): array
    {
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') ym")
            ->selectRaw('SUM(product_revenue + other_income) omzet')
            ->selectRaw('SUM(' . ProfitService::sqlProfit() . ') laba')
            ->groupBy('ym')->orderBy('ym')
            ->get()->keyBy('ym');

        $labels = []; $omzet = []; $laba = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $ym = $d->format('Y-m');
            $labels[] = $d->translatedFormat('M y');
            $omzet[] = round((float) ($rows->get($ym)->omzet ?? 0));
            $laba[] = round((float) ($rows->get($ym)->laba ?? 0));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Omzet',
                    'data' => $omzet,
                    'borderColor' => '#93c5fd',
                    'backgroundColor' => 'rgba(147, 197, 253, 0.25)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Laba',
                    'data' => $laba,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.25)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
