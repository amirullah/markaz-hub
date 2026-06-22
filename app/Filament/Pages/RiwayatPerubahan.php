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
     * Catatan rilis — apa yang berubah di tiap versi (terbaru di atas).
     * Tambahkan entri baru setiap ada perubahan.
     */
    public const CHANGELOG = [
        [
            'version' => '1.9',
            'date' => '23 Jun 2026',
            'title' => 'Tampilan Pesanan lebih praktis',
            'changes' => [
                'Total pesanan kini tampil sebagai kartu menarik di ATAS tabel (Jumlah Pesanan, Total Omzet, Total Laba) dan mengikuti filter aktif.',
                'Filter ditampilkan langsung di atas tabel & berlaku seketika (tanpa tombol terapkan) — lebih praktis, tetap bisa filter detail.',
            ],
        ],
        [
            'version' => '1.8',
            'date' => '23 Jun 2026',
            'title' => 'Detail produk & total pesanan',
            'changes' => [
                'Detail produk (item) kini tampil di setiap pesanan: SKU, nama, qty, harga, subtotal, HPP.',
                'Tabel Pesanan menampilkan ringkasan total (jumlah pesanan, total omzet, total laba) yang mengikuti filter — mis. filter 1 minggu menampilkan total minggu itu.',
            ],
        ],
        [
            'version' => '1.7',
            'date' => '23 Jun 2026',
            'title' => 'Kategori resmi lengkap & biaya tambahan',
            'changes' => [
                'Kategori diperbanyak jadi 26 kategori dengan nama & tarif mengikuti dokumentasi resmi marketplace (Shopee & Tokopedia/TikTok).',
                'Semua organisasi/akun otomatis mendapat kategori default (sebelumnya hanya akun utama).',
                'Estimasi biaya admin kini menambahkan biaya proses pesanan Rp1.250 (resmi).',
                'Halaman Riwayat Perubahan ini.',
            ],
        ],
        [
            'version' => '1.6',
            'date' => '23 Jun 2026',
            'title' => 'Estimasi biaya admin per kategori',
            'changes' => [
                'Menu Kategori: tarif % biaya admin Shopee & Tokopedia/TikTok per kategori.',
                'Sistem otomatis memilih kategori produk dari nama (bisa diubah).',
                'Tombol "Isi Estimasi Biaya Admin": mengisi biaya admin pesanan yang belum final agar laba lebih akurat.',
            ],
        ],
        [
            'version' => '1.5',
            'date' => '23 Jun 2026',
            'title' => 'Filter mudah, gabung channel, restore',
            'changes' => [
                'Filter cepat per Periode (Minggu/Bulan/Tahun ini, 30 hari, dll).',
                'Tokopedia & TikTok digabung jadi satu channel di filter.',
                'Fitur Pulihkan (Restore) dari file backup.',
            ],
        ],
        [
            'version' => '1.4',
            'date' => '22 Jun 2026',
            'title' => 'Audit menyeluruh: keamanan & konsistensi',
            'changes' => [
                'Penguatan isolasi data antar-akun & login Google lebih aman.',
                'Semua form dirapikan ke Bahasa Indonesia + nuansa Rupiah.',
                'Format angka & istilah dikonsistenkan.',
            ],
        ],
        [
            'version' => '1.3',
            'date' => '22 Jun 2026',
            'title' => 'Backup & pemantauan error',
            'changes' => [
                'Fitur Backup data (.sql).',
                'Pemantauan error (Sentry) — aktif bila DSN diisi.',
            ],
        ],
        [
            'version' => '1.2',
            'date' => '22 Jun 2026',
            'title' => 'Notifikasi & tampilan',
            'changes' => [
                'Notifikasi (lonceng) — mis. setelah import.',
                'Sidebar diperkecil agar konten lebih lebar.',
            ],
        ],
        [
            'version' => '1.1',
            'date' => '22 Jun 2026',
            'title' => 'Insight & Aktivitas',
            'changes' => [
                'Halaman Insight & Produk Merugi.',
                'Log Aktivitas (audit perubahan).',
            ],
        ],
        [
            'version' => '1.0',
            'date' => '22 Jun 2026',
            'title' => 'Rilis awal MarkazHub v2',
            'changes' => [
                'Aplikasi baru (Laravel + Filament): multi-akun, login Google, import laporan, dashboard laba, perhitungan laba teraudit.',
            ],
        ],
    ];

    public function getViewData(): array
    {
        return ['changelog' => self::CHANGELOG];
    }
}
