# Prompt Templates — Deploy Web ke Hosting VPS

## 1. Prompt untuk MENGAKHIRI sesi (sebelum context penuh)
Pakai ini kalau usage context sudah sekitar 70-80%, sebelum pindah sesi baru:

```
Sebelum sesi ini berakhir, tolong lakukan ini:
1. Ringkas semua yang sudah dikerjakan di sesi ini (poin-poin singkat).
2. Update file PROGRESS.md: centang item yang sudah selesai di bagian
   "Sudah Selesai", tambahkan keputusan teknis penting ke bagian
   "Keputusan Penting", dan isi "Langkah Selanjutnya" dengan hal yang
   perlu dilanjutkan.
3. Kalau ada masalah yang belum kelar, catat di "Masalah yang Belum
   Selesai / Blocker" dengan detail error/kondisinya.
4. Commit semua perubahan file ke git dengan pesan yang jelas.
```

## 2. Prompt untuk MEMULAI sesi baru
Pakai ini di awal sesi baru, sebelum minta task apapun:

```
Baca file AGENTS.md dan PROGRESS.md di root project ini dulu sebelum
mulai kerja. Pastikan kamu paham:
- Tahap proyek saat ini ada di mana
- Apa yang sudah selesai
- Keputusan teknis apa saja yang sudah diambil
- Apa langkah selanjutnya yang perlu dikerjakan

Setelah itu, lanjutkan ke: [isi task spesifik, misal: "lanjutkan
konfigurasi SSL untuk hosting.markazvirtual.com"]
```

## 3. Prompt untuk task spesifik (biar tidak melebar)
Supaya AI tidak mengerjakan hal di luar scope dan boros context:

```
Task: [nama task spesifik, misal "setup Nginx virtual host untuk
hosting.markazvirtual.com"]

Batasan:
- Hanya kerjakan task ini, jangan ubah bagian lain di luar scope.
- Kalau butuh keputusan (misal ganti konfigurasi lain), tanya dulu
  sebelum eksekusi.
- Setelah selesai, ringkas perubahan yang dibuat (file apa saja yang
  diubah/dibuat).
```

## 4. Prompt untuk cek status tanpa baca ulang semua history
Kalau cuma mau tahu status tanpa AI baca ulang seluruh chat lama:

```
Baca PROGRESS.md saja (jangan baca ulang history chat), lalu
ringkas: sudah sampai mana, dan apa 3 langkah berikutnya yang
paling prioritas.
```
