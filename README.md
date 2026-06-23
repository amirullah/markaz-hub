<div align="center">

# 🛍️ MarkazHub

### Tahu laba aslimu, di setiap pesanan.

**Aplikasi manajemen penjualan & laba untuk seller marketplace Indonesia — Shopee, Tokopedia/TikTok, dan dropship — dalam satu tempat.**

[🚀 Coba Aplikasinya](https://markazhub.mkz.my.id) · [✨ Fitur](#-fitur) · [🧰 Teknologi](#-teknologi)

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-v5-FDAE4B?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Status](https://img.shields.io/badge/status-live-22c55e)

</div>

---

## ❓ Masalah yang dipecahkan

Jualan ramai, tapi **untungnya berapa, sih?** Laporan marketplace berserakan di banyak file; biaya admin, ongkir, voucher, dan modal sulit dijumlahkan manual. Akhirnya banyak seller cuma **menebak** laba — dan tidak sadar ada produk yang ternyata **dijual di bawah modal**.

**MarkazHub** menggabungkan laporan-laporan itu, menghitung **laba bersih yang akurat** per pesanan, dan menunjukkan dengan jelas mana yang untung dan mana yang merugi.

## ✨ Fitur

- 📥 **Impor laporan otomatis** — unggah file ekspor Shopee & Tokopedia/TikTok, sistem membaca & merapikan sendiri (deteksi channel, dropship, retur, cocokkan SKU).
- 💰 **Laba bersih akurat & teraudit** — omzet − (modal + biaya admin + ongkir + voucher + dropship + biaya lain).
- 📉 **Insight produk merugi** — temukan produk di bawah modal & pesanan rugi sebelum makin dalam.
- 🧮 **Estimasi biaya admin per kategori** — pesanan tanpa laporan penghasilan tetap punya perkiraan laba (tarif resmi per kategori, bisa diatur).
- 📊 **Dashboard informatif** — omzet, laba, margin, pesanan rugi, tren bulanan, dan omzet per channel.
- 🗂️ **Kelola produk, kategori, toko, supplier** dengan pemilihan kategori produk otomatis.
- 🔔 **Notifikasi**, 💾 **backup & restore**, 🗑️ **kosongkan data**, dan 📝 **log aktivitas**.
- 🔐 **Multi-tenant aman** — data tiap seller terpisah; login praktis dengan Google.
- 📱 **API-first** — siap untuk aplikasi mobile (Android/iOS) di masa depan.

## 🧰 Teknologi

| | |
|---|---|
| **Backend** | Laravel 13 (PHP 8.3) |
| **Panel admin** | Filament v5 |
| **Auth** | Login Google (Socialite) + Sanctum (API) |
| **Database** | MySQL |
| **Arsitektur** | Multi-tenant satu-database; logika bisnis di lapisan Service (dipakai bersama web & API) |

## 🚀 Coba sekarang

👉 **[markazhub.mkz.my.id](https://markazhub.mkz.my.id)** — masuk dengan akun Google, toko-mu langsung siap.

## 🛠️ Menjalankan secara lokal

```bash
git clone <repo-url> && cd markazhub-v2
composer install
cp .env.example .env && php artisan key:generate
# atur koneksi database di .env
php artisan migrate
php artisan serve
```

Buka `http://localhost:8000`.

---

<div align="center">
Dibuat dengan ❤️ untuk para seller Indonesia. 🇮🇩
</div>
