<x-filament-panels::page>
    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $card = 'border:1px solid rgb(229 231 235);border-radius:.75rem;padding:1rem;background:#fff';
        $th = 'text-align:left;padding:.5rem .5rem;font-size:.72rem;color:#64748b;text-transform:uppercase;border-bottom:1px solid #eef2f7';
        $td = 'padding:.5rem .5rem;font-size:.85rem;border-top:1px solid #f1f5f9';
    @endphp

    {{-- Statistik --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem">
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Pesanan Rugi (Selesai)</div>
            <div style="font-size:1.6rem;font-weight:800;color:#dc2626">{{ number_format($jmlRugi, 0, ',', '.') }}</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Total Nilai Rugi</div>
            <div style="font-size:1.6rem;font-weight:800;color:#dc2626">{{ $rp($nilaiRugi) }}</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Rasio Retur</div>
            <div style="font-size:1.6rem;font-weight:800;color:#d97706">{{ $rasioRetur }}%</div>
            <div style="font-size:.7rem;color:#94a3b8">{{ $jmlRetur }} dari {{ number_format($totalPesanan, 0, ',', '.') }}</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Dibatalkan</div>
            <div style="font-size:1.6rem;font-weight:800;color:#475569">{{ number_format($jmlBatal, 0, ',', '.') }}</div>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">📉 Produk Merugi — dijual di bawah modal</x-slot>
        <x-slot name="description">Harga jual rata-rata lebih rendah dari HPP. Pertimbangkan naikkan harga atau stop jual.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th>
                    <th style="{{ $th }};text-align:right">Avg Jual</th><th style="{{ $th }};text-align:right">Avg Modal</th>
                    <th style="{{ $th }};text-align:right">Selisih/pcs</th><th style="{{ $th }};text-align:right">×terjual</th>
                </tr></thead>
                <tbody>
                @forelse ($bawahModal as $b)
                    <tr>
                        <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $b->sku }}</td>
                        <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($b->name, 40) }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b->avg_jual) }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b->avg_modal) }}</td>
                        <td style="{{ $td }};text-align:right;color:#dc2626;font-weight:700">{{ $rp($b->avg_jual - $b->avg_modal) }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ $b->n }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }};text-align:center;color:#94a3b8">Tidak ada produk dijual di bawah modal. 👍</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem">
        <x-filament::section>
            <x-slot name="heading">🔻 Pesanan Rugi Terbesar (Selesai)</x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="{{ $th }}">No. Pesanan</th><th style="{{ $th }}">Tanggal</th>
                        <th style="{{ $th }};text-align:right">Omzet</th><th style="{{ $th }};text-align:right">Laba</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($pesananRugi as $o)
                        <tr>
                            <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $o->external_no }}</td>
                            <td style="{{ $td }};color:#64748b">{{ $o->order_date?->format('d M Y') }}</td>
                            <td style="{{ $td }};text-align:right">{{ $rp($o->product_revenue) }}</td>
                            <td style="{{ $td }};text-align:right;color:#dc2626;font-weight:700">{{ $rp($o->profit) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="{{ $td }};text-align:center;color:#94a3b8">Tidak ada pesanan rugi. 🎉</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">🏆 Produk Terlaris (qty)</x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th><th style="{{ $th }};text-align:right">Terjual</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($terlaris as $t)
                        <tr>
                            <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $t->sku }}</td>
                            <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($t->name, 35) }}</td>
                            <td style="{{ $td }};text-align:right;font-weight:700">{{ number_format($t->total_qty, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
