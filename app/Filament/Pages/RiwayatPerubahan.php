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
            'date' => '26 Juni 2026',
            'changes' => [
                'Salin No. Pesanan (1 tombol, di menu Tindakan): centang baris → "Salin No. Pesanan". Bisa pilih BANYAK sekaligus — tampilkan 100/250 per halaman, atau centang kotak di header tabel lalu klik "Pilih semua" untuk SELURUH hasil filter (lintas halaman, tak terbatas 50). Yang dipilih disalin ke clipboard; kalau sangat banyak (>2000) otomatis diunduh sebagai file .txt (clipboard tak andal untuk teks raksasa). Berlaku juga untuk "Salin SKU Produk".',
                'Perbaikan impor: baris HEADER/LEGENDA dari file marketplace (mis. "Platform unique order ID.") tak lagi keimpor jadi "pesanan" — importer kini melewati baris yang nomor pesanannya kosong atau mengandung spasi (nomor pesanan asli tak pernah berspasi). Pesanan sampah yang sudah terlanjur masuk juga dibersihkan.',
                'Link "Lihat pesanan" di panel "File yang perlu diupload" kini menampilkan pesanan PERSIS sesuai jumlah di tiap baris (sebelumnya bisa beda karena pakai filter perkiraan). Caranya: filter tersembunyi "saran" yang meniru query tepat tiap kategori (income belum diverifikasi / tanpa rincian item / HPP kosong tapi produk ada / kurang data dropship). Butir "Daftar Produk (HPP)" pun kini punya link.',
                'Laba pesanan yang "belum final" (HPP/modal belum ada, biaya masih estimasi, atau settlement belum cair) kini tampil sebagai ESTIMASI — angka abu-abu + awalan "≈", dan di detail diberi label "belum final" — supaya tidak terkesan untung pasti. Pesanan yang sudah final tetap hijau (untung) / merah (rugi) seperti biasa. Berlaku di tabel Pesanan & halaman detail.',
            ],
        ],
        [
            'date' => '25 Juni 2026',
            'changes' => [
                'Panel "File yang perlu segera diupload": tiap baris kini ada link "Lihat pesanan →" yang membuka daftar Pesanan TERFILTER (per toko + status terkait) — jadi bisa langsung cek pesanan yang dimaksud tiap butir. (Butir "Daftar Produk/HPP" tak diberi link karena subsetnya — produk sudah tercatat tapi modal kosong — tak punya padanan filter tabel yang persis.)',
                'Panel "File yang perlu segera diupload": butir Laporan Penghasilan & File Pesanan kini DIPECAH PER TOKO (cantumkan nama toko) agar jelas harus unduh dari seller center toko mana — sebelumnya hanya per-channel. Juga koreksi penting: butir "Daftar Produk (HPP)" kini hanya menghitung pesanan yang SUDAH punya item/SKU (modalnya memang bisa diisi via Daftar Produk); pesanan yang belum ada rincian produknya diarahkan ke butir "File Pesanan" (Daftar Produk tak bisa mengisi HPP tanpa SKU). Angka HPP jadi lebih akurat (mis. 954 → 49).',
                'Laporan (matriks per toko): kini bisa DIURUTKAN — klik judul kolom (Toko, bulan mana pun, atau Total) untuk mengurutkan baris; klik lagi untuk membalik arah (panah ▲/▼). Urutan mengikuti metrik aktif (Laba/Omzet/Pesanan).',
                'Laporan: tabel baru "Perbandingan Bulanan per Toko" — tiap toko (baris) × 12 bulan (kolom) tampil SEKALIGUS agar mudah dibandingkan antar-bulan & antar-toko tanpa ganti-ganti bulan. Ada pemilih metrik (Laba / Omzet / Pesanan), kolom Total per toko, dan baris Total semua toko. Klik sel untuk membuka pesanan toko+bulan itu; angka ditampilkan PENUH (angka asli, tanpa singkatan). Totalnya konsisten dengan Laporan Bulanan.',
                'Perbaikan PERFORMA penting: impor Master Produk yang belakangan bisa "loading" PULUHAN DETIK kini kembali cepat (hitungan detik). Penyebabnya: proses isi/hitung-ulang HPP (modal) memilih rencana query yang sangat lambat; kini urutan join dipaksa benar sehingga ±1.000× lebih cepat. Tidak ada perubahan pada angka — hanya kecepatan.',
                'Perbaikan (audit): filter "Belum cair (settlement)" kini cocok PERSIS dengan label kolom Status Laba — pesanan ber-label "Perlu data" (HPP belum ada) tak lagi keliru ikut muncul. Juga: pemilihan HPP per-tanggal kini konsisten antara saat impor & hitung-ulang untuk pesanan yang tanggalnya sama dengan tanggal perubahan harga.',
                'Impor Daftar Produk: perlakuan HPP pesanan LAMA kini PILIH SALAH SATU (radio, tak bisa kepilih dua yang bertentangan) — (a) Jangan ubah (default, hanya isi yang kosong); (b) "Sesuaikan sesuai TANGGAL pesanan" (tiap pesanan lama pakai modal yang berlaku saat tanggalnya; tepat untuk perubahan harga MUNDUR/backdated, mis. upload tgl 10 tapi harga berubah tgl 4 → pesanan tgl 5 ikut harga tgl 4); (c) "Samakan dengan harga TERBARU" (timpa semua ke modal terkini).',
                'HPP (modal) packing sendiri kini diambil sesuai TANGGAL pesanan: bila harga modal produk pernah berubah, pesanan lama memakai modal yang BERLAKU saat tanggal pesanan itu (dari riwayat harga), bukan modal terkini. Berlaku saat impor pesanan, pengisian HPP otomatis, dan "Perbarui harga modal pesanan lama". Laba per pesanan jadi lebih akurat secara historis.',
                'Insight "Produk di Bawah Modal" kini pakai HARGA JUAL & MODAL TERKINI (bukan modal lama yang beku): tiap produk diambil dari penjualan TERBARU-nya lalu dibandingkan dengan modal sekarang (modal katalog untuk packing sendiri, biaya dropship terbaru untuk dropship). Jadi produk yang harganya SUDAH dinaikkan atau modalnya SUDAH turun otomatis hilang dari daftar — hanya yang benar-benar masih rugi sekarang yang tampil.',
                'BARU — Penanda "Jual di Bawah Modal": kartu Dashboard + filter di Pesanan yang OTOMATIS menandai pesanan yang harga jualnya LEBIH KECIL dari modal (HPP untuk packing sendiri, biaya dropship untuk dropship). Untuk cepat menemukan produk yang dijual rugi — harga keliru, supplier mahal, atau biaya dropship salah input — tanpa mencari manual.',
                'Perbaikan laba (settlement belum cair): pesanan "Selesai" yang dananya BELUM cair dari marketplace (settlement masih 0 di laporan, mis. baru selesai & dana TikTok/Tokopedia ditahan dulu) kini berstatus "Belum cair" — TIDAK lagi keliru ditandai "Final" dan tidak tampil rugi palsu (laba pending Rp 0). Begitu dana cair & Anda impor ulang Laporan Penghasilan, labanya jadi final & benar.',
                'Perbaikan laba dropship: pesanan dropship yang masih "Dibayar" (belum selesai/dicairkan marketplace) TIDAK lagi tampil sebagai rugi palsu. Selama omzet belum keluar, labanya dianggap PENDING (Rp 0) — biaya dropship baru dihitung saat pesanan selesai & omzet masuk. Total Laba jadi lebih akurat (tak terseret pesanan yang belum tuntas).',
                'Perbaikan PENTING: setelah impor Laporan Marketplace, estimasi biaya admin & laba kini LANGSUNG terhitung otomatis — tak perlu lagi menekan tombol "Isi Estimasi Biaya Admin" manual. (Dulu pesanan baru biayanya 0 sampai tombol itu ditekan, sehingga laba terlihat "belum dihitung".)',
                'Laporan per Toko kini bisa dilihat PER BULAN: pilih bulan (atau "Setahun") di bagian Laporan per Toko untuk melihat omzet/laba tiap toko pada bulan itu.',
                'Filter Toko di halaman Pesanan dibuat lebih lebar (muat nama + channel) dan teks daftar tokonya sedikit diperkecil agar ringkas.',
                'Impor BEBAS URUTAN (ditegaskan): unggah Dropship atau Daftar Produk SEBELUM Laporan Marketplace pun aman — datanya tersimpan permanen & otomatis terpasang saat pesanannya menyusul (begitu juga sebaliknya). Notifikasi dropship diperjelas: dulu seolah data hilang bila pesanan belum ada, padahal biayanya tetap tersimpan.',
                'Perbaikan: angka "Tarif efektif rata-rata" di halaman Pengaturan kini SAMA PERSIS dengan yang muncul di notifikasi setelah kalibrasi (dulu sedikit berbeda karena halaman memakai rata-rata kategori sederhana, sedangkan notifikasi memakai tarif efektif tertimbang-omzet dari Laporan Penghasilan — kini keduanya pakai sumber yang sama).',
                'BARU — Laporan per Toko: di menu Laporan, lihat omzet, laba, dan margin SETIAP toko untuk tahun terpilih (channel ditampilkan); klik baris toko untuk membuka pesanannya.',
                'Filter Toko di halaman Pesanan kini menampilkan channel tiap toko (nama toko diikuti "— Shopee" atau "— Tokopedia/TikTok") agar tak tertukar saat memilih.',
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
