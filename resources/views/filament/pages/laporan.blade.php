<x-filament-panels::page>
    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $namaBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $th = 'text-align:left;padding:.5rem .6rem;font-size:.72rem;color:#64748b;text-transform:uppercase;border-bottom:1px solid #eef2f7';
        $td = 'padding:.5rem .6rem;font-size:.85rem;border-top:1px solid #f1f5f9';
        $urlPeriode = fn ($value) => $urlOrders . '?' . http_build_query(['filters' => ['bulan_tahun' => ['value' => $value]]]);
        $totOmzet = collect($bulanan)->sum('omzet');
        $totLaba = collect($bulanan)->sum('laba');
        $totJml = collect($bulanan)->sum('jml');
        // Tautan ke Pesanan terfilter: toko (multi-pilih: values[]) + tahun.
        $urlTokoTahun = fn ($storeId) => $urlOrders . '?' . http_build_query(['filters' => [
            'store_id' => ['values' => [$storeId]],
            'bulan_tahun' => ['value' => (string) $tahun],
        ]]);
        $urlTahunSaja = fn () => $urlOrders . '?' . http_build_query(['filters' => ['bulan_tahun' => ['value' => (string) $tahun]]]);
        $chBadge = function ($mp) {
            $label = \App\Models\Store::channelLabel($mp);
            [$bg, $fg] = $mp === 'SHOPEE' ? ['#fef3c7', '#92400e'] : ['#dcfce7', '#166534'];
            return '<span style="display:inline-block;font-size:10px;line-height:1.4;padding:1px 7px;border-radius:6px;background:' . $bg . ';color:' . $fg . '">' . e($label) . '</span>';
        };
        $totTokoOmzet = collect($perToko)->sum('omzet');
        $totTokoLaba = collect($perToko)->sum('laba');
        $totTokoJml = collect($perToko)->sum('jml');
    @endphp

    {{-- Pemilih tahun --}}
    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <span style="font-size:.85rem;color:#64748b;font-weight:600">Tahun:</span>
        @forelse ($years as $y)
            <button type="button" wire:click="pilihTahun({{ $y }})"
                style="border:1px solid {{ $y === $tahun ? '#2563eb' : '#e8edf3' }};background:{{ $y === $tahun ? '#2563eb' : '#fff' }};color:{{ $y === $tahun ? '#fff' : '#0f172a' }};border-radius:9999px;padding:.25rem .95rem;font-size:.85rem;cursor:pointer">{{ $y }}</button>
        @empty
            <span style="color:#94a3b8">Belum ada data pesanan.</span>
        @endforelse
    </div>

    {{-- Laporan bulanan (tahun terpilih) --}}
    <x-filament::section>
        <x-slot name="heading">Laporan Bulanan — {{ $tahun }}</x-slot>
        <x-slot name="description">Klik baris bulan untuk membuka pesanannya (terfilter otomatis). Pesanan batal/retur tidak dihitung.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">Bulan</th>
                    <th style="{{ $th }};text-align:right">Pesanan</th>
                    <th style="{{ $th }};text-align:right">Omzet</th>
                    <th style="{{ $th }};text-align:right">Laba</th>
                    <th style="{{ $th }};text-align:right">Margin</th>
                </tr></thead>
                <tbody>
                @foreach ($bulanan as $b)
                    @php
                        $m = $b['bln'];
                        $margin = $b['omzet'] > 0 ? round($b['laba'] / $b['omzet'] * 100, 1) : 0;
                        $dari = sprintf('%04d-%02d-01', $tahun, $m);
                        $sampai = \Illuminate\Support\Carbon::create($tahun, $m, 1)->endOfMonth()->format('Y-m-d');
                    @endphp
                    <tr @if ($b['jml'] > 0) onclick="window.location='{{ $urlPeriode(sprintf('%04d-%02d', $tahun, $m)) }}'" style="cursor:pointer" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''" @else style="opacity:.5" @endif>
                        <td style="{{ $td }}">{{ $namaBulan[$m] }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($b['jml'], 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b['omzet']) }}</td>
                        <td style="{{ $td }};text-align:right;font-weight:700;color:{{ $b['laba'] < 0 ? '#dc2626' : '#16a34a' }}">{{ $rp($b['laba']) }}</td>
                        <td style="{{ $td }};text-align:right;color:{{ $margin < 0 ? '#dc2626' : '#64748b' }}">{{ $margin }}%</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr style="font-weight:800">
                    <td style="{{ $td }};border-top:2px solid #e8edf3">Total {{ $tahun }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right">{{ number_format($totJml, 0, ',', '.') }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right">{{ $rp($totOmzet) }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right;color:{{ $totLaba < 0 ? '#dc2626' : '#16a34a' }}">{{ $rp($totLaba) }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right">{{ $totOmzet > 0 ? round($totLaba / $totOmzet * 100, 1) : 0 }}%</td>
                </tr></tfoot>
            </table>
        </div>
    </x-filament::section>

    {{-- Laporan per toko (tahun terpilih) --}}
    <x-filament::section>
        <x-slot name="heading">Laporan per Toko — {{ $tahun }}</x-slot>
        <x-slot name="description">Omzet & laba tiap toko untuk tahun ini (channel ditampilkan di bawah nama). Klik baris untuk membuka pesanannya. Batal/retur tidak dihitung.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">Toko</th>
                    <th style="{{ $th }};text-align:right">Pesanan</th>
                    <th style="{{ $th }};text-align:right">Omzet</th>
                    <th style="{{ $th }};text-align:right">Laba</th>
                    <th style="{{ $th }};text-align:right">Margin</th>
                </tr></thead>
                <tbody>
                @forelse ($perToko as $t)
                    @php
                        $mg = $t['omzet'] > 0 ? round($t['laba'] / $t['omzet'] * 100, 1) : 0;
                        $href = $t['store_id'] ? $urlTokoTahun($t['store_id']) : $urlTahunSaja();
                    @endphp
                    <tr onclick="window.location='{{ $href }}'" style="cursor:pointer" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <td style="{{ $td }}">
                            <span style="font-weight:600">{{ $t['nama'] }}</span>
                            <div style="margin-top:3px">{!! $chBadge($t['marketplace']) !!}</div>
                        </td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($t['jml'], 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($t['omzet']) }}</td>
                        <td style="{{ $td }};text-align:right;font-weight:700;color:{{ $t['laba'] < 0 ? '#dc2626' : '#16a34a' }}">{{ $rp($t['laba']) }}</td>
                        <td style="{{ $td }};text-align:right;color:{{ $mg < 0 ? '#dc2626' : '#64748b' }}">{{ $mg }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada pesanan tahun ini.</td></tr>
                @endforelse
                </tbody>
                @if (count($perToko) > 0)
                <tfoot><tr style="font-weight:800">
                    <td style="{{ $td }};border-top:2px solid #e8edf3">Total {{ count($perToko) }} toko</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right">{{ number_format($totTokoJml, 0, ',', '.') }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right">{{ $rp($totTokoOmzet) }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right;color:{{ $totTokoLaba < 0 ? '#dc2626' : '#16a34a' }}">{{ $rp($totTokoLaba) }}</td>
                    <td style="{{ $td }};border-top:2px solid #e8edf3;text-align:right">{{ $totTokoOmzet > 0 ? round($totTokoLaba / $totTokoOmzet * 100, 1) : 0 }}%</td>
                </tr></tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    {{-- Laporan tahunan --}}
    <x-filament::section>
        <x-slot name="heading">Laporan Tahunan</x-slot>
        <x-slot name="description">Ringkasan semua tahun. Klik baris untuk membuka pesanan tahun tersebut.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">Tahun</th>
                    <th style="{{ $th }};text-align:right">Pesanan</th>
                    <th style="{{ $th }};text-align:right">Omzet</th>
                    <th style="{{ $th }};text-align:right">Laba</th>
                    <th style="{{ $th }};text-align:right">Margin</th>
                </tr></thead>
                <tbody>
                @forelse ($tahunan as $t)
                    @php $om = (float) $t->omzet; $lb = (float) $t->laba; $mg = $om > 0 ? round($lb / $om * 100, 1) : 0; @endphp
                    <tr onclick="window.location='{{ $urlPeriode((string) $t->th) }}'" style="cursor:pointer" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <td style="{{ $td }};font-weight:700">{{ $t->th }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($t->jml, 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($om) }}</td>
                        <td style="{{ $td }};text-align:right;font-weight:700;color:{{ $lb < 0 ? '#dc2626' : '#16a34a' }}">{{ $rp($lb) }}</td>
                        <td style="{{ $td }};text-align:right;color:{{ $mg < 0 ? '#dc2626' : '#64748b' }}">{{ $mg }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
