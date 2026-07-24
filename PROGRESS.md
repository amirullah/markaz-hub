# PROGRESS.md — Log Progress Proyek Hosting VPS

> Update file ini setiap kali menyelesaikan langkah penting.
> Format: tanggal, apa yang dikerjakan, status, catatan.

## Status Saat Ini
**Tahap**: ✅ Integrasi API Tokopedia/TikTok selesai. Menunggu deploy ke server.
**Terakhir dikerjakan**: 24 Juli 2026

## Sudah Selesai
- [x] Deploy aplikasi Laravel ke VPS (markazhub.mkzid.cloud)
- [x] Setup database, env, cache, storage link
- [x] Code review — temuan Critical, High, Medium, Low
- [x] Deep study aplikasi (controllers, models, services, migrations, dll)
- [x] C1: Hapus `.env.production` dari repo
- [x] C2: Generate APP_KEY baru di server (`key:generate --force`)
- [x] C5: Handle `InvalidStateException` Google login dengan pesan jelas
- [x] H2: Try-catch refresh token Shopee — set status ERROR bila gagal
- [x] H3: Covered oleh C5 (InvalidStateException)
- [x] H4/H5: Migration FK constraints `dropship_costs.organization_id` & `product_price_changes.organization_id`
- [x] Deploy semua perubahan ke server via SFTP
- [x] `config:cache`, `route:cache`, `view:cache` — semua sukses
- [x] `git push` ke origin

## Keputusan Penting
- **Database**: MySQL di server VPS, SQLite di lokal
- **APP_KEY**: Lokal dan production harus berbeda — sudah regenerate di server via `deploy.php`
- **Google OAuth**: Pakai Socialite; login pertama bikin Organization (tenant) otomatis
- **Multi-tenant**: Via `BelongsToOrganization` trait + global scope `organization_id`
- **Shopee**: HMAC-SHA256; refresh token tiap ±4 jam; refresh_token berubah tiap refresh; sandbox di `partner.test-stable.shopeemobile.com`
- **TikTok Shop**: HMAC-SHA256; header `x-tts-access-token`; access_token 7 hari; gateway `open-api.tiktokglobalshop.com` (HTTPS only)
- **Profit**: `ProfitService` sebagai SSOT; order importer loop per batch dari DB
- **No Docker**: Deploy langsung ke VPS via SFTP/AI agent
- **Server**: Plesk, path `/var/www/vhosts/mkzs105/markazhub.mkzid.cloud/htdocs`
- **exec()/shell_exec()**: Disabled di server — gunakan Artisan kernel langsung di PHP scripts

## Masalah yang Belum Selesai / Blocker
- Perlu daftar di TikTok Shop Partner Center (partner.tiktokshop.com) untuk dapat `TIKTOK_APP_KEY`, `TIKTOK_APP_SECRET`, `TIKTOK_SERVICE_ID`
- Perlu update `.env` server dengan credentials TikTok Shop
- Token TikTok Shop `shop_cipher` kolom baru perlu migration dijalankan

## Langkah Selanjutnya
- Setup TikTok Shop Partner Center app → isi `.env` server
- Migration `add_tokpedtiktok_fields` di server
- Deploy kode ke server
- Testing API sync Shopee & TikTok

---
## Riwayat Sesi
### Sesi 1 — 24 Juli 2026
- Dikerjakan: Deploy awal Laravel ke VPS markazhub.mkzid.cloud
- Hasil: App running, migrations done, storage linked

### Sesi 2 — 24 Juli 2026
- Dikerjakan: Code review, deep study, perbaikan C1/C5/H2/H3/H4/H5
- Deploy via SFTP (Plesk — SSH shell disabled)
- Generate APP_KEY baru via deploy.php (Artisan kernel langsung, tanpa exec)
- Hasil: Semua fix deployed & running di production
- Catatan: `exec()` disabled di server — pakai require artisan/bootstrap langsung

### Sesi 3 — 24 Juli 2026
- Dikerjakan: Integrasi API Tokopedia/TikTok (TikTok Shop Open Platform)
- File baru:
  - `TokpedTikTokClient` — HTTP client (HMAC-SHA256, x-tts-access-token, 7d expiry)
  - `TokpedTikTokSync` — sync orders/settlement/catalog → OrderImporter pipeline
  - `TokpedTikTokAuthController` — OAuth connect/callback
  - `TokpedTikTokWebhookController` — push handler (HMAC verified)
  - Console command `tokpedtiktok:sync` + schedule tiap 4 jam / katalog 03:20
  - Migration: `shop_cipher` + `refresh_token_expires_at` di marketplace_connections
  - Routes: web (connect/callback) + api (push)
  - Filament: tombol Hubungkan/Sinkron/Katalog TikTok di StoresTable
- Hasil: Siap di-deploy dan diisi credentials TikTok Shop Partner Center