<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ChannelChart extends ChartWidget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return 'Omzet per Channel';
    }

    protected function getData(): array
    {
        return \App\Support\DashboardCache::remember('channel', fn (): array => $this->buildData());
    }

    private function buildData(): array
    {
        // Tokopedia/TikTok dianggap satu channel: nilai legacy TOKOPEDIA/TIKTOK dilebur ke TIKTOKTOKO
        // (konsisten dgn filter & merge sistem) agar tidak ada omzet yang hilang dari grafik.
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw("CASE WHEN marketplace IN ('TOKOPEDIA', 'TIKTOK') THEN 'TIKTOKTOKO' ELSE marketplace END AS ch, SUM(product_revenue + other_income) omzet")
            ->groupBy('ch')
            ->pluck('omzet', 'ch');

        $labelMap = ['SHOPEE' => 'Shopee', 'TIKTOKTOKO' => 'Tokopedia/TikTok'];
        $colorMap = ['SHOPEE' => '#f97316', 'TIKTOKTOKO' => '#22c55e'];

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
