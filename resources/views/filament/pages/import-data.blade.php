<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.75rem;padding:1rem;background:#fff';
        $mini = 'border:1px solid #eef2f7;border-radius:.6rem;padding:.75rem;background:#f8fafc';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.5rem">
        {{-- ====== SEBELUM IMPORT: panduan ====== --}}
        <div style="{{ $card }}">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.6rem">📥 Cara import (3 langkah)</div>
            <ol style="margin:0 0 0 1.1rem;padding:0;color:#475569;font-size:.88rem;line-height:1.7">
                <li>Klik <strong>Unggah &amp; Import</strong> di kanan atas.</li>
                <li>Pilih <strong>toko tujuan</strong> sesuai channel file (nama toko + channel ditampilkan, mis. "MarkazMall SBY — Shopee").</li>
                <li>Unggah satu atau beberapa file ekspor sekaligus, lalu klik Import. Sistem mengenali jenis tiap file otomatis.</li>
            </ol>

            <div style="font-weight:700;color:#1e293b;margin:1.1rem 0 .5rem">Jenis file yang didukung</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.6rem">
                <div style="{{ $mini }}">
                    <div style="font-weight:600;color:#1e293b">🧾 Laporan Penghasilan</div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:.25rem">Memberi <strong>biaya admin &amp; laba final</strong> yang akurat. Tanpa ini, laba masih estimasi.</div>
                </div>
                <div style="{{ $mini }}">
                    <div style="font-weight:600;color:#1e293b">📦 File Pesanan</div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:.25rem">Daftar pesanan, produk, qty, &amp; status (selesai/dikirim/retur/batal).</div>
                </div>
                <div style="{{ $mini }}">
                    <div style="font-weight:600;color:#1e293b">🗂️ Master Produk / Katalog</div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:.25rem">Katalog produk, <strong>harga/HPP</strong> &amp; riwayat harga.</div>
                </div>
                @if (\App\Models\Organization::currentUsesDropship())
                    <div style="{{ $mini }}">
                        <div style="font-weight:600;color:#1e293b">📑 Laporan Dropship</div>
                        <div style="font-size:.8rem;color:#64748b;margin-top:.25rem">Biaya dropship per pesanan dari supplier.</div>
                    </div>
                @endif
            </div>
            <p style="margin:.8rem 0 0;font-size:.8rem;color:#64748b">💡 File yang channel-nya tidak cocok dengan toko otomatis dilewati (tidak menggagalkan file lain). Aman mengunggah ulang — data yang sama akan diperbarui, bukan dobel.</p>
        </div>

        {{-- ====== SESUDAH IMPORT: hasil ====== --}}
        @if ($report)
            @php
                $ok = collect($report)->where('ok', true)->count();
                $fail = collect($report)->where('ok', false)->count();
                $ringkas = array_filter([$summary['jakmall'] ?? null, $summary['orders'] ?? null, $summary['dropship'] ?? null]);
            @endphp

            {{-- Ringkasan menonjol --}}
            <div style="border-radius:.75rem;padding:1rem;border:1px solid {{ $fail ? '#fcd34d' : '#86efac' }};background:{{ $fail ? '#fffbeb' : '#f0fdf4' }}">
                <div style="font-weight:800;font-size:1rem;margin-bottom:.3rem">
                    {{ $fail ? '⚠️' : '✅' }} Import selesai — {{ $ok }} file berhasil{{ $fail ? ", {$fail} dilewati/gagal" : '' }}
                </div>
                @forelse ($ringkas as $line)
                    <div style="font-size:.85rem;color:#334155">• {{ $line }}</div>
                @empty
                    <div style="font-size:.85rem;color:#334155">Tidak ada data baru diproses. Cek detail di bawah.</div>
                @endforelse
            </div>

            {{-- Detail per file --}}
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
                    <div style="font-weight:700;margin-bottom:.6rem">💲 Perubahan harga HPP: {{ count($summary['hpp_changes']) }} SKU</div>
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
                    <p style="font-size:.78rem;color:#64748b;margin:.6rem 0 0">Pesanan lama tidak berubah kecuali Anda mencentang "Perbarui HPP pesanan lama".</p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
