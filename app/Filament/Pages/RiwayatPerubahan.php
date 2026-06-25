<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class RiwayatPerubahan extends Page
{
    protected string $view = 'filament.pages.riwayat-perubahan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Riwayat Perubahan';

    protected static ?string $title = 'Riwayat Perubahan';

    protected static ?int $navigationSort = 9;

    /**
     * Catatan perubahan PER HARI (terbaru di atas). Tambahkan poin baru ke
     * tanggal teratas setiap ada perubahan.
     */
    public const CHANGELOG = [
        [
            'date' => '25 Juni 2026',
            'changes' => [
                'Halaman Pesanan lebih lengkap: filter Periode cepat (Bulan ini / 30 hari / 90 hari / Tahun ini) plus rentang tanggal sendiri, dan kini bisa pilih BEBERAPA toko & BEBERAPA status sekaligus (mis. Dibatalkan + Dikirim). Tiap baris ringkas dua baris, klik baris untuk membuka pesanan, kolom Margin % bisa diurutkan, pencarian ikut nama pembeli.',
                'Halaman Produk dirapikan: klik baris untuk mengubah produk, kolom (Modal/Kategori/Admin %) bisa diurutkan, plus filter lengkap — Supplier, Kategori, Aktif/Nonaktif, harga pernah berubah, rentang modal, dan rentang tanggal harga diubah.',
                'Halaman detail/ubah Pesanan ditata ulang 2 kolom: rincian produk di kiri dan panel "Ringkasan Laba" di kanan yang menghitung laba langsung saat diubah — lengkap dengan judul No. Pesanan, info toko/tanggal/pembeli, dan tombol Kembali.',
                'BARU — Pengingat upload pintar: Dashboard & halaman Impor menampilkan file apa yang perlu diunggah, berapa pesanan terdampak, dan rentang tanggalnya — agar laba cepat final ("Data lengkap" bila tak ada yang kurang).',
                'BARU — Tombol "Tandai Mode Otomatis" di halaman Toko: sistem menebak mode tiap toko (Dropship saja / Packing sendiri saja) dari riwayat pesanannya, tanpa menimpa toko yang sudah Anda tandai sendiri.',
                'BARU — Halaman error yang ramah: bila terjadi 404/403/gangguan server, muncul halaman ber-logo MarkazHub berbahasa Indonesia yang menjelaskan kemungkinan penyebab (mis. data milik akun lain) dengan tombol kembali ke Dashboard.',
                'BARU — Menu Laporan: laporan laba BULANAN & TAHUNAN (omzet, laba, margin, jumlah pesanan per periode). Klik baris periode untuk langsung membuka pesanannya.',
                'BARU — Mode toko: tandai tiap toko sebagai "Dropship saja", "Packing sendiri saja", atau "Keduanya". Sistem otomatis menandai "Pesanan Janggal" — pesanan yang pemenuhannya tak sesuai mode tokonya — di Dashboard, halaman Toko, dan filter Pesanan.',
                'Backup lebih aman & ringkas: file .zip kecil yang TIDAK lagi menyertakan data login; restore jauh lebih tahan-banting (kebal karakter khusus & beda setelan server).',
                'BARU — Pulihkan dari akun lain: backup dari akun mana pun bisa dipulihkan ke akun Anda; ID & relasi (toko/produk/pesanan) dipetakan ulang otomatis (tanpa bentrok), kategori dicocokkan per-nama, dan akun sumber tak tersentuh. Kategori kini ikut dicadangkan.',
                'Semua kartu ringkasan (Dashboard, atas tabel Pesanan, dan Insight) BISA DIKLIK langsung ke data terkait — filternya otomatis terpilih. Filter baru "Hasil: Untung/Rugi" di halaman Pesanan.',
                'BARU — Salin massal: conteng beberapa baris lalu Tindakan → "Salin No. Pesanan" / "Salin SKU Produk" (di Pesanan) atau "Salin SKU" (di Produk). Hasilnya satu nilai per baris, siap tempel (Ctrl+V) di Excel.',
                'Perbaikan: "Salin SKU Produk" di halaman Pesanan kini benar-benar menyalin SKU (sebelumnya keliru menganggap "tidak ada SKU" walau pesanannya jelas ber-SKU).',
                'Ukuran filter sedikit diperbesar agar lebih mudah ditekan.',
            ],
        ],
        [
            'date' => '24 Juni 2026',
            'changes' => [
                'Bisa membaca file Laporan Penghasilan berbahasa INGGRIS (TikTok/Tokopedia) — sebelumnya hanya format Indonesia.',
                'Kartu baru "Laba Semu (HPP kosong)": menandai pesanan yang labanya terlihat besar padahal modal/HPP belum diisi, agar Total Laba tidak menyesatkan.',
                'Insight: analisa SEBAB pesanan rugi (jual di bawah modal / biaya admin tinggi / voucher-ongkir besar / margin tipis) lengkap dengan saran tindakan dalam waktu dekat.',
                'Area unggah file impor diperbesar agar lebih mudah menyeret berkas.',
            ],
        ],
        [
            'date' => '23 Juni 2026',
            'changes' => [
                'Perbaikan: import yang sebelumnya gagal kini berfungsi; pilihan toko saat import menampilkan channel-nya (Shopee / Tokopedia/TikTok) agar tidak tertukar.',
                'Tokopedia & TikTok benar-benar jadi satu channel di input toko, pesanan, tabel, filter, dan grafik (sesuai satu seller center).',
                'Halaman Pesanan: kartu total (Jumlah Pesanan, Omzet, Laba) di atas tabel yang mengikuti filter; filter ringkas (collapsible) & berlaku seketika; detail produk tampil di tiap pesanan; total mengikuti periode yang dipilih.',
                'Estimasi biaya admin per kategori produk (termasuk biaya proses Rp1.250); 26 kategori resmi untuk semua akun; sistem memilih kategori produk otomatis.',
                'Estimasi biaya lebih akurat: kini mengikuti struktur biaya nyata marketplace — Shopee (Biaya Administrasi + Biaya Layanan + Proses) & Tokopedia/TikTok (Komisi + Komisi Dinamis + Proses). Biaya Layanan/Komisi Dinamis bisa diatur di Pengaturan. Pesanan batal otomatis berbiaya Rp0; pesanan berjalan yang produknya belum dikenal tetap diestimasi (tarif rata-rata).',
                'BARU — Kalibrasi Tarif dari Laporan Penghasilan: di menu Pengaturan, sistem bisa menghitung tarif biaya EFEKTIF dari pesanan Anda yang sudah ada laporannya, lalu memakainya untuk estimasi. Paling akurat karena pakai data toko Anda sendiri (terbukti reproduksi biaya asli dengan selisih <2%).',
                'Halaman Impor dirapikan untuk awam: 3 menu jelas (Laporan Marketplace / Daftar Produk / Dropship), contoh nama file, tombol Unduh Format File (Excel), dan notifikasi yang lebih jelas.',
                'Fitur Kosongkan Data (reset) dengan konfirmasi & aman per akun; tampilan dirapikan agar lebih menarik dan hemat tempat.',
            ],
        ],
        [
            'date' => '22 Juni 2026',
            'changes' => [
                'Rilis MarkazHub versi baru (lebih cepat & modern): multi-akun, login Google, import laporan, dashboard laba, perhitungan laba teraudit.',
                'Halaman Insight & Produk Merugi dan Log Aktivitas.',
                'Notifikasi (lonceng) dan tampilan sidebar yang lebih ringkas.',
                'Fitur Backup data & pemantauan error (Sentry).',
                'Audit menyeluruh: penguatan keamanan antar-akun, login Google lebih aman, seluruh tampilan dirapikan ke Bahasa Indonesia & nuansa Rupiah.',
            ],
        ],
    ];

    public function getViewData(): array
    {
        return ['changelog' => self::CHANGELOG];
    }
}
