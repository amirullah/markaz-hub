<x-filament-panels::page>
    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $card = 'border:1px solid #e8edf3;border-radius:.75rem;padding:.85rem 1rem;background:#fff';
        $th = 'text-align:left;padding:.5rem .5rem;font-size:.72rem;color:#64748b;text-transform:uppercase;border-bottom:1px solid #eef2f7';
        $td = 'padding:.5rem .5rem;font-size:.85rem;border-top:1px solid #f1f5f9';
        // Ikon garis (heroicon) + kotak warna — seragam dgn kartu Pesanan/Dashboard.
        $svg = fn ($path, $color) => '<svg style="width:1.3rem;height:1.3rem;color:' . $color . '" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '"/></svg>';
        $hicon = fn ($path, $color) => '<svg style="width:1.1rem;height:1.1rem;vertical-align:-3px;margin-right:.35rem;color:' . $color . '" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '"/></svg>';
        $statCard = fn ($label, $value, $color, $path, $bg, $sub = null, $href = null) =>
            ($href
                ? '<a href="' . $href . '" class="mkz-stat-card" style="' . $card . ';display:flex;align-items:center;gap:.7rem;text-decoration:none;cursor:pointer">'
                : '<div style="' . $card . ';display:flex;align-items:center;gap:.7rem">')
            . '<div style="width:2.4rem;height:2.4rem;flex:none;display:flex;align-items:center;justify-content:center;border-radius:.6rem;background:' . $bg . '">' . $svg($path, $color) . '</div>'
            . '<div style="min-width:0"><div style="font-size:.78rem;color:#64748b">' . $label . '</div>'
            . '<div style="font-size:1.35rem;font-weight:800;color:' . $color . ';line-height:1.2">' . $value . '</div>'
            . ($sub ? '<div style="font-size:.7rem;color:#94a3b8">' . $sub . '</div>' : '') . '</div>'
            . ($href ? '</a>' : '</div>');
        $pUp = 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941';
        $pDown = 'M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181';
        $pChart = 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z';
        $pWarn = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z';
        $pReturn = 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3';
        $pTrophy = 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0';
    @endphp

    {{-- Statistik utama — kartu bisa diklik ke data terkait (filter auto-terpilih lewat URL) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem">
        {!! $statCard('Total Laba (Selesai)', $rp($totalLaba), $totalLaba < 0 ? '#dc2626' : '#16a34a', $totalLaba < 0 ? $pDown : $pUp, $totalLaba < 0 ? '#fef2f2' : '#f0fdf4', null, $urlSelesai) !!}
        {!! $statCard('Margin Laba', $margin . '%', $margin < 0 ? '#dc2626' : ($margin < 10 ? '#d97706' : '#16a34a'), $pChart, $margin < 0 ? '#fef2f2' : ($margin < 10 ? '#fffbeb' : '#f0fdf4'), null, $urlSelesai) !!}
        {!! $statCard('Pesanan Rugi (Selesai)', number_format($jmlRugi, 0, ',', '.'), '#dc2626', $pWarn, '#fef2f2', 'nilai ' . $rp($nilaiRugi), $urlRugi) !!}
        {!! $statCard('Produk di Bawah Modal', number_format($jmlProdukRugi, 0, ',', '.'), '#dc2626', $pDown, '#fef2f2', null, '#produk-merugi') !!}
        {!! $statCard('Rasio Retur', $rasioRetur . '%', '#d97706', $pReturn, '#fffbeb', $jmlRetur . ' dari ' . number_format($totalPesanan, 0, ',', '.') . ' · batal ' . $jmlBatal, $urlRetur) !!}
    </div>

    @if ($jmlRugi > 0)
    {{-- KENAPA pesanan rugi + TINDAKAN dekat --}}
    <x-filament::section>
        <x-slot name="heading">{!! $hicon($pWarn, '#dc2626') !!}Kenapa {{ number_format($jmlRugi, 0, ',', '.') }} pesanan rugi — &amp; apa yang harus segera dilakukan</x-slot>
        <x-slot name="description">Total kerugian {{ $rp($nilaiRugi) }}. Tiap pesanan rugi dikelompokkan ke 1 sebab dominan.</x-slot>

        @php
            $sebabRows = [
                ['Jual di bawah modal', $sebab['bawahModal'], '#dc2626', 'Harga jual < HPP. Solusi cepat: naikkan harga atau setop jual rugi.'],
                ['Biaya admin tinggi (>30% omzet)', $sebab['adminTinggi'], '#d97706', 'Komisi marketplace memakan margin. Naikkan harga atau kurangi diskon.'],
                ['Voucher/ongkir ditanggung besar', $sebab['voucherBesar'], '#7c3aed', 'Subsidi voucher/ongkir > 20% omzet. Batasi voucher yang ditanggung penjual.'],
                ['Margin tipis (biaya > untung kotor)', $sebab['marginTipis'], '#64748b', 'Untung kotor terlalu tipis untuk menutup biaya. Tinjau harga/HPP produk.'],
            ];
            $btn = 'display:flex;align-items:center;gap:.5rem;text-decoration:none;border:1px solid #e8edf3;border-radius:.6rem;padding:.6rem .8rem;background:#fff;font-size:.83rem;color:#0f172a';
        @endphp
        <div style="display:flex;flex-direction:column;gap:.65rem">
            @foreach ($sebabRows as [$lbl, $n, $clr, $desc])
                @php $pct = $jmlRugi > 0 ? round($n / $jmlRugi * 100) : 0; @endphp
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:baseline;font-size:.82rem;margin-bottom:.25rem">
                        <span style="font-weight:600;color:#0f172a">{{ $lbl }}</span>
                        <span style="color:{{ $clr }};font-weight:700;white-space:nowrap">{{ number_format($n, 0, ',', '.') }} pesanan · {{ $pct }}%</span>
                    </div>
                    <div style="height:.5rem;background:#f1f5f9;border-radius:999px;overflow:hidden"><div style="height:100%;width:{{ max($pct, 1) }}%;background:{{ $clr }}"></div></div>
                    <div style="font-size:.72rem;color:#94a3b8;margin-top:.25rem">{{ $desc }}</div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:1.1rem;border-top:1px solid #eef2f7;padding-top:.9rem">
            <div style="font-size:.82rem;font-weight:700;color:#0f172a;margin-bottom:.6rem">⚡ Tindakan dalam waktu dekat</div>
            <div style="display:flex;flex-direction:column;gap:.5rem">
                @if ($jmlProdukRugi > 0)
                    <a href="{{ $urlProduk }}" class="mkz-stat-card" style="{{ $btn }}">{!! $hicon($pUp, '#dc2626') !!}<span><b>Naikkan harga / perbaiki HPP</b> untuk {{ number_format($jmlProdukRugi, 0, ',', '.') }} produk yang dijual di bawah modal (rincian di tabel bawah).</span></a>
                @endif
                @if ($labaSemu > 0)
                    <a href="{{ $urlImpor }}" class="mkz-stat-card" style="{{ $btn }}">{!! $hicon('M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 18 4.5H6a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 6 19.5Z', '#d97706') !!}<span><b>Impor "Daftar Produk"</b> untuk isi HPP {{ number_format($labaSemu, 0, ',', '.') }} pesanan "laba semu" — laba sekarang masih ter-overstate sampai HPP terisi.</span></a>
                @endif
                @if ($sebab['adminTinggi'] > 0 || $sebab['marginTipis'] > 0)
                    <a href="{{ $urlOrders }}" class="mkz-stat-card" style="{{ $btn }}">{!! $hicon($pChart, '#2563eb') !!}<span><b>Isi/segarkan estimasi biaya admin</b> (tombol di halaman Pesanan) &amp; tinjau tarif kategori, agar margin tipis tak berubah jadi rugi.</span></a>
                @endif
            </div>
        </div>
    </x-filament::section>
    @endif

    {{-- Produk Merugi (klik untuk detail) --}}
    <div id="produk-merugi" style="scroll-margin-top:5rem"></div>
    <x-filament::section>
        <x-slot name="heading">{!! $hicon($pDown, '#dc2626') !!}Produk Merugi — dijual di bawah modal</x-slot>
        <x-slot name="description">Diurutkan dari kerugian terbesar. Klik baris untuk lihat detail produk.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th>
                    <th style="{{ $th }};text-align:right">Avg Jual</th><th style="{{ $th }};text-align:right">Avg Modal</th>
                    <th style="{{ $th }};text-align:right">Qty</th><th style="{{ $th }};text-align:right">Total Rugi</th>
                </tr></thead>
                <tbody>
                @forelse ($bawahModal as $b)
                    <tr wire:click="showDetail(@js($b->sku))" style="cursor:pointer" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $b->sku }}</td>
                        <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($b->name, 40) }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b->avg_jual) }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b->avg_modal) }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($b->qty_terjual, 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right;color:#dc2626;font-weight:700">{{ $rp($b->total_rugi) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }};text-align:center;color:#94a3b8">Tidak ada produk dijual di bawah modal. 👍</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Produk Paling Untung --}}
    <x-filament::section>
        <x-slot name="heading">{!! $hicon($pUp, '#16a34a') !!}Produk Paling Untung</x-slot>
        <x-slot name="description">Penyumbang laba terbesar (pesanan selesai). Pertahankan & promosikan.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th>
                    <th style="{{ $th }};text-align:right">Qty</th><th style="{{ $th }};text-align:right">Total Untung</th>
                </tr></thead>
                <tbody>
                @forelse ($palingUntung as $p)
                    <tr>
                        <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $p->sku }}</td>
                        <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($p->name, 40) }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($p->qty_terjual, 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right;color:#16a34a;font-weight:700">{{ $rp($p->total_untung) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem">
        <x-filament::section>
            <x-slot name="heading">{!! $hicon($pWarn, '#dc2626') !!}Pesanan Rugi Terbesar (Selesai)</x-slot>
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
            <x-slot name="heading">{!! $hicon($pTrophy, '#d97706') !!}Produk Terlaris (qty)</x-slot>
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

    {{-- Modal detail produk (muncul saat baris produk merugi diklik) --}}
    @if ($detail)
        <div wire:click.self="closeDetail"
             style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:50;display:flex;align-items:center;justify-content:center;padding:1rem">
            <div style="background:#fff;border-radius:1rem;max-width:460px;width:100%;padding:1.5rem;box-shadow:0 24px 70px rgba(0,0,0,.35)">
                <div style="display:flex;justify-content:space-between;align-items:start;gap:1rem;margin-bottom:1rem">
                    <div>
                        <div style="font-size:.72rem;color:#94a3b8;font-family:monospace">{{ $detail['sku'] }}</div>
                        <div style="font-weight:700;font-size:1rem;line-height:1.3">{{ $detail['name'] }}</div>
                        @if ($detail['kategori'])
                            <span style="display:inline-block;margin-top:.4rem;background:#eff6ff;color:#2563eb;font-size:.72rem;padding:.15rem .6rem;border-radius:999px">{{ $detail['kategori'] }}</span>
                        @endif
                    </div>
                    <button wire:click="closeDetail" style="background:#f1f5f9;border:none;border-radius:.5rem;width:2rem;height:2rem;cursor:pointer;font-size:1.1rem;color:#475569">×</button>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">HPP / Modal</div><div style="font-weight:700">{{ $detail['hpp'] !== null ? $rp($detail['hpp']) : '—' }}</div></div>
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">Total Terjual</div><div style="font-weight:700">{{ number_format($detail['total_terjual'], 0, ',', '.') }} pcs</div></div>
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">Avg Harga Jual</div><div style="font-weight:700">{{ $rp($detail['avg_jual']) }}</div></div>
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">Avg Modal</div><div style="font-weight:700">{{ $rp($detail['avg_modal']) }}</div></div>
                    <div style="{{ $card }};grid-column:1 / -1;background:#fef2f2;border-color:#fecaca">
                        <div style="font-size:.72rem;color:#b91c1c">Total Kerugian ({{ $detail['transaksi_rugi'] }} transaksi · {{ $detail['qty_rugi'] }} pcs di bawah modal)</div>
                        <div style="font-weight:800;font-size:1.3rem;color:#dc2626">{{ $rp($detail['total_rugi']) }}</div>
                    </div>
                </div>
                <p style="margin-top:1rem;font-size:.78rem;color:#64748b">💡 Saran: naikkan harga jual minimal di atas HPP, atau perbarui HPP bila modal sudah berubah (menu Produk).</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>
