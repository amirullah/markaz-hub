<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    // Lebar penuh agar layout 2 kolom (form + sidebar Ringkasan Laba) lega — meniru v1.
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pesanan ' . $this->record->external_no;
    }

    // Subjudul ala v1: Toko · Tanggal · Pembeli + badge channel berwarna.
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

    // Tombol simpan ada di sidebar "Ringkasan Laba" (meniru v1) — hilangkan tombol bawah default.
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
