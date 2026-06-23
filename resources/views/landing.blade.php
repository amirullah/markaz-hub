<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MarkazHub — Kelola Laba Marketplace dengan Mudah</title>
    <meta name="description" content="MarkazHub membantu seller Shopee, Tokopedia/TikTok, dan dropship menghitung laba akurat, menemukan produk merugi, dan mengelola pesanan dalam satu aplikasi.">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#0f172a;background:#fff;line-height:1.6}
        a{text-decoration:none}
        .wrap{max-width:1080px;margin:0 auto;padding:0 1.25rem}
        .btn{display:inline-flex;align-items:center;gap:.5rem;background:#2563eb;color:#fff;font-weight:700;padding:.8rem 1.5rem;border-radius:.7rem;font-size:1rem;transition:.15s;box-shadow:0 6px 18px rgba(37,99,235,.3)}
        .btn:hover{background:#1d4ed8;transform:translateY(-1px)}
        .btn-ghost{background:rgba(255,255,255,.15);box-shadow:none;border:1px solid rgba(255,255,255,.4)}
        .btn-ghost:hover{background:rgba(255,255,255,.25)}
        /* Topbar */
        .nav{position:absolute;top:0;left:0;right:0;z-index:10}
        .nav .wrap{display:flex;align-items:center;justify-content:space-between;padding-top:1.25rem;padding-bottom:1.25rem}
        .brand{display:flex;align-items:center;gap:.55rem;color:#fff;font-weight:800;font-size:1.25rem}
        .brand .logo{width:2rem;height:2rem;background:#fff;color:#2563eb;border-radius:.55rem;display:flex;align-items:center;justify-content:center;font-weight:900}
        /* Hero */
        .hero{background:linear-gradient(160deg,#1e3a8a 0%,#2563eb 55%,#3b82f6 100%);color:#fff;padding:8rem 0 5rem;text-align:center;position:relative;overflow:hidden}
        .hero h1{font-size:2.8rem;font-weight:900;line-height:1.15;letter-spacing:-.02em;max-width:780px;margin:0 auto 1.1rem}
        .hero p{font-size:1.18rem;color:#dbeafe;max-width:620px;margin:0 auto 2rem}
        .hero .cta{display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap}
        .pill{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#eff6ff;padding:.35rem .9rem;border-radius:999px;font-size:.82rem;font-weight:600;margin-bottom:1.5rem}
        /* Stats strip */
        .stats{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:3rem}
        .stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:.9rem;padding:1rem 1.5rem;min-width:160px}
        .stat b{display:block;font-size:1.7rem;font-weight:900}
        .stat span{font-size:.85rem;color:#dbeafe}
        /* Sections */
        section{padding:4.5rem 0}
        .eyebrow{color:#2563eb;font-weight:700;font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;text-align:center}
        h2{font-size:2rem;font-weight:800;text-align:center;margin:.4rem 0 .6rem;letter-spacing:-.01em}
        .sub{text-align:center;color:#64748b;max-width:560px;margin:0 auto 2.5rem}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.1rem}
        .card{border:1px solid #e8edf3;border-radius:1rem;padding:1.4rem;background:#fff;transition:.15s}
        .card:hover{border-color:#bfdbfe;box-shadow:0 10px 30px rgba(2,6,23,.06);transform:translateY(-2px)}
        .card .ic{width:2.6rem;height:2.6rem;border-radius:.7rem;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:.8rem}
        .card h3{font-size:1.05rem;font-weight:700;margin-bottom:.3rem}
        .card p{color:#64748b;font-size:.92rem}
        /* Steps */
        .steps{background:#f8fafc}
        .step-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;counter-reset:step}
        .step{text-align:center}
        .step .num{counter-increment:step;width:2.6rem;height:2.6rem;border-radius:50%;background:#2563eb;color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto .8rem}
        .step .num::before{content:counter(step)}
        .step h3{font-size:1.05rem;margin-bottom:.3rem}
        .step p{color:#64748b;font-size:.92rem}
        /* CTA */
        .cta-band{background:linear-gradient(120deg,#1d4ed8,#2563eb);color:#fff;border-radius:1.4rem;padding:3rem 2rem;text-align:center;margin:0 auto}
        .cta-band h2{color:#fff}
        .cta-band p{color:#dbeafe;margin-bottom:1.6rem}
        /* Footer */
        footer{padding:2.5rem 0;text-align:center;color:#94a3b8;font-size:.85rem;border-top:1px solid #eef2f7}
        @media(max-width:760px){.hero h1{font-size:2rem}.grid,.step-grid{grid-template-columns:1fr}.hero{padding:7rem 0 4rem}}
    </style>
</head>
<body>
    <nav class="nav">
        <div class="wrap">
            <div class="brand"><span class="logo">M</span> MarkazHub</div>
            <a href="/admin/login" class="btn btn-ghost">Masuk</a>
        </div>
    </nav>

    <header class="hero">
        <div class="wrap">
            <span class="pill">📊 Untuk seller Shopee · Tokopedia/TikTok · Dropship</span>
            <h1>Tahu laba aslimu, di setiap pesanan.</h1>
            <p>MarkazHub menggabungkan laporan marketplace-mu, menghitung laba bersih yang akurat, dan menunjukkan produk mana yang sebenarnya merugi — semua dalam satu aplikasi.</p>
            <div class="cta">
                <a href="/admin/login" class="btn">Mulai Gratis dengan Google →</a>
                <a href="#fitur" class="btn btn-ghost">Lihat Fitur</a>
            </div>
            <div class="stats">
                <div class="stat"><b>1 Menit</b><span>impor laporan, laba langsung muncul</span></div>
                <div class="stat"><b>Akurat</b><span>laba teraudit, bukan kira-kira</span></div>
                <div class="stat"><b>Multi-channel</b><span>Shopee + Tokopedia/TikTok</span></div>
            </div>
        </div>
    </header>

    <section id="fitur">
        <div class="wrap">
            <p class="eyebrow">Fitur</p>
            <h2>Semua yang dibutuhkan untuk untung lebih jelas</h2>
            <p class="sub">Berhenti menebak. MarkazHub mengubah tumpukan laporan jadi keputusan yang menguntungkan.</p>
            <div class="grid">
                <div class="card"><div class="ic">📥</div><h3>Impor laporan otomatis</h3><p>Unggah file ekspor Shopee & Tokopedia/TikTok — sistem membaca, mencocokkan, dan merapikan sendiri.</p></div>
                <div class="card"><div class="ic">💰</div><h3>Laba bersih akurat</h3><p>Perhitungan laba teraudit: omzet dikurangi modal, biaya admin, ongkir, voucher, dan biaya lain.</p></div>
                <div class="card"><div class="ic">📉</div><h3>Deteksi produk merugi</h3><p>Lihat produk yang dijual di bawah modal dan pesanan yang rugi — sebelum makin dalam.</p></div>
                <div class="card"><div class="ic">🧮</div><h3>Estimasi biaya admin</h3><p>Belum ada laporan penghasilan? Sistem mengestimasi biaya admin per kategori secara otomatis.</p></div>
                <div class="card"><div class="ic">📈</div><h3>Dashboard & insight</h3><p>Omzet, laba, margin, tren bulanan, dan channel — ringkas dalam grafik yang mudah dipahami.</p></div>
                <div class="card"><div class="ic">🔒</div><h3>Aman & milikmu sendiri</h3><p>Data tiap seller terpisah & aman. Lengkap dengan backup, pemulihan, dan log aktivitas.</p></div>
            </div>
        </div>
    </section>

    <section class="steps">
        <div class="wrap">
            <p class="eyebrow">Cara kerja</p>
            <h2>Tiga langkah, langsung paham labamu</h2>
            <div class="step-grid" style="margin-top:2.5rem">
                <div class="step"><div class="num"></div><h3>Masuk dengan Google</h3><p>Tanpa ribet daftar. Satu klik, toko-mu langsung siap.</p></div>
                <div class="step"><div class="num"></div><h3>Impor laporan</h3><p>Unggah file ekspor marketplace-mu — pesanan & biaya otomatis terisi.</p></div>
                <div class="step"><div class="num"></div><h3>Ambil keputusan</h3><p>Lihat laba, produk merugi, dan insight untuk menaikkan untung.</p></div>
            </div>
        </div>
    </section>

    <section>
        <div class="wrap">
            <div class="cta-band">
                <h2>Siap tahu laba aslimu?</h2>
                <p>Gratis untuk memulai. Cukup masuk dengan akun Google-mu.</p>
                <a href="/admin/login" class="btn" style="background:#fff;color:#1d4ed8">Mulai Sekarang →</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap">© {{ date('Y') }} MarkazHub — Kelola penjualan & laba marketplace. Dibuat untuk para seller Indonesia. 🇮🇩</div>
    </footer>
</body>
</html>
