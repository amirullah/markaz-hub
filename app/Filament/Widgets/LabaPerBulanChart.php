<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class LabaPerBulanChart extends ChartWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Laba & Omzet per Bulan (12 bulan terakhir)';
    }

    protected function getData(): array
    {
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') ym")
            ->selectRaw('SUM(product_revenue + other_income) omzet')
            ->selectRaw('SUM(product_revenue + other_income - (cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + dropship_cost + other_cost)) laba')
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
