# PROGRESS.md — Log Progress Proyek Hosting VPS

> Update file ini setiap kali menyelesaikan langkah penting.
> Format: tanggal, apa yang dikerjakan, status, catatan.

## Status Saat Ini
**Tahap**: ✅ Order processing pipeline lengkap — tabs, stock check, auto-fail/retry, manual fail/reason, shipping API, cetak label, pipeline dashboard.
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

## Blocker (butuh aksi user)
- **Credentials marketplace** belum diisi di `.env` server:
  - `SHOPEE_PARTNER_ID`, `SHOPEE_PARTNER_KEY` — daftar di https://open.shopee.com
  - `TIKTOK_APP_KEY`, `TIKTOK_APP_SECRET`, `TIKTOK_SERVICE_ID` — daftar di https://partner.tiktokshop.com
- **Deploy kode** ke server (pull git atau upload SFTP) + `php deploy.php`
- **Testing API** — shipping & cetak label perlu koneksi aktif

## Langkah Selanjutnya (Deploy ke Server)
1. **Pull kode terbaru** di server (git pull) atau upload via SFTP
2. **Jalankan deploy**:
   ```
   php deploy.php
   ```
   (otomatis: migrate --force, config:cache, route:cache, view:cache, storage:link)
3. **Isi credentials marketplace** di `.env` server:
   - `SHOPEE_PARTNER_ID`, `SHOPEE_PARTNER_KEY`
   - `TIKTOK_APP_KEY`, `TIKTOK_APP_SECRET`, `TIKTOK_SERVICE_ID`
4. **Jalankan ulang** `php deploy.php cache` (setelah isi .env)
5. **Test pipeline**: Baru → Diproses → Dikemas → Dikirim + Resi → label
6. **Hapus/lindungi** `deploy.php` dari server jika tidak diperlukan lagi

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

### Sesi 4 — 24 Juli 2026
- Dikerjakan: Order processing pipeline — processing statuses, stock management, dummy data
- Migrations: `processing_status`/`tracking_number`/`courier`/`shipped_at` di orders, `stock`/`min_stock` di products, `stock_movements` table
- Order model: `processingStatusLabel()`, `processingStatusColor()`, scopes `perluDiproses`/`sudahDikirim`
- Product model: `stock`/`min_stock` fields + scopes `stockMenipis`/`stockHabis` + `stockMovements()` relation
- `StockMovement` model (BelongsToOrganization + product relation)
- OrdersTable: processing_status badge + filter "Proses" + bulk actions (Tandai Diproses/Dikemas/Dikirim + Resi + auto-deduct stock + Cetak Packing Slip)
- ProductsTable: stock/min_stock columns (color-coded) + filter "Stok"
- ProductForm: stock/min_stock fields
- `StockMovementsRelationManager` on ProductResource: history + manual adjustment (IN/OUT/ADJUSTMENT)
- Packing slip print views (single + batch) + routes
- `SeedDummyData` Artisan command — seeds 24 products, 80 orders, 23 stock movements
- Dummy data seeded for `markazvirtual@gmail.com` via deploy.php
- Semua perubahan deployed & pushed

### Sesi 5 — 24 Juli 2026
- Dikerjakan: Marketplace shipping API integration + stock check on process
- `ShopeeClient::shipOrder()` — call Shopee `/api/v2/logistics/ship_order` to send tracking number
- `TokpedTikTokClient::shipOrder()` — call TikTok `/fulfillment/202309/ship` to send tracking + carrier code
- `ShippingService` — auto-ship to marketplace when order marked Shipped (maps courier to TikTok carrier codes)
- OrdersTable "Tandai Dikirim + Resi" now calls marketplace API after internal status update + stock deduction
- OrdersTable "Tandai Diproses" now checks product stock sufficiency before proceeding; warns if insufficient
- Hasil: Siap deploy. Shipping API requires `.env` credentials to function.

### Sesi 6 — 24 Juli 2026
- Dikerjakan: Pipeline tabs, shipping labels, auto-generate resi
- **Pipeline Tabs**: `ListOrders::getTabs()` — Semua/Baru/Diproses/Dikemas/Dikirim/Gagal dengan badge count
- **FAILED status**: ditambahkan ke `Order::processingStatusLabel()` dan `processingStatusColor()`, filter "Proses" di OrdersTable
- **Label Pengiriman**: `ShopeeClient::getShippingDocument()`, `TokpedTikTokClient::getShippingDocument()`, `ShippingService::getShippingLabel()`
- **Cetak Label** action: per baris (dropdown ⋮) dan bulk action — dapatkan URL label dari marketplace, tampilkan notifikasi dengan tombol buka
- **Auto-generate Resi**: `ShopeeClient::getTrackingNumber()` untuk ambil nomor resi dari Shopee setelah ship
- Hasil: Fitur lengkap BigSeller-style — pipeline tabs, stock check, auto-ship ke marketplace, cetak label pengiriman

### Sesi 8 — 24 Juli 2026
- Dikerjakan: Finalisasi + fitur lanjutan + deploy preparation
- **Tandai Gagal** bulk action: manual fail dengan modal input alasan
- **Stock status column** di tabel: icon check/warning per order + tooltip detail produk yang stoknya kurang
- **Input Resi Massal**: bulk action dengan textarea — format `NoPesanan|Resi|Kurir` per baris, auto-deduct stock + kirim ke marketplace API
- **Dashboard widget**: 3 kartu pipeline — Perlu Diproses, Sedang Diproses, Gagal Diproses
- **Cari Resi**: `tracking_number` ditambahkan ke searchable columns di tabel
- **Export CSV**: header action — export hasil filter ke CSV (BOM UTF-8, includes kolom laba, tracking, dll)
- **Invoice PDF**: print view per-order + batch (`/print/invoice/{order}` dan `/print/invoice/batch?ids=...`) dengan `window.print()`, record action (dropdown ⋮) dan bulk action
- **Bugfix deploy.php**: `section()` typo (2 arg jadi 1), `now()` ganti `date()` (bootstrap belum penuh)
- **Code polish**: pisah chained method per baris (rapikan `->label('X')->sortable()` dll)
- **deploy.php**: script artisan untuk server dengan `exec()` disabled — migrasi, cache, seed
- **Commit + Push** ke origin/main
- Siap deploy ke production. Langkah selanjutnya: `php deploy.php` di server

### Sesi 7 — 24 Juli 2026
- Dikerjakan: Pipeline completion — auto-fail, retry, failed_reason, dashboard
- **Migration**: `failed_reason` column di orders (string 255, nullable)
- **Order model**: `failed_reason` fillable + activity log tracking untuk `processing_status` dan `failed_reason`
- **Tandai Diproses**: skrg auto-fail (FAILED + reason) untuk pesanan dengan stok kurang — bukan sekedar warning
- **Retry Gagal** bulk action: pindahkan FAILED → PENDING (hapus failed_reason)
- **Tandai Gagal** bulk action: manual fail (modal input alasan)
- **Tandai Dikemas/Dikirim**: otomatis hapus failed_reason saat lanjut ke status berikutnya
- **Order detail view**: tambah field `processing_status` badge, `failed_reason` (visibel saat FAILED), section Pengiriman (resi/kurir/tgl kirim)
- **Table tooltip**: badge Proses tampilkan failed_reason sebagai tooltip saat FAILED
- **Stock status column** di order table: icon check/warning dengan tooltip detail produk yang stoknya kurang
- **Dashboard Insight**: tambah section "Pipeline Proses Pesanan" — kartu Baru/Diproses/Dikemas/Dikirim/Gagal dengan jumlah & %, bisa diklik ke tab terkait
- **SeedDummyData**: generate FAILED orders + failed_reason di data dummy