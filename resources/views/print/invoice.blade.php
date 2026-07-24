<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Invoice — {{ $order->external_no }}</title>
<style>
    @page { margin: 1.5cm 2cm; size: A4 portrait; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; font-size: 12px; color: #0f172a; margin:0; padding:0; }
    .invoice { max-width: 170mm; margin:0 auto; }
    .header { display:flex; justify-content:space-between; align-items:start; border-bottom:2px solid #0f172a; padding-bottom:12px; margin-bottom:14px; }
    .header h1 { font-size: 22px; margin:0; text-transform:uppercase; letter-spacing:1px; }
    .header .sub { color:#64748b; font-size:11px; margin-top:2px; }
    .store { text-align:right; font-size:11px; }
    .store strong { display:block; font-size:13px; }
    .info { display:flex; justify-content:space-between; gap:20px; margin-bottom:14px; }
    .info .box { flex:1; background:#f8fafc; border-radius:6px; padding:10px; }
    .info .box strong { display:block; font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:4px; }
    .info .box .val { font-size:12px; }
    table { width:100%; border-collapse: collapse; margin-top:10px; }
    th, td { text-align:left; padding:7px 8px; border-bottom:1px solid #e2e8f0; font-size:11px; }
    th { background:#f8fafc; color:#475569; font-weight:600; text-transform:uppercase; font-size:10px; }
    .total-table { width:auto; margin-left:auto; margin-top:8px; }
    .total-table td { padding:4px 8px; border:none; font-size:11px; }
    .total-table .label { color:#64748b; text-align:right; }
    .total-table .value { text-align:right; font-weight:600; min-width:90px; }
    .total-table .grand td { font-size:14px; font-weight:700; border-top:2px solid #0f172a; padding-top:6px; }
    .footer { margin-top:20px; text-align:center; color:#94a3b8; font-size:9px; border-top:1px solid #e2e8f0; padding-top:8px; }
    .status { display:inline-block; padding:2px 10px; border-radius:8px; font-size:10px; font-weight:600; }
    .status.selesai { background:#dcfce7; color:#166534; }
    .status.dikirim { background:#dbeafe; color:#1e40af; }
    .status.dibatalkan { background:#fee2e2; color:#991b1b; }
    .status.default { background:#f1f5f9; color:#475569; }
    @media print { .no-print { display:none; } }
</style>
</head>
<body>
<div class="invoice">
    <div class="header">
        <div>
            <h1>Invoice</h1>
            <div class="sub">{{ $order->external_no }}</div>
        </div>
        <div class="store">
            <strong>{{ $order->store?->name ?? 'MarkazHub' }}</strong>
            <div>{{ $order->store?->marketplace ?? '-' }}</div>
        </div>
    </div>

    <div class="info">
        <div class="box">
            <strong>Tanggal Pesanan</strong>
            <div class="val">{{ $order->order_date?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</div>
        </div>
        <div class="box">
            <strong>Pembeli</strong>
            <div class="val">{{ $order->buyer_name ?: '-' }}</div>
        </div>
        <div class="box">
            <strong>Status</strong>
            <div class="val">
                <span class="status {{ match($order->status) { 'COMPLETED' => 'selesai', 'SHIPPED' => 'dikirim', 'CANCELLED', 'RETURNED' => 'dibatalkan', default => 'default' } }}">
                    {{ match($order->status) { 'COMPLETED' => 'Selesai', 'SHIPPED' => 'Dikirim', 'CANCELLED' => 'Dibatalkan', 'RETURNED' => 'Dikembalikan', 'PAID' => 'Dibayar', 'PENDING' => 'Menunggu', default => $order->status } }}
                </span>
            </div>
        </div>
    </div>

    @if ($order->processing_status)
    <div style="margin-bottom:8px;font-size:10px;color:#64748b">
        Proses: {{ \App\Models\Order::processingStatusLabel($order->processing_status) }}
        @if ($order->tracking_number) · Resi: {{ $order->tracking_number }} @endif
        @if ($order->courier) · {{ $order->courier }} @endif
        @if ($order->shipped_at) · Kirim: {{ $order->shipped_at->timezone('Asia/Jakarta')->format('d M Y') }} @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width:40%">Produk</th>
                <th>SKU</th>
                <th style="text-align:center">Qty</th>
                <th style="text-align:right">Harga</th>
                <th style="text-align:right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td style="font-family:monospace;font-size:10px;color:#64748b">{{ $item->sku ?: '—' }}</td>
                <td style="text-align:center">{{ (int) $item->qty }}</td>
                <td style="text-align:right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td style="text-align:right;font-weight:600">Rp {{ number_format((float) ($item->unit_price * $item->qty), 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px">Tidak ada rincian produk</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="total-table">
        @php
            $subtotal = $order->items->sum(fn($i) => $i->unit_price * $i->qty);
        @endphp
        <tr>
            <td class="label">Subtotal Produk</td>
            <td class="value">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</td>
        </tr>
        @if ((float) $order->product_revenue > 0 && (float) $order->product_revenue !== $subtotal)
        <tr>
            <td class="label">Revenue Marketplace</td>
            <td class="value">Rp {{ number_format((float) $order->product_revenue, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td class="label">Total</td>
            <td class="value">Rp {{ number_format((float) max($subtotal, $order->product_revenue), 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Dicetak {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · MarkazHub
    </div>
</div>
<script>window.print();</script>
</body>
</html>
