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

        // Hitung 5 angka kartu total. Tampilan DEFAULT (tanpa filter) → di-cache per org
        // (spt dashboard, disegarkan saat impor). Saat ada filter → hitung live agar akurat.
        $compute = fn (): array => [
            'count' => (clone $query)->count(),
            'omzet' => (float) (clone $query)->sum('product_revenue'),
            'laba' => (float) (clone $query)->sum(DB::raw(ProfitService::sqlProfit())),
            'batal' => (clone $query)->where('status', 'CANCELLED')->count(),
            'belumFinal' => (clone $query)->labaBelumFinal()->count(),
        ];
        $d = $this->hasActiveTableFilters()
            ? $compute()
            : \App\Support\DashboardCache::remember('orders_subheading', $compute);

        $count = $d['count'];
        $omzet = $d['omzet'];
        $laba = $d['laba'];
        $batal = $d['batal'];
        $belumFinal = $d['belumFinal'];
        $rp = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $n = fn ($v): string => number_format((int) $v, 0, ',', '.');

        $card = fn (string $label, string $value, string $color, string $icon, string $bg): string =>
            "<div style='flex:1 1 150px;display:flex;align-items:center;gap:.6rem;border:1px solid #e8edf3;border-radius:.7rem;padding:.5rem .75rem;background:#fff'>"
            . "<div style='width:2rem;height:2rem;flex:none;display:flex;align-items:center;justify-content:center;border-radius:.55rem;background:{$bg};font-size:1rem'>{$icon}</div>"
            . "<div style='min-width:0'>"
            . "<div style='font-size:.7rem;color:#64748b;white-space:nowrap'>{$label}</div>"
            . "<div style='font-size:1.15rem;font-weight:800;color:{$color};line-height:1.2'>{$value}</div>"
            . '</div></div>';

        $html = "<div style='display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.25rem'>"
            . $card('Jumlah Pesanan', $n($count), '#0f172a', '🧾', '#f1f5f9')
            . $card('Total Omzet', $rp($omzet), '#2563eb', '💰', '#eff6ff')
            . $card('Total Laba', $rp($laba), $laba < 0 ? '#dc2626' : '#16a34a', '📈', $laba < 0 ? '#fef2f2' : '#f0fdf4')
            . $card('Pesanan Batal', $n($batal), '#dc2626', '🚫', '#fef2f2')
            . $card('Laba Belum Final', $n($belumFinal), '#b45309', '⏳', '#fffbeb')
            . '</div>';

        return new HtmlString($html);
    }

    /** Ada filter tabel yang sedang aktif (punya nilai)? Dipakai utk memutuskan cache kartu total. */
    private function hasActiveTableFilters(): bool
    {
        $flatten = function ($arr) use (&$flatten): array {
            $out = [];
            foreach ((array) $arr as $v) {
                is_array($v) ? $out = array_merge($out, $flatten($v)) : $out[] = $v;
            }

            return $out;
        };

        foreach ($flatten($this->tableFilters ?? []) as $v) {
            if ($v !== null && $v !== '' && $v !== false) {
                return true;
            }
        }

        return false;
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
                ->modalDescription(function (): string {
                    $tanpaKategori = \App\Models\Product::whereNull('category_id')->count();
                    $base = 'Menghitung ulang estimasi biaya (komisi % kategori + Biaya Layanan/Komisi Dinamis + biaya proses Rp1.250) untuk SEMUA pesanan yang belum final. Pesanan batal otomatis jadi Rp0. Pesanan dengan laba final (laporan penghasilan) tidak diubah.';
                    if ($tanpaKategori > 0) {
                        return $base . " Catatan: {$tanpaKategori} produk belum berkategori — estimasinya tetap terisi memakai tarif rata-rata, tapi pasang kategori (menu Produk → \"Auto-pasang Kategori\") agar lebih akurat.";
                    }
                    return $base . ' ✓ Semua produk sudah berkategori.';
                })
                ->modalSubmitActionLabel('Isi estimasi sekarang')
                ->action(function (): void {
                    $res = app(AdminFeeEstimator::class)->applyToOrg((int) auth()->user()->organization_id);
                    \App\Support\DashboardCache::forget((int) auth()->user()->organization_id); // biaya admin berubah → dashboard segar
                    \App\Support\Bell::send(Notification::make()
                        ->title("Estimasi terisi untuk {$res['updated']} pesanan")
                        ->body('Total estimasi biaya admin: Rp ' . number_format($res['total'], 0, ',', '.')
                            . ($res['updated'] === 0 ? ' — pastikan produk sudah dipasangi kategori.' : ''))
                        ->success());
                }),
        ];
    }
}
