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
            'date' => '23 Juni 2026',
            'changes' => [
                'Perbaikan: import yang sebelumnya gagal kini berfungsi; pilihan toko saat import menampilkan channel-nya (Shopee / Tokopedia/TikTok) agar tidak tertukar.',
                'Tokopedia & TikTok benar-benar jadi satu channel di input toko, pesanan, tabel, filter, dan grafik (sesuai satu seller center).',
                'Halaman Pesanan: kartu total (Jumlah Pesanan, Omzet, Laba) di atas tabel yang mengikuti filter; filter ringkas (collapsible) & berlaku seketika; detail produk tampil di tiap pesanan; total mengikuti periode yang dipilih.',
                'Estimasi biaya admin per kategori produk (termasuk biaya proses Rp1.250); 26 kategori resmi untuk semua akun; sistem memilih kategori produk otomatis.',
                'Estimasi biaya lebih akurat: kini mengikuti struktur biaya nyata marketplace — Shopee (Biaya Administrasi + Biaya Layanan + Proses) & Tokopedia/TikTok (Komisi + Komisi Dinamis + Proses). Biaya Layanan/Komisi Dinamis bisa diatur di Pengaturan.',
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
