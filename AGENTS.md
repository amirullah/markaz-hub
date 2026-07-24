# AGENTS.md — Konteks Proyek untuk AI Agent

> File ini dibaca AI agent (OpenCode/OpenClaw) di AWAL setiap sesi baru,
> sebelum mengerjakan task apapun. Tujuannya: agar agent tidak "lupa"
> konteks proyek meskipun sesi percakapan sebelumnya sudah ditutup.

## Ringkasan Proyek
- **Nama proyek**: Hosting sendiri — MarkazHub (manajemen toko marketplace multi-tenant)
- **Domain/subdomain**: markazhub.mkzid.cloud
- **Stack**: PHP 8.4 (Laravel 13 / Filamen v5), MySQL, Nginx, deploy langsung ke VPS (tanpa Docker)
- **Metode deploy**: Upload via SFTP; pull via git; konfigurasi langsung di server

## Aturan Kerja untuk Agent
1. Sebelum mulai kerja, baca `PROGRESS.md` untuk tahu status terakhir.
2. Setelah menyelesaikan satu langkah/task, **update `PROGRESS.md`** —
   jangan tunggu sampai akhir sesi.
3. Jangan mengulang pekerjaan yang sudah tercatat selesai di
   `PROGRESS.md` kecuali diminta eksplisit.
4. Kalau menemukan keputusan penting (misalnya versi PHP yang dipakai,
   struktur folder, konfigurasi Nginx/Apache), catat di bagian
   "Keputusan Penting" pada `PROGRESS.md`.
5. Commit ke git dengan pesan yang jelas setiap ada perubahan berarti,
   supaya riwayat bisa dibaca ulang tanpa perlu context percakapan lama.

## Informasi Server (isi manual, jangan commit kalau ada kredensial)
- Host: `markazhub.mkzid.cloud` (IP terresolve otomatis)
- Port: 22
- User: `mkzs105`
- OS: Linux (custom VPS)
- Web server: Nginx
- PHP version: 8.4.23 (FPM pool: `mkz-mkzs105.sock`)
- Path deploy: `/var/www/vhosts/mkzs105/markazhub.mkzid.cloud/htdocs`
- SSH key: `~/.ssh/mkz_vps` (root access)
- DB name: `mkz105`
- DB user: `mkz105`
- Git remote: `https://github.com/amirullah/markaz-hub.git`

## Catatan Lain
- Tulis instruksi/preferensi tambahan di sini kalau muncul di tengah
  jalan (misalnya "selalu pakai queue driver X", "jangan restart
  service Y otomatis").
- Saat deploy ulang: jangan lupa `php artisan key:generate` jika APP_KEY baru
- Production gunakan `APP_ENV=production, APP_DEBUG=false`
- `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` jangan commit — hanya di .env server
- `ShippingService` otomatis kirim resi ke Shopee/TikTok saat "Tandai Dikirim + Resi"
  - Butuh `SHOPEE_PARTNER_ID`/`SHOPEE_PARTNER_KEY` dan/atau `TIKTOK_APP_KEY`/`TIKTOK_APP_SECRET` di `.env`
  - Gagal kirim ke marketplace tidak menggagalkan update internal — error dicatat di notifikasi
- Stock check dilakukan di "Tandai Diproses": bila stok kurang, proses dibatalkan dengan peringatan