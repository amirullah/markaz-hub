<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Invoice — {{ $orders->count() }} pesanan</title>
<style>
    @page { margin: 1cm 1.5cm; size: A4 portrait; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin:0; padding:0; }
    .page-break { page-break-after: always; }
    .invoice { max-width: 170mm; margin:0 auto; padding:10px 0; }
    .header { display:flex; justify-content:space-between; align-items:start; border-bottom:2px solid #0f172a; padding-bottom:8px; margin-bottom:10px; }
    .header h2 { font-size: 16px; margin:0; text-transform:uppercase; letter-spacing:1px; }
    .header .sub { color:#64748b; font-size:10px; }
    .store { text-align:right; font-size:10px; }
    .store strong { display:block; font-size:11px; }
    .info { display:flex; gap:10px; margin-bottom:10px; }
    .info .box { flex:1; background:#f8fafc; border-radius:4px; padding:6px 8px; }
    .info .box strong { display:block; font-size:9px; color:#64748b; text-transform:uppercase; }
    .info .box .val { font-size:11px; }
    table { width:100%; border-collapse: collapse; margin-top:6px; }
    th, td { text-align:left; padding:4px 6px; border-bottom:1px solid #e2e8f0; font-size:10px; }
    th { background:#f8fafc; color:#475569; font-weight:600; text-transform:uppercase; font-size:9px; }
    .total { text-align:right; font-weight:600; margin-top:4px; font-size:11px; }
    .footer { margin-top:10px; text-align:center; color:#94a3b8; font-size:8px; border-top:1px solid #e2e8f0; padding-top:4px; }
    @media print { .no-print { display:none; } }
</style>
</head>
<body>
@foreach ($orders as $order)
<div class="invoice @if(!$loop->last) page-break @endif">
    <div class="header">
        <div>
            <h2>Invoice</h2>
            <div class="sub">{{ $order->external_no }}</div>
        </div>
        <div class="store">
            <strong>{{ $order->store?->name ?? 'MarkazHub' }}</strong>
            <div>{{ $order->store?->marketplace ?? '-' }}</div>
        </div>
    </div>

    <div class="info">
        <div class="box">
            <strong>Tanggal</strong>
            <div class="val">{{ $order->order_date?->timezone('Asia/Jakarta')->format('d M Y') }}</div>
        </div>
        <div class="box">
            <strong>Pembeli</strong>
            <div class="val">{{ $order->buyer_name ?: '-' }}</div>
        </div>
        <div class="box">
            <strong>Status</strong>
            <div class="val">{{ $order->status }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
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
                <td style="font-family:monospace;color:#64748b">{{ $item->sku ?: '—' }}</td>
                <td style="text-align:center">{{ (int) $item->qty }}</td>
                <td style="text-align:right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td style="text-align:right">Rp {{ number_format((float) ($item->unit_price * $item->qty), 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:8px">Tidak ada rincian produk</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">
        Total: Rp {{ number_format((float) $order->items->sum(fn($i) => $i->unit_price * $i->qty), 0, ',', '.') }}
    </div>
</div>
@endforeach
<script>window.print();</script>
</body>
</html>
