# AGENTS.md — Konteks Proyek untuk AI Agent

> File ini dibaca AI agent (OpenCode/OpenClaw) di AWAL setiap sesi baru,
> sebelum mengerjakan task apapun. Tujuannya: agar agent tidak "lupa"
> konteks proyek meskipun sesi percakapan sebelumnya sudah ditutup.

## Ringkasan Proyek
- **Nama proyek**: Layanan hosting sendiri di VPS
- **Domain/subdomain**: markazhub.mkzid.cloud
- **Stack**: PHP (Laravel), deploy langsung ke VPS (tanpa Docker)
- **Metode deploy**: otomatis via AI agent (OpenClaw)

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
- IP VPS: `_isi_`
- OS: `_isi_`
- Web server: `_isi_ (Nginx/Apache)`
- PHP version: `_isi_`
- Path deploy: `_isi_`

## Catatan Lain
- Tulis instruksi/preferensi tambahan di sini kalau muncul di tengah
  jalan (misalnya "selalu pakai queue driver X", "jangan restart
  service Y otomatis").
