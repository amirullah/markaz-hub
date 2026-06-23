<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopProdukChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return '5 Produk Terlaris (qty)';
    }

    protected function getData(): array
    {
        return \App\Support\DashboardCache::remember('top_produk', fn (): array => $this->buildData());
    }

    private function buildData(): array
    {
        $rows = OrderItem::query()
            ->whereNotNull('sku')
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED'))
            ->selectRaw('sku, MAX(name) AS nm, SUM(qty) AS total')
            ->groupBy('sku')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Qty terjual',
                'data' => $rows->pluck('total')->map(fn ($v) => (int) $v)->toArray(),
                'backgroundColor' => '#2563eb',
            ]],
            'labels' => $rows->pluck('nm')->map(fn ($n) => mb_strlen((string) $n) > 24 ? mb_substr((string) $n, 0, 24) . '…' : $n)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
