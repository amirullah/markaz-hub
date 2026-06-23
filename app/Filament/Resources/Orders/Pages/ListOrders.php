<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\AdminFeeEstimator;
use App\Services\ProfitService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Kartu total di ATAS tabel — mengikuti filter (dihitung dari query terfilter). */
    public function getSubheading(): string|Htmlable|null
    {
        $query = $this->getFilteredTableQuery();
        if (! $query) {
            return null;
        }

        $count = (clone $query)->count();
        $omzet = (float) (clone $query)->sum('product_revenue');
        $laba = (float) (clone $query)->sum(DB::raw(ProfitService::SQL_PROFIT));
        $rp = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $card = fn (string $label, string $value, string $color, string $icon, string $bg): string =>
            "<div style='flex:1 1 150px;display:flex;align-items:center;gap:.6rem;border:1px solid #e8edf3;border-radius:.7rem;padding:.5rem .75rem;background:#fff'>"
            . "<div style='width:2rem;height:2rem;flex:none;display:flex;align-items:center;justify-content:center;border-radius:.55rem;background:{$bg};font-size:1rem'>{$icon}</div>"
            . "<div style='min-width:0'>"
            . "<div style='font-size:.7rem;color:#64748b;white-space:nowrap'>{$label}</div>"
            . "<div style='font-size:1.15rem;font-weight:800;color:{$color};line-height:1.2'>{$value}</div>"
            . '</div></div>';

        $html = "<div style='display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.25rem'>"
            . $card('Jumlah Pesanan', number_format($count, 0, ',', '.'), '#0f172a', '🧾', '#f1f5f9')
            . $card('Total Omzet', $rp($omzet), '#2563eb', '💰', '#eff6ff')
            . $card('Total Laba', $rp($laba), $laba < 0 ? '#dc2626' : '#16a34a', '📈', $laba < 0 ? '#fef2f2' : '#f0fdf4')
            . '</div>';

        return new HtmlString($html);
    }

    // Pesanan masuk lewat Import (tanpa tombol "Buat"). Aksi: isi estimasi biaya admin.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('estimasiAdmin')
                ->label('Isi Estimasi Biaya Admin')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Isi estimasi biaya admin')
                ->modalDescription('Mengisi biaya admin (estimasi dari % kategori produk) untuk pesanan yang BELUM punya biaya admin & belum final. Pesanan dengan laba final tidak diubah. Pastikan produk sudah punya kategori.')
                ->modalSubmitActionLabel('Isi estimasi sekarang')
                ->action(function (): void {
                    $res = app(AdminFeeEstimator::class)->applyToOrg((int) auth()->user()->organization_id);
                    Notification::make()
                        ->title("Estimasi terisi untuk {$res['updated']} pesanan")
                        ->body('Total estimasi biaya admin: Rp ' . number_format($res['total'], 0, ',', '.')
                            . ($res['updated'] === 0 ? ' — pastikan produk sudah dipasangi kategori.' : ''))
                        ->success()
                        ->send();
                }),
        ];
    }
}
