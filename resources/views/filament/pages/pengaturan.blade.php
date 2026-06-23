<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.75rem;padding:1.25rem;background:#fff';
        $dropship = (bool) ($org?->uses_dropship ?? true);
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

        <p style="font-size:.8rem;color:#94a3b8">Klik <strong>Ubah Pengaturan</strong> di kanan atas untuk mengubah.</p>
    </div>
</x-filament-panels::page>
