<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ChannelChart extends ChartWidget
{
    protected static ?int $sort = -1;

    public function getHeading(): ?string
    {
        return 'Omzet per Channel';
    }

    protected function getData(): array
    {
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw('marketplace, SUM(product_revenue + other_income) omzet')
            ->groupBy('marketplace')
            ->pluck('omzet', 'marketplace');

        $labelMap = ['SHOPEE' => 'Shopee', 'TOKOPEDIA' => 'Tokopedia', 'TIKTOK' => 'TikTok'];
        $colorMap = ['SHOPEE' => '#f97316', 'TOKOPEDIA' => '#22c55e', 'TIKTOK' => '#64748b'];

        $labels = []; $data = []; $colors = [];
        foreach ($labelMap as $key => $label) {
            $labels[] = $label;
            $data[] = round((float) ($rows[$key] ?? 0));
            $colors[] = $colorMap[$key];
        }

        return [
            'datasets' => [[
                'label' => 'Omzet',
                'data' => $data,
                'backgroundColor' => $colors,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
