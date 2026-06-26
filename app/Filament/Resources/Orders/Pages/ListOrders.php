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
            'omzet' => (float) (clone $query)->sum(DB::raw('product_revenue + other_income')),
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

        // Kartu BISA DIKLIK → buka data terkait yang sudah terfilter (atau halaman Insight).
        $url = fn (array $filters = []): string => OrderResource::getUrl('index')
            . ($filters ? '?' . http_build_query(['filters' => $filters]) : '');
        $insightUrl = \App\Filament\Pages\Insight::getUrl();

        // Ikon garis (heroicon outline inline) diwarnai sesuai aksen kartu; seluruh kartu jadi tautan.
        $card = fn (string $label, string $value, string $color, string $iconPath, string $bg, string $href): string =>
            "<a href='{$href}' class='mkz-stat-card' style='flex:1 1 150px;display:flex;align-items:center;gap:.6rem;border:1px solid #e8edf3;border-radius:.7rem;padding:.5rem .75rem;background:#fff;text-decoration:none;cursor:pointer'>"
            . "<div style='width:2rem;height:2rem;flex:none;display:flex;align-items:center;justify-content:center;border-radius:.55rem;background:{$bg}'>"
            . "<svg style='width:1.15rem;height:1.15rem;color:{$color}' fill='none' viewBox='0 0 24 24' stroke-width='1.7' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' d='{$iconPath}'/></svg>"
            . '</div>'
            . "<div style='min-width:0'>"
            . "<div style='font-size:.7rem;color:#64748b;white-space:nowrap'>{$label}</div>"
            . "<div style='font-size:1.15rem;font-weight:800;color:{$color};line-height:1.2'>{$value}</div>"
            . '</div></a>';

        $html = "<div style='display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.25rem'>"
            . $card('Jumlah Pesanan', $n($count), '#0f172a', 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z', '#f1f5f9', $url())
            . $card('Total Omzet', $rp($omzet), '#2563eb', 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V12Zm-12 0h.008v.008H6V12Z', '#eff6ff', $url())
            . $card('Total Laba', $rp($laba), $laba < 0 ? '#dc2626' : '#16a34a', 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941', $laba < 0 ? '#fef2f2' : '#f0fdf4', $insightUrl)
            . $card('Pesanan Batal', $n($batal), '#dc2626', 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', '#fef2f2', $url(['status' => ['values' => ['CANCELLED']]]))
            . $card('Laba Belum Final', $n($belumFinal), '#b45309', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', '#fffbeb', $url(['status_laba' => ['value' => 'belum_final']]))
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
