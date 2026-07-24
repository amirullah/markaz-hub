<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Label Pengiriman — {{ $orders->count() }} pesanan</title>
<style>
    @page { margin: 0; size: 100mm 150mm; }
    body { margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 9px; color: #000; }
    .page-break { page-break-after: always; }
    .label { width: 88mm; height: 138mm; padding: 5mm; position: relative; }

    .header { display: flex; align-items: center; gap: 6px; padding: 3px 5px; border-radius: 3px; margin-bottom: 3px; }
    .header.shopee { background: #ee4d2d; color: #fff; }
    .header.tiktok { background: #222; color: #fff; }
    .header .brand { font-weight: 800; font-size: 13px; letter-spacing: -0.5px; }

    .barcode-area { text-align: center; margin: 5px 0; padding: 3px 0; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; }
    .barcode-area .no { font-family: 'Courier New', monospace; font-size: 20px; letter-spacing: 2px; font-weight: 700; margin: 2px 0; }
    .barcode-area .label-small { font-size: 6px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
    .barcode-area .stars { font-size: 8px; color: #999; font-family: monospace; letter-spacing: 2px; }

    .boxes { display: flex; gap: 3px; margin: 3px 0; }
    .box { flex: 1; padding: 3px 4px; border: 1px solid #ddd; border-radius: 2px; }
    .box .lbl { font-size: 6px; text-transform: uppercase; color: #999; letter-spacing: 0.5px; }
    .box .name { font-weight: 700; font-size: 10px; }
    .box .detail { font-size: 8px; color: #333; }

    .row { display: flex; justify-content: space-between; margin: 2px 0; padding: 2px 4px; background: #f5f5f5; border-radius: 2px; font-size: 8px; }

    .footer { position: absolute; bottom: 4px; left: 5mm; right: 5mm; display: flex; justify-content: space-between; border-top: 1px solid #ddd; padding-top: 2px; font-size: 6px; color: #999; }
    .footer .ord { font-family: monospace; }

    @media print { .no-print { display: none; } }
</style>
</head>
<body>
@foreach ($orders as $order)
@php
    $isShopee = in_array($order->marketplace, ['SHOPEE']);
    $isTikTok = in_array($order->marketplace, ['TIKTOK', 'TIKTOKTOKO']);
    $cls = $isShopee ? 'shopee' : ($isTikTok ? 'tiktok' : 'generic');
@endphp
<div class="label @if(!$loop->last) page-break @endif">
    <div class="header {{ $cls }}">
        <span class="brand">{{ $isShopee ? 'SHOPEE' : ($isTikTok ? 'TIKTOK' : 'MARKAZ') }}</span>
        <span style="font-size:7px;opacity:0.8">MarkazHub · {{ $order->external_no }}</span>
    </div>

    <div class="barcode-area">
        <div class="label-small">Tracking Number</div>
        <div class="no">{{ $order->tracking_number ?: '-' }}</div>
        <div class="stars">{{ chr(42) }}{{ $order->tracking_number ?? 'N/A' }}{{ chr(42) }}</div>
    </div>

    <div class="boxes">
        <div class="box">
            <div class="lbl">Pengirim</div>
            <div class="name">{{ $order->store?->name ?? '-' }}</div>
            <div class="detail">{{ $order->store?->marketplace ?? '-' }}</div>
        </div>
        <div class="box">
            <div class="lbl">Penerima</div>
            <div class="name">{{ $order->buyer_name ?: '-' }}</div>
            <div class="detail">{{ $order->shipping_address ? \Illuminate\Support\Str::limit($order->shipping_address, 40) : '' }}</div>
        </div>
    </div>

    <div class="row"><span>Kurir</span><span style="font-weight:600">{{ $order->courier ?: '-' }}</span></div>
    <div class="row"><span>Layanan</span><span style="font-weight:600">{{ $isShopee ? 'Shopee XT' : ($isTikTok ? 'TikTok Logistik' : 'Reguler') }}</span></div>
    @if ($order->items->isNotEmpty())
    <div class="row"><span>Item</span><span style="font-weight:600">{{ (int) $order->items->sum('qty') }} pcs</span></div>
    @endif

    <div class="footer">
        <span class="ord">{{ $order->external_no }}</span>
        <span>{{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endforeach
<script>window.print();</script>
</body>
</html>
