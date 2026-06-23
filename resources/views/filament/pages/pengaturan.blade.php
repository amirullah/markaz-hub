<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.75rem;padding:1.25rem;background:#fff';
        $dropship = (bool) ($org?->uses_dropship ?? true);
        $svcPct = rtrim(rtrim(number_format((float) ($org?->fee_shopee_service_pct ?? 10), 2, '.', ''), '0'), '.');
        $svcCap = (int) ($org?->fee_shopee_service_cap ?? 10000);
        $dynPct = rtrim(rtrim(number_format((float) ($org?->fee_tokotiktok_dynamic_pct ?? 6.5), 2, '.', ''), '0'), '.');
        $row = 'display:flex;justify-content:space-between;gap:1rem;font-size:.85rem;padding:.35rem 0;border-top:1px solid #f1f5f9';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1rem;max-width:640px">
        <div style="{{ $card }}">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.25rem">🏪 {{ $org?->name ?? 'Organisasi' }}</div>
            <div style="font-size:.85rem;color:#64748b">Atur fitur yang sesuai dengan cara Anda berjualan.</div>
        </div>

        <div style="{{ $card }};display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <div>
                <div style="font-weight:600;color:#1e293b">Berjualan Dropship</div>
                <div style="font-size:.82rem;color:#64748b;margin-top:.25rem;max-width:46ch">
                    Aktifkan bila sebagian/semua pesanan Anda dropship — dari sumber mana pun (supplier/perusahaan lain, atau manual dari seller lain). Aktif: kolom & biaya dropship + pemenuhan tampil pada produk & pesanan. Nonaktif: tampilan dropship disembunyikan & laba dihitung sebagai packing sendiri.
                </div>
            </div>
            <div style="flex-shrink:0">
                @if ($dropship)
                    <span style="display:inline-block;background:#dcfce7;color:#15803d;font-weight:700;font-size:.82rem;padding:.4rem .9rem;border-radius:999px">● Aktif</span>
                @else
                    <span style="display:inline-block;background:#f1f5f9;color:#64748b;font-weight:700;font-size:.82rem;padding:.4rem .9rem;border-radius:999px">○ Nonaktif</span>
                @endif
            </div>
        </div>

        <div style="{{ $card }}">
            <div style="font-weight:700;color:#1e293b">💸 Biaya Marketplace (untuk estimasi laba)</div>
            <div style="font-size:.82rem;color:#64748b;margin:.25rem 0 .6rem;max-width:60ch">
                Dipakai saat <strong>Laporan Penghasilan belum diimpor</strong>. Komisi/biaya admin per kategori diatur di menu <strong>Kategori</strong>; komponen di bawah adalah biaya TAMBAHAN sesuai struktur biaya nyata marketplace.
            </div>
            <div style="{{ $row }}"><span>Shopee — Biaya Layanan</span><strong>{{ $svcPct }}%{{ $svcCap > 0 ? ' (maks Rp' . number_format($svcCap, 0, ',', '.') . ')' : '' }}</strong></div>
            <div style="{{ $row }}"><span>Tokopedia/TikTok — Komisi Dinamis</span><strong>{{ $dynPct }}%</strong></div>
            <div style="{{ $row }}"><span>Biaya Proses Pesanan (kedua platform)</span><strong>Rp1.250</strong></div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:.6rem">Rumus estimasi: (komisi kategori % + biaya tambahan di atas) × subtotal + Rp1.250. Saat Laporan Penghasilan resmi masuk, biaya asli menggantikan estimasi ini.</div>
        </div>

        <p style="font-size:.8rem;color:#94a3b8">Klik <strong>Ubah Pengaturan</strong> di kanan atas untuk mengubah.</p>
    </div>
</x-filament-panels::page>
