<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.85rem;padding:1.1rem 1.2rem;background:#fff';
        $ul = 'margin:.35rem 0 0 1.1rem;padding:0;font-size:.8rem;color:#64748b;line-height:1.6';
        $btnWrap = 'max-width:16rem';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1rem;max-width:1040px">
        <p style="margin:0;color:#475569;font-size:.9rem">Simpan cadangan data Anda, pulihkan dari cadangan, atau mulai dari awal. Tiap kotak punya tombolnya sendiri.</p>

        {{-- ===== 1) Unduh Backup ===== --}}
        <div style="{{ $card }};border-left:4px solid #2563eb">
            <div style="display:flex;align-items:flex-start;gap:.85rem">
                <div style="font-size:1.6rem;line-height:1">📥</div>
                <div style="flex:1">
                    <div style="font-weight:700;color:#1e293b;font-size:1.02rem">Unduh Backup</div>
                    <div style="font-size:.85rem;color:#475569;margin:.25rem 0">Simpan <strong>seluruh data Anda</strong> (toko, supplier, produk, pemetaan SKU, pesanan &amp; item) ke satu file <code>.zip</code> di komputer.</div>
                    <ul style="{{ $ul }}">
                        <li>Aman disimpan sebagai pengaman; lakukan berkala (mis. mingguan).</li>
                        <li>Selalu unduh backup dulu sebelum memulihkan / mengosongkan data.</li>
                    </ul>
                    <div style="{{ $btnWrap }};margin-top:.85rem">{{ $this->downloadAction }}</div>
                </div>
            </div>
        </div>

        {{-- ===== 2) Pulihkan dari Backup ===== --}}
        <div style="{{ $card }};border-left:4px solid #f59e0b">
            <div style="display:flex;align-items:flex-start;gap:.85rem">
                <div style="font-size:1.6rem;line-height:1">📤</div>
                <div style="flex:1">
                    <div style="font-weight:700;color:#1e293b;font-size:1.02rem">Pulihkan dari Backup</div>
                    <div style="font-size:.85rem;color:#475569;margin:.25rem 0"><strong>Mengganti</strong> data Anda saat ini dengan isi file backup. Berguna untuk mengembalikan kondisi sebelumnya.</div>
                    <ul style="{{ $ul }}">
                        <li>Unggah file <code>.zip</code> hasil <em>Unduh Backup</em>, lalu ketik konfirmasi.</li>
                        <li>Data saat ini akan ditimpa — pastikan filenya benar.</li>
                    </ul>
                    <div style="{{ $btnWrap }};margin-top:.85rem">{{ $this->restoreAction }}</div>
                </div>
            </div>
        </div>

        {{-- ===== 3) Kosongkan Data ===== --}}
        <div style="{{ $card }};border-left:4px solid #ef4444">
            <div style="display:flex;align-items:flex-start;gap:.85rem">
                <div style="font-size:1.6rem;line-height:1">🗑️</div>
                <div style="flex:1">
                    <div style="font-weight:700;color:#1e293b;font-size:1.02rem">Kosongkan Data</div>
                    <div style="font-size:.85rem;color:#475569;margin:.25rem 0">Menghapus pesanan (atau semua data bisnis) untuk mulai dari awal. <strong>Kategori &amp; akun tetap</strong>.</div>
                    <ul style="{{ $ul }}">
                        <li>Pilih cakupan: hanya pesanan, atau semua data bisnis.</li>
                        <li><strong style="color:#b91c1c">Permanen &amp; tidak bisa dibatalkan</strong> — unduh backup dulu!</li>
                    </ul>
                    <div style="{{ $btnWrap }};margin-top:.85rem">{{ $this->clearAction }}</div>
                </div>
            </div>
        </div>

        <p style="margin:.2rem 0 0;font-size:.8rem;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.55rem .7rem">
            ⚠️ <strong>Pulihkan &amp; Kosongkan mengubah/menghapus data</strong> — keduanya hanya berlaku untuk data milik Anda sendiri. Selalu <strong>Unduh Backup</strong> dulu.
        </p>
    </div>
</x-filament-panels::page>
