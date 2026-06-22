<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4 text-sm text-gray-600 dark:text-gray-300">
            <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Cara import</p>
            <ol class="list-decimal ms-5 space-y-1">
                <li>Klik <strong>Unggah &amp; Import</strong> di kanan atas.</li>
                <li>Pilih <strong>toko tujuan</strong>, lalu unggah file ekspor (boleh beberapa: Laporan Penghasilan, file Pesanan, Master/Laporan Jakmall).</li>
                <li>Sistem mengenali jenis tiap file otomatis. File beda channel dilewati (tidak menggagalkan yang lain).</li>
            </ol>
        </div>

        @if ($report)
            @php
                $ok = collect($report)->where('ok', true)->count();
                $fail = collect($report)->where('ok', false)->count();
            @endphp
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4">
                <p class="font-semibold mb-3">
                    📋 Hasil import per file —
                    <span class="text-success-600">{{ $ok }} berhasil</span>@if ($fail), <span class="text-danger-600">{{ $fail }} gagal</span>@endif
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($report as $r)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-2 w-6">{{ $r['ok'] ? '✅' : '❌' }}</td>
                                <td class="py-2 pe-3 font-mono text-xs break-all max-w-xs">{{ $r['name'] }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-300">
                                    @if ($r['ok'])
                                        <span class="font-medium">{{ $r['type'] }}</span> · {{ $r['detail'] ?? '' }}
                                    @else
                                        <span class="text-warning-600">{{ $r['reason'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if (!empty($summary['hpp_changes']))
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4">
                    <p class="font-semibold mb-3">💲 Perubahan harga HPP: {{ count($summary['hpp_changes']) }} SKU</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 text-left">
                                <tr><th class="py-1 pe-3">SKU</th><th class="py-1 pe-3">Produk</th><th class="py-1 pe-3 text-right">Lama</th><th class="py-1 pe-3 text-right">Baru</th></tr>
                            </thead>
                            <tbody>
                            @foreach (array_slice($summary['hpp_changes'], 0, 50) as $c)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="py-1 pe-3 font-mono text-xs">{{ $c['sku'] }}</td>
                                    <td class="py-1 pe-3">{{ \Illuminate\Support\Str::limit($c['name'], 40) }}</td>
                                    <td class="py-1 pe-3 text-right">Rp {{ number_format($c['old'], 0, ',', '.') }}</td>
                                    <td class="py-1 pe-3 text-right">Rp {{ number_format($c['new'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Pesanan lama tidak berubah kecuali Anda mencentang "Perbarui HPP pesanan lama".</p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
