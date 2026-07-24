<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Label Pengiriman — {{ $order->external_no }}</title>
<style>
    @page { margin: 0; size: 100mm 150mm; }
    body { margin: 0; padding: 6mm; font-family: 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #000; }
    .label { width: 88mm; height: 138mm; position: relative; }

    .marketplace-bar {
        display: flex; align-items: center; gap: 6px;
        padding: 4px 6px; border-radius: 3px; margin-bottom: 4px;
    }
    .marketplace-bar.shopee { background: #ee4d2d; color: #fff; }
    .marketplace-bar.tiktok { background: #222; color: #fff; }
    .marketplace-bar .logo { font-weight: 800; font-size: 14px; letter-spacing: -0.5px; }
    .marketplace-bar .channel { font-size: 8px; opacity: 0.8; }

    .barcode-area {
        text-align: center; margin: 6px 0; padding: 4px 0; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc;
    }
    .barcode-area .tracking {
        font-family: 'Courier New', monospace; font-size: 22px; letter-spacing: 2px; font-weight: 700; margin: 2px 0;
    }
    .barcode-area .tracking-label { font-size: 7px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
    .barcode-render {
        font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 2px;
        display: flex; justify-content: center; margin: 2px 0;
    }
    .barcode-render .bar { display: flex; }
    .barcode-render .bar span { display: block; width: 2px; margin-right: 1px; }

    .addresses { display: flex; gap: 4px; margin: 4px 0; }
    .address-box { flex: 1; padding: 4px 5px; border: 1px solid #ddd; border-radius: 3px; }
    .address-box .label { font-size: 7px; text-transform: uppercase; color: #999; letter-spacing: 0.5px; margin-bottom: 1px; }
    .address-box .name { font-weight: 700; font-size: 11px; }
    .address-box .detail { font-size: 9px; color: #333; }

    .info-row { display: flex; justify-content: space-between; margin: 3px 0; padding: 2px 4px; background: #f5f5f5; border-radius: 2px; font-size: 9px; }
    .info-row .key { color: #666; }
    .info-row .val { font-weight: 600; }

    .footer-bar {
        position: absolute; bottom: 0; left: 0; right: 0;
        display: flex; justify-content: space-between; align-items: center;
        padding: 3px 6px; border-top: 1px solid #ddd; font-size: 7px; color: #999;
    }
    .footer-bar .order-no { font-family: monospace; }

    @media print { .no-print { display: none; } }
</style>
</head>
<body>
@php
    $isShopee = in_array($order->marketplace, ['SHOPEE']);
    $isTikTok = in_array($order->marketplace, ['TIKTOK', 'TIKTOKTOKO']);
    $mkClass = $isShopee ? 'shopee' : ($isTikTok ? 'tiktok' : 'generic');
    $mkName = $isShopee ? 'Shopee' : ($isTikTok ? 'TikTok Shop' : 'Marketplace');
@endphp

<div class="label">
    <div class="marketplace-bar {{ $mkClass }}">
        <span class="logo">{{ $isShopee ? 'SHOPEE' : ($isTikTok ? 'TIKTOK' : 'MKZ') }}</span>
        <span class="channel">Support by MarkazHub · {{ $order->store?->name ?? '-' }}</span>
    </div>

    <div class="barcode-area">
        <div class="tracking-label">Tracking Number</div>
        <div class="tracking">{{ $order->tracking_number ?: '-' }}</div>
        <div style="font-size:9px;color:#999;margin:3px 0 0">
            {{ chr(42) }}{{ $order->tracking_number ?? 'N/A' }}{{ chr(42) }}
        </div>
        <div style="font-size:7px;color:#999;font-family:monospace;letter-spacing:2px">
            *{{ $order->tracking_number ?? 'N/A' }}*
        </div>
    </div>

    <div class="addresses">
        <div class="address-box">
            <div class="label">Dari (Pengirim)</div>
            <div class="name">{{ $order->store?->name ?? 'MarkazHub Store' }}</div>
            <div class="detail">
                {{ $order->store?->marketplace ?? '-' }}<br>
                {{ $order->store?->shop_name ?? '-' }}
            </div>
        </div>
        <div class="address-box">
            <div class="label">Kepada (Penerima)</div>
            <div class="name">{{ $order->buyer_name ?: '-' }}</div>
            <div class="detail">
                {{ $order->buyer_username ? '@' . $order->buyer_username : '' }}<br>
                {{ $order->shipping_address ?? '' }}
            </div>
        </div>
    </div>

    <div class="info-row">
        <span class="key">Kurir</span>
        <span class="val">{{ $order->courier ?: '-' }}</span>
    </div>
    @if ($order->shipped_at)
    <div class="info-row">
        <span class="key">Tgl Kirim</span>
        <span class="val">{{ $order->shipped_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
    </div>
    @endif
    <div class="info-row">
        <span class="key">Layanan</span>
        <span class="val">{{ $isShopee ? 'Shopee XT Reguler' : ($isTikTok ? 'TikTok Logistik' : 'Reguler') }}</span>
    </div>
    @if ($order->items->isNotEmpty())
    <div class="info-row">
        <span class="key">Item</span>
        <span class="val">{{ $order->items->sum('qty') }} pcs · {{ $order->items->count() }} produk</span>
    </div>
    @endif

    <div class="footer-bar">
        <span class="order-no">{{ $order->external_no }}</span>
        <span>MarkazHub · {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span>
    </div>
</div>

<script>window.print();</script>
</body>
</html>
