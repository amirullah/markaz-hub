<x-filament-panels::page>
    @php
        $th = 'text-align:left;padding:.5rem .6rem;font-size:.72rem;color:#64748b;text-transform:uppercase;border-bottom:1px solid #eef2f7';
        $td = 'padding:.55rem .6rem;font-size:.85rem;border-top:1px solid #f1f5f9;vertical-align:top';
        $obj = ['toko' => 'Toko', 'produk' => 'Produk', 'pesanan' => 'Pesanan'];
        // [label, warna teks, warna latar badge] — badge berwarna seragam dgn Pesanan.
        $evt = [
            'created' => ['Dibuat', '#166534', '#dcfce7'],
            'updated' => ['Diubah', '#1e40af', '#dbeafe'],
            'deleted' => ['Dihapus', '#991b1b', '#fee2e2'],
        ];
        $attrLabels = [
            'name' => 'Nama', 'marketplace' => 'Channel', 'active' => 'Aktif', 'note' => 'Catatan',
            'sku' => 'SKU', 'cost_price' => 'HPP', 'dropship_cost' => 'Modal Dropship', 'supplier_id' => 'Supplier',
            'status' => 'Status', 'fulfillment' => 'Pemenuhan', 'product_revenue' => 'Omzet', 'cogs' => 'HPP',
            'admin_fee' => 'Biaya Admin', 'buyer_name' => 'Nama Pembeli',
        ];
        $usesDropship = \App\Models\Organization::currentUsesDropship();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Log Aktivitas (100 terbaru)</x-slot>
        <x-slot name="description">Mencatat perubahan manual lewat panel (toko, produk, pesanan). Import massal tidak dicatat.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">Waktu</th>
                    <th style="{{ $th }}">Pengguna</th>
                    <th style="{{ $th }}">Aksi</th>
                    <th style="{{ $th }}">Objek</th>
                    <th style="{{ $th }}">Perubahan</th>
                </tr></thead>
                <tbody>
                @forelse ($activities as $a)
                    @php
                        [$evtLabel, $evtColor, $evtBg] = $evt[$a->event] ?? [$a->event, '#475569', '#f1f5f9'];
                        $changedKeys = array_keys($a->properties['attributes'] ?? []);
                        if (! $usesDropship) {
                            $changedKeys = array_filter($changedKeys, fn ($k) => ! in_array($k, ['dropship_cost', 'fulfillment'], true));
                        }
                        $changed = array_map(fn ($k) => $attrLabels[$k] ?? $k, $changedKeys);
                    @endphp
                    <tr>
                        <td style="{{ $td }};color:#64748b;white-space:nowrap">{{ $a->created_at?->format('d M Y H:i') }}</td>
                        <td style="{{ $td }}">{{ $a->causer?->name ?? $a->causer?->email ?? 'Sistem' }}</td>
                        <td style="{{ $td }};white-space:nowrap">
                            <span style="display:inline-block;font-size:11px;line-height:1.45;padding:1px 8px;border-radius:6px;background:{{ $evtBg }};color:{{ $evtColor }}">{{ $evtLabel }}</span>
                            <span style="color:#94a3b8">{{ $obj[$a->log_name] ?? $a->log_name }}</span>
                        </td>
                        <td style="{{ $td }};font-size:.8rem">{{ $obj[$a->log_name] ?? class_basename($a->subject_type) }} <span style="color:#94a3b8">#{{ $a->subject_id }}</span></td>
                        <td style="{{ $td }};color:#475569;font-size:.78rem">{{ $changed ? implode(', ', $changed) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada aktivitas tercatat. Coba ubah sebuah Toko/Produk lewat panel.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
