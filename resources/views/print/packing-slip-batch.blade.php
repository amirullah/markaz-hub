<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Packing Slip Batch — MarkazHub</title>
<style>
    @page { margin: 1.2cm 1.5cm; size: A4 portrait; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; font-size: 12px; color: #0f172a; margin:0; padding:0; }
    .slip { max-width: 190mm; margin:0 auto; page-break-after: always; padding: 10px 0; }
    h1 { font-size: 18px; margin:0 0 2px; }
    .meta { color:#64748b; font-size:11px; margin-bottom:8px; }
    table { width:100%; border-collapse: collapse; margin-top:10px; }
    th, td { text-align:left; padding:6px 8px; border-bottom:1px solid #e2e8f0; font-size:11px; }
    th { background:#f8fafc; color:#475569; font-weight:600; text-transform:uppercase; font-size:10px; }
    td { vertical-align:top; }
    .address { background:#f8fafc; border-radius:6px; padding:10px; margin-top:8px; }
    .address strong { display:block; font-size:12px; margin-bottom:2px; }
    .address span { color:#64748b; font-size:11px; }
    .footer { margin-top:20px; text-align:center; color:#94a3b8; font-size:9px; border-top:1px solid #e2e8f0; padding-top:8px; }
    .barcode { font-family: 'Courier New', monospace; font-size:16px; letter-spacing:2px; margin-top:6px; }
    .header-info { display:flex; justify-content:space-between; align-items:start; }
    .header-right { text-align:right; font-size:10px; color:#64748b; }
</style>
</head>
<body>
@foreach ($orders as $order)
<div class="slip">
    <div class="header-info">
        <div>
            <h1>Packing Slip</h1>
            <div class="meta">
                {{ $order->external_no }} · {{ $order->order_date?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
            </div>
        </div>
        <div class="header-right">
            <div>MarkazHub</div>
            <div>{{ $order->store?->name ?? '-' }}</div>
        </div>
    </div>

    <div class="address">
        <strong>Pembeli</strong>
        <span>{{ $order->buyer_name ?: '-' }}</span>
        <div class="barcode">{{ $order->external_no }}</div>
    </div>

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
                <td style="text-align:right;font-weight:700">Rp {{ number_format((float) ($item->unit_price * $item->qty), 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:12px">Tidak ada rincian produk</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:8px;text-align:right;font-size:11px">
        <span style="color:#64748b">Total Item: </span>
        <span style="font-weight:700">{{ $order->items->sum('qty') }}</span>
    </div>

    <div class="footer">Dicetak {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · MarkazHub</div>
</div>
@endforeach
<script>window.print();</script>
</body>
</html>
