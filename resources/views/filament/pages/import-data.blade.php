<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.85rem;padding:1.1rem 1.2rem;background:#fff';
        $ul = 'margin:.35rem 0 0 1.1rem;padding:0;font-size:.8rem;color:#64748b;line-height:1.6';
        $dropship = \App\Models\Organization::currentUsesDropship();
    @endphp

    <div style="display:flex;flex-direction:column;gap:1rem;max-width:760px">
        <p style="margin:0;color:#475569;font-size:.9rem">Pilih jenis impor sesuai file Anda. Tiap kotak punya tombolnya sendiri — tinggal klik di kotak yang sesuai.</p>

        {{-- ===== 1) Laporan Marketplace ===== --}}
        <div style="{{ $card }};border-left:4px solid #2563eb">
            <div style="display:flex;align-items:flex-start;gap:.85rem">
                <div style="font-size:1.6rem;line-height:1">📊</div>
                <div style="flex:1">
                    <div style="font-weight:700;color:#1e293b;font-size:1.02rem">Laporan Marketplace</div>
                    <div style="font-size:.85rem;color:#475569;margin:.25rem 0">File yang Anda <strong>download dari Shopee / Tokopedia / TikTok</strong>. Dari sinilah pesanan &amp; laba dihitung.</div>
                    <ul style="{{ $ul }}">
                        <li><strong>Laporan Penghasilan</strong> — biaya admin &amp; laba final yang akurat.</li>
                        <li><strong>Laporan / File Pesanan</strong> — daftar pesanan, produk, jumlah, status.</li>
                    </ul>
                    <div style="margin-top:.8rem">{{ $this->importAction }}</div>
                </div>
            </div>
        </div>

        {{-- ===== 2) Daftar Produk ===== --}}
        <div style="{{ $card }};border-left:4px solid #64748b">
            <div style="display:flex;align-items:flex-start;gap:.85rem">
                <div style="font-size:1.6rem;line-height:1">📦</div>
                <div style="flex:1">
                    <div style="font-weight:700;color:#1e293b;font-size:1.02rem">Daftar Produk</div>
                    <div style="font-size:.85rem;color:#475569;margin:.25rem 0">Daftar produk Anda + <strong>harga modal</strong> (untuk menghitung laba). Bukan file dari marketplace.</div>
                    <ul style="{{ $ul }}">
                        <li>File Excel/CSV berisi <strong>Kode Produk (SKU)</strong> &amp; <strong>Harga Modal</strong> (opsional: Nama Produk, Tanggal).</li>
                        <li>Isi asal produk — mis. <em>"Stok Sendiri"</em> atau <em>"Supplier A"</em>.</li>
                    </ul>
                    <div style="margin-top:.8rem">{{ $this->catalogAction }}</div>
                </div>
            </div>
        </div>

        {{-- ===== 3) Dropship ===== --}}
        @if ($dropship)
            <div style="{{ $card }};border-left:4px solid #f59e0b">
                <div style="display:flex;align-items:flex-start;gap:.85rem">
                    <div style="font-size:1.6rem;line-height:1">🚚</div>
                    <div style="flex:1">
                        <div style="font-weight:700;color:#1e293b;font-size:1.02rem">Dropship</div>
                        <div style="font-size:.85rem;color:#475569;margin:.25rem 0"><strong>Biaya dropship per pesanan</strong> — supaya laba pesanan dropship dihitung benar.</div>
                        <ul style="{{ $ul }}">
                            <li><strong>Punya file laporan dari penyedia dropship?</strong> Langsung unggah di sini.</li>
                            <li><strong>Tidak punya laporan?</strong> Klik <em>"Unduh Format File"</em>, isi <strong>No. Pesanan</strong> &amp; <strong>Biaya Dropship</strong>, lalu unggah.</li>
                        </ul>
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem;margin-top:.8rem">
                            {{ $this->dropshipAction }}
                            {{ $this->downloadTemplateAction }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <p style="margin:.2rem 0 0;font-size:.8rem;color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;padding:.55rem .7rem">
            ✅ <strong>Aman mengunggah file yang sama berulang kali.</strong> Sistem memperbarui data lama berdasarkan nomor pesanan / kode produk — <strong>tidak akan membuat data dobel</strong>.
        </p>

        {{-- ===== Hasil impor Laporan Marketplace ===== --}}
        @if ($report)
            @php
                $ok = collect($report)->where('ok', true)->count();
                $fail = collect($report)->where('ok', false)->count();
                $ringkas = array_filter([$summary['orders'] ?? null]);
            @endphp

            <div style="border-radius:.85rem;padding:1rem;border:1px solid {{ $fail ? '#fcd34d' : '#86efac' }};background:{{ $fail ? '#fffbeb' : '#f0fdf4' }}">
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
        @endif
    </div>
</x-filament-panels::page>
