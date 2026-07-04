<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Services\ProfitService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class Laporan extends Page
{
    protected string $view = 'filament.pages.laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan Bulanan & Tahunan';

    protected static ?int $navigationSort = 6;

    /** Tahun yang sedang dilihat untuk rincian bulanan. */
    public ?int $tahun = null;

    /** Bulan (1-12) untuk rincian PER TOKO; null = sepanjang tahun. */
    public ?int $bulan = null;

    /** Metrik yang ditampilkan di matriks perbandingan bulanan: laba | omzet | jml. */
    public string $metrik = 'laba';

    /** Urutan matriks: kolom ('nama' | 'total' | '1'..'12') + arah ('asc' | 'desc'). */
    public string $urutKolom = 'total';

    public string $urutArah = 'desc';

    public function mount(): void
    {
        $latest = Order::query()->max('order_date');
        $this->tahun = $latest ? (int) Carbon::parse($latest)->year : (int) now()->year;
    }

    public function pilihTahun(int $tahun): void
    {
        $this->tahun = $tahun;
    }

    public function pilihBulan(?int $bulan): void
    {
        $this->bulan = ($bulan >= 1 && $bulan <= 12) ? $bulan : null;
    }

    public function pilihMetrik(string $metrik): void
    {
        $this->metrik = in_array($metrik, ['laba', 'omzet', 'jml', 'biaya'], true) ? $metrik : 'laba';
    }

    /** Klik judul kolom: kolom sama → balik arah; kolom beda → set kolom (default desc, nama asc). */
    public function urutkan(string $kolom): void
    {
        if ($this->urutKolom === $kolom) {
            $this->urutArah = $this->urutArah === 'desc' ? 'asc' : 'desc';
        } else {
            $this->urutKolom = $kolom;
            $this->urutArah = $kolom === 'nama' ? 'asc' : 'desc';
        }
    }

    public function getViewData(): array
    {
        $pf = ProfitService::sqlProfit();
        $ops = fn () => Order::query()->whereNotIn('status', ['CANCELLED', 'RETURNED']);

        // Ringkasan per TAHUN (semua tahun yang ada datanya).
        $tahunan = $ops()
            ->selectRaw('YEAR(order_date) th, SUM(product_revenue + other_income) omzet, SUM(' . $pf . ') laba, COUNT(*) jml')
            ->groupBy('th')->orderByDesc('th')->get();

        $years = $tahunan->pluck('th')->map(fn ($y) => (int) $y)->all();
        if (! in_array($this->tahun, $years, true) && $years !== []) {
            $this->tahun = $years[0];
        }

        // Rincian per BULAN untuk tahun terpilih (12 bulan penuh).
        $rows = $ops()->whereYear('order_date', $this->tahun)
            ->selectRaw('MONTH(order_date) bln, SUM(product_revenue + other_income) omzet, SUM(' . $pf . ') laba, COUNT(*) jml')
            ->groupBy('bln')->get()->keyBy('bln');

        $bulanan = [];
        for ($m = 1; $m <= 12; $m++) {
            $r = $rows->get($m);
            $bulanan[] = [
                'bln' => $m,
                'omzet' => (float) ($r->omzet ?? 0),
                'laba' => (float) ($r->laba ?? 0),
                'jml' => (int) ($r->jml ?? 0),
            ];
        }

        // Rincian per TOKO untuk periode terpilih (sepanjang tahun, atau satu bulan), urut omzet terbesar.
        $stores = \App\Models\Store::query()->get()->keyBy('id');
        $perTokoQuery = $ops()->whereYear('order_date', $this->tahun);
        if ($this->bulan) {
            $perTokoQuery->whereMonth('order_date', $this->bulan);
        }
        $perToko = $perTokoQuery
            ->selectRaw('store_id, SUM(product_revenue + other_income) omzet, SUM(' . $pf . ') laba, COUNT(*) jml')
            ->groupBy('store_id')->get()
            ->map(function ($r) use ($stores): array {
                $s = $r->store_id ? $stores->get($r->store_id) : null;

                return [
                    'store_id' => $r->store_id ? (int) $r->store_id : null,
                    'nama' => $s?->name ?? 'Tanpa toko',
                    'marketplace' => $s?->marketplace,
                    'channel' => $s ? $s->channel_label : '—',
                    'omzet' => (float) $r->omzet,
                    'laba' => (float) $r->laba,
                    'jml' => (int) $r->jml,
                ];
            })
            ->sortByDesc('omzet')->values()->all();

        // Matriks PERBANDINGAN: tiap toko (baris) × 12 bulan (kolom) untuk tahun terpilih.
        $matrixRows = $ops()->whereYear('order_date', $this->tahun)
            ->selectRaw('store_id, MONTH(order_date) bln, SUM(product_revenue + other_income) omzet, SUM(' . $pf . ') laba, COUNT(*) jml')
            ->groupBy('store_id', 'bln')->get();
        $byStore = [];
        foreach ($matrixRows as $r) {
            $byStore[$r->store_id ? (int) $r->store_id : 0][(int) $r->bln] = $r;
        }
        $colTot = [];
        for ($m = 1; $m <= 12; $m++) {
            $colTot[$m] = ['omzet' => 0.0, 'laba' => 0.0, 'jml' => 0, 'biaya' => 0.0];
        }
        $grand = ['omzet' => 0.0, 'laba' => 0.0, 'jml' => 0, 'biaya' => 0.0];
        $matriks = [];
        foreach ($byStore as $sid => $months) {
            $s = $sid ? $stores->get($sid) : null;
            $bulanCells = [];
            $rowTot = ['omzet' => 0.0, 'laba' => 0.0, 'jml' => 0, 'biaya' => 0.0];
            for ($m = 1; $m <= 12; $m++) {
                $rr = $months[$m] ?? null;
                $omz = (float) ($rr->omzet ?? 0);
                $lb = (float) ($rr->laba ?? 0);
                // Biaya = Omzet − Laba (total semua pengurang: admin/komisi, ongkir, voucher, HPP/modal, dropship, lain).
                $cell = ['omzet' => $omz, 'laba' => $lb, 'jml' => (int) ($rr->jml ?? 0), 'biaya' => $omz - $lb];
                $bulanCells[$m] = $cell;
                foreach (['omzet', 'laba', 'jml', 'biaya'] as $k) {
                    $rowTot[$k] += $cell[$k];
                    $colTot[$m][$k] += $cell[$k];
                    $grand[$k] += $cell[$k];
                }
            }
            $matriks[] = [
                'store_id' => $sid ?: null,
                'nama' => $s?->name ?? 'Tanpa toko',
                'marketplace' => $s?->marketplace,
                'bulan' => $bulanCells,
                'total' => $rowTot,
            ];
        }
        // Urutkan sesuai kolom & arah pilihan (kolom bulan/total memakai metrik aktif).
        $uKol = $this->urutKolom;
        $uMetrik = $this->metrik;
        $nilaiUrut = function (array $row) use ($uKol, $uMetrik) {
            if ($uKol === 'nama') return mb_strtolower($row['nama']);
            if ($uKol === 'total') return $row['total'][$uMetrik];
            return $row['bulan'][(int) $uKol][$uMetrik] ?? 0;
        };
        $uArah = $this->urutArah;
        usort($matriks, function ($a, $b) use ($nilaiUrut, $uKol, $uArah) {
            $va = $nilaiUrut($a);
            $vb = $nilaiUrut($b);
            $cmp = $uKol === 'nama' ? strcmp((string) $va, (string) $vb) : ($va <=> $vb);
            return $uArah === 'asc' ? $cmp : -$cmp;
        });

        // Nilai filter periode utk tautan baris per-toko: 'YYYY' (setahun) atau 'YYYY-MM' (sebulan).
        $periodeValue = $this->bulan ? sprintf('%04d-%02d', $this->tahun, $this->bulan) : (string) $this->tahun;

        return [
            'tahunan' => $tahunan,
            'bulanan' => $bulanan,
            'perToko' => $perToko,
            'matriks' => $matriks,
            'colTot' => $colTot,
            'grand' => $grand,
            'metrik' => $this->metrik,
            'urutKolom' => $this->urutKolom,
            'urutArah' => $this->urutArah,
            'bulan' => $this->bulan,
            'periodeValue' => $periodeValue,
            'years' => $years,
            'tahun' => $this->tahun,
            'urlOrders' => \App\Filament\Resources\Orders\OrderResource::getUrl('index'),
        ];
    }
}
