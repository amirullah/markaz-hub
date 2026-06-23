<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Backup & pemulihan data</x-slot>
        <x-slot name="description">Simpan cadangan data Anda, atau pulihkan dari cadangan sebelumnya.</x-slot>
        <div style="font-size:.9rem;color:#475569;line-height:1.7">
            <p><strong>📥 Unduh Backup</strong> — menyimpan seluruh data Anda (toko, supplier, produk, pemetaan SKU, pesanan, dan item) ke satu file <code>.sql</code> di komputer Anda.</p>
            <p style="margin-top:.5rem"><strong>📤 Pulihkan dari Backup</strong> — mengganti data Anda saat ini dengan isi file backup. Berguna jika ingin mengembalikan kondisi sebelumnya.</p>
            <p style="margin-top:.5rem"><strong>🗑️ Kosongkan Data</strong> — menghapus pesanan (atau semua data bisnis) untuk mulai dari awal. Kategori, toko/akun tetap (sesuai pilihan).</p>
            <ul style="list-style:disc;margin:.6rem 0 0 1.25rem">
                <li>File backup berisi <strong>seluruh data Anda</strong> — aman disimpan sebagai pengaman.</li>
                <li>Lakukan backup secara berkala (mis. mingguan).</li>
                <li>Pemulihan & pengosongan <strong>mengubah/menghapus</strong> data — <strong>unduh backup dulu</strong> sebelum melakukannya.</li>
            </ul>
            <div style="margin-top:.75rem;padding:.6rem .85rem;background:#fef2f2;border:1px solid #fecaca;border-radius:.6rem;color:#b91c1c;font-size:.85rem">
                ⚠️ <strong>Kosongkan Data bersifat permanen</strong> dan tidak bisa dibatalkan. Hanya mengosongkan data milik Anda sendiri.
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
