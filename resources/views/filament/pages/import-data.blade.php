<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.75rem;padding:1rem;background:#fff';
        $mini = 'border:1px solid #eef2f7;border-radius:.6rem;padding:.75rem;background:#f8fafc';
        $dropship = \App\Models\Organization::currentUsesDropship();
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.5rem">
        {{-- ====== PANDUAN: 2 jenis impor ====== --}}
        <div style="{{ $card }}">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.6rem">📥 Ada {{ $dropship ? '3' : '2' }} cara impor — pilih sesuai file Anda</div>

            <div style="{{ $mini }};margin-bottom:.6rem;border-left:4px solid #2563eb">
                <div style="font-weight:700;color:#1e293b">1️⃣ Impor Pesanan &amp; Laporan <span style="color:#2563eb;font-weight:600">(tombol biru)</span></div>
                <div style="font-size:.82rem;color:#475569;margin-top:.3rem">Untuk file yang Anda <strong>download dari Shopee / Tokopedia / TikTok</strong>:</div>
                <ul style="margin:.4rem 0 0 1.1rem;padding:0;font-size:.8rem;color:#64748b;line-height:1.65">
                    <li><strong>Laporan Penghasilan</strong> — biaya admin &amp; laba final yang akurat.</li>
                    <li><strong>File / Laporan Pesanan</strong> — daftar pesanan, produk, jumlah, status.</li>
                    @if ($dropship)
                        <li><strong>Laporan Dropship</strong> — biaya dropship per pesanan dari supplier.</li>
                    @endif
                </ul>
                <div style="font-size:.78rem;color:#94a3b8;margin-top:.4rem">Caranya: klik tombol → pilih toko → unggah file (boleh beberapa) → Impor.</div>
            </div>

            <div style="{{ $mini }};border-left:4px solid #64748b">
                <div style="font-weight:700;color:#1e293b">2️⃣ Impor Daftar Produk <span style="color:#64748b;font-weight:600">(tombol abu-abu)</span></div>
                <div style="font-size:.82rem;color:#475569;margin-top:.3rem">Untuk <strong>daftar produk Anda sendiri / dari supplier</strong> (BUKAN file marketplace):</div>
                <ul style="margin:.4rem 0 0 1.1rem;padding:0;font-size:.8rem;color:#64748b;line-height:1.65">
                    <li>File Excel/CSV berisi <strong>Kode Produk (SKU)</strong> &amp; <strong>Harga Modal</strong> (opsional: Nama Produk, Tanggal).</li>
                    <li>Pilih asal produknya — mis. <em>"Stok Sendiri"</em> atau <em>"Supplier A"</em>.</li>
                </ul>
                <div style="font-size:.78rem;color:#94a3b8;margin-top:.4rem">Caranya: klik tombol → unggah file → isi supplier → Impor.</div>
            </div>

            @if ($dropship)
                <div style="{{ $mini }};margin-top:.6rem;border-left:4px solid #f59e0b">
                    <div style="font-weight:700;color:#1e293b">3️⃣ Impor Biaya Dropship <span style="color:#b45309;font-weight:600">(dropship manual)</span></div>
                    <div style="font-size:.82rem;color:#475569;margin-top:.3rem">Untuk dropship <strong>tanpa laporan otomatis</strong> (dari supplier / seller lain yang Anda catat sendiri):</div>
                    <ul style="margin:.4rem 0 0 1.1rem;padding:0;font-size:.8rem;color:#64748b;line-height:1.65">
                        <li>File Excel/CSV berisi <strong>No. Pesanan</strong> (dari marketplace) &amp; <strong>Biaya Dropship</strong> (opsional: Modal Produk).</li>
                        <li>Pesanan yang cocok otomatis ditandai <strong>Dropship</strong> &amp; biayanya terisi.</li>
                    </ul>
                    <div style="font-size:.78rem;color:#94a3b8;margin-top:.4rem">Caranya: klik tombol → unggah file → Impor.</div>
                </div>
            @endif

            <p style="margin:.8rem 0 0;font-size:.8rem;color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;padding:.55rem .7rem">
                ✅ <strong>Aman mengunggah file yang sama berulang kali.</strong> Sistem memperbarui data lama berdasarkan nomor pesanan / kode produk — <strong>tidak akan membuat data dobel</strong>. Tidak perlu khawatir.
            </p>
        </div>

        {{-- ====== SESUDAH IMPOR: hasil (Impor Pesanan & Laporan) ====== --}}
        @if ($report)
            @php
                $ok = collect($report)->where('ok', true)->count();
                $fail = collect($report)->where('ok', false)->count();
                $ringkas = array_filter([$summary['orders'] ?? null, $summary['dropship'] ?? null]);
            @endphp

            <div style="border-radius:.75rem;padding:1rem;border:1px solid {{ $fail ? '#fcd34d' : '#86efac' }};background:{{ $fail ? '#fffbeb' : '#f0fdf4' }}">
                <div style="font-weight:800;font-size:1rem;margin-bottom:.3rem">
                    {{ $fail ? '⚠️' : '✅' }} Selesai — {{ $ok }} file diproses{{ $fail ? ", {$fail} dilewati" : '' }}
                </div>
                @forelse ($ringkas as $line)
                    <div style="font-size:.85rem;color:#334155">• {{ $line }}</div>
                @empty
                    <div style="font-size:.85rem;color:#334155">Tidak ada data baru diproses. Cek detail di bawah.</div>
                @endforelse
            </div>

            <div style="{{ $card }}">
                <div style="font-weight:700;margin-bottom:.6rem">📋 Detail per file</div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <tbody>
                        @foreach ($report as $r)
                            <tr style="border-top:1px solid #f1f5f9">
                                <td style="padding:.5rem .4rem;width:1.5rem;vertical-align:top">{{ $r['ok'] ? '✅' : '⏭️' }}</td>
                                <td style="padding:.5rem .6rem .5rem 0;font-family:monospace;font-size:.72rem;word-break:break-all;max-width:18rem;vertical-align:top">{{ $r['name'] }}</td>
                                <td style="padding:.5rem 0;font-size:.85rem;color:#475569">
                                    @if ($r['ok'])
                                        <span style="font-weight:600;color:#1e293b">{{ $r['type'] }}</span> — {{ $r['detail'] ?? '' }}
                                    @else
                                        <span style="color:#b45309">Dilewati: {{ $r['reason'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if (!empty($summary['hpp_changes']))
                <div style="{{ $card }}">
                    <div style="font-weight:700;margin-bottom:.6rem">💲 Perubahan harga modal: {{ count($summary['hpp_changes']) }} produk</div>
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse">
                            <thead><tr style="text-align:left;color:#64748b;font-size:.72rem">
                                <th style="padding:.3rem .6rem .3rem 0">SKU</th><th style="padding:.3rem .6rem .3rem 0">Produk</th>
                                <th style="padding:.3rem .6rem .3rem 0;text-align:right">Lama</th><th style="padding:.3rem 0;text-align:right">Baru</th>
                            </tr></thead>
                            <tbody>
                            @foreach (array_slice($summary['hpp_changes'], 0, 50) as $c)
                                <tr style="border-top:1px solid #f1f5f9;font-size:.82rem">
                                    <td style="padding:.35rem .6rem .35rem 0;font-family:monospace;font-size:.72rem">{{ $c['sku'] }}</td>
                                    <td style="padding:.35rem .6rem .35rem 0">{{ \Illuminate\Support\Str::limit($c['name'], 40) }}</td>
                                    <td style="padding:.35rem .6rem .35rem 0;text-align:right">Rp {{ number_format($c['old'], 0, ',', '.') }}</td>
                                    <td style="padding:.35rem 0;text-align:right;font-weight:600">Rp {{ number_format($c['new'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
