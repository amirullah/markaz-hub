<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\ProfitService;
use Filament\Widgets\ChartWidget;

class LabaChannelChart extends ChartWidget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'Laba per Channel';
    }

    protected function getData(): array
    {
        return \App\Support\DashboardCache::remember('laba_channel', fn (): array => $this->buildData());
    }

    private function buildData(): array
    {
        // Lebur legacy TOKOPEDIA/TIKTOK ke TIKTOKTOKO (konsisten dgn ChannelChart & filter sistem).
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw("CASE WHEN marketplace IN ('TOKOPEDIA', 'TIKTOK') THEN 'TIKTOKTOKO' ELSE marketplace END AS ch, SUM(" . ProfitService::sqlProfit() . ') laba')
            ->groupBy('ch')
            ->pluck('laba', 'ch');

        $labelMap = ['SHOPEE' => 'Shopee', 'TIKTOKTOKO' => 'Tokopedia/TikTok'];
        $colorMap = ['SHOPEE' => '#f97316', 'TIKTOKTOKO' => '#22c55e'];
        $labels = []; $data = []; $colors = [];
        foreach ($labelMap as $key => $label) {
            $labels[] = $label;
            $data[] = round((float) ($rows[$key] ?? 0));
            $colors[] = $colorMap[$key];
        }

        return [
            'datasets' => [['label' => 'Laba (Rp)', 'data' => $data, 'backgroundColor' => $colors]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
