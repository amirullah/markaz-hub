<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    // Lebar penuh agar layout 2 kolom (info + sidebar Ringkasan Laba) lega — sama dgn Ubah.
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pesanan ' . $this->record->external_no;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $r = $this->record;
        [$bg, $fg] = $r->marketplace === 'SHOPEE' ? ['#fef3c7', '#92400e'] : ['#dcfce7', '#166534'];
        $label = OrderForm::CHANNEL[$r->marketplace] ?? $r->marketplace;
        $info = e($r->store?->name . ' · ' . $r->order_date?->translatedFormat('d M Y') . ($r->buyer_name ? ' · ' . $r->buyer_name : ''));

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:.5rem;flex-wrap:wrap">'
            . '<span style="color:#64748b">' . $info . '</span>'
            . '<span style="font-size:11px;padding:1px 8px;border-radius:6px;background:' . $bg . ';color:' . $fg . '">' . e($label) . '</span>'
            . '</span>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Ubah'),
            Action::make('kembali')
                ->label('Kembali')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl('index')),
        ];
    }
}
