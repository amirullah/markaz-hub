<?php

namespace App\Services\TokpedTikTok;

use App\Models\MarketplaceConnection;
use App\Services\Import\OrderImporter;
use Illuminate\Support\Carbon;

/**
 * Sinkron data TikTok Shop API → pipeline impor (sama pola ShopeeSync).
 *
 * TikTok Shop access_token berlaku 7 hari, refresh_token punya expiry sendiri.
 * Normalisasi data TikTok → bentuk normalized (sama format file import).
 */
class TokpedTikTokSync
{
    private const TZ = 'Asia/Jakarta';

    private const STATUS_MAP = [
        'UNPAID' => 'PENDING',
        'AWAITING_SHIPMENT' => 'PAID',
        'AWAITING_COLLECTION' => 'PAID',
        'IN_TRANSIT' => 'SHIPPED',
        'DELIVERED' => 'COMPLETED',
        'COMPLETED' => 'COMPLETED',
        'CANCELLED' => 'CANCELLED',
        'PARTIALLY_REFUNDED' => 'RETURNED',
        'FULLY_REFUNDED' => 'RETURNED',
    ];

    public function __construct(private readonly TokpedTikTokClient $client)
    {
    }

    public function syncStore(MarketplaceConnection $conn, int $daysBack = 15): array
    {
        $from = $conn->last_synced_at
            ? $conn->last_synced_at->clone()->subMinutes(30)->getTimestamp()
            : now()->subDays($daysBack)->getTimestamp();
        $until = now()->getTimestamp();

        $orders = [];
        $page = 1;
        do {
            $res = $this->client->orderList($conn, $from, $until, 100, $page);
            $list = $res['orders'] ?? $res['list'] ?? [];
            foreach ($list as $o) {
                $orders[] = $o;
            }
            $total = (int) ($res['total_count'] ?? $res['total'] ?? 0);
            $page++;
        } while (count($orders) < $total && count($list) > 0);

        $summary = $this->syncOrders($conn, $orders);
        $conn->forceFill(['last_synced_at' => now(), 'status' => 'CONNECTED', 'last_error' => null])->save();

        return $summary;
    }

    public function syncOrders(MarketplaceConnection $conn, array $orders): array
    {
        if (empty($orders)) {
            return ['orders' => 0, 'message' => 'Tidak ada pesanan pada rentang ini.'];
        }

        $fromDetail = [];
        $fromSettlement = [];

        foreach ($orders as $o) {
            $orderId = (string) ($o['order_id'] ?? '');
            if ($orderId === '') {
                continue;
            }
            try {
                $detail = $this->client->orderDetail($conn, $orderId);
                $norm = $this->normalizeDetail($detail);
                $fromDetail[] = $norm;

                if (in_array($norm['status'], ['COMPLETED', 'RETURNED'], true)) {
                    try {
                        $sett = $this->client->settlementDetail($conn, $orderId);
                        if ($row = $this->normalizeSettlement($sett, $norm)) {
                            $fromSettlement[] = $row;
                        }
                    } catch (TokpedTikTokApiException $e) {
                        \Log::info('Settlement TikTok belum tersedia', ['order_id' => $orderId, 'err' => $e->getMessage()]);
                    }
                }
            } catch (TokpedTikTokApiException $e) {
                \Log::warning('Gagal ambil detail TikTok', ['order_id' => $orderId, 'err' => $e->getMessage()]);
            }
        }

        if (! $fromDetail) {
            return ['orders' => 0, 'message' => 'Detail pesanan tidak ditemukan di TikTok.'];
        }

        $sources = [$fromDetail];
        if ($fromSettlement) {
            $sources[] = $fromSettlement;
        }

        $msg = (new OrderImporter((int) $conn->organization_id))
            ->importFromApi($sources, (int) $conn->store_id, 'TIKTOKTOKO');

        return ['orders' => count($fromDetail), 'settlement' => count($fromSettlement), 'message' => $msg];
    }

    public function syncCatalog(MarketplaceConnection $conn): array
    {
        $orgId = (int) $conn->organization_id;
        $now = now();
        $products = [];
        $pmi = [];

        $page = 1;
        do {
            $res = $this->client->productList($conn, $page, 100);
            $list = $res['products'] ?? $res['list'] ?? [];
            foreach ($list as $p) {
                $productId = (string) ($p['product_id'] ?? $p['id'] ?? '');
                $productName = trim((string) ($p['product_name'] ?? $p['name'] ?? ''));
                $skus = $p['skus'] ?? $p['sku_list'] ?? [];

                if (! empty($skus)) {
                    foreach ($skus as $sku) {
                        $skuCode = trim((string) ($sku['sku_code'] ?? $sku['seller_sku'] ?? ''));
                        if ($skuCode === '') {
                            continue;
                        }
                        $skuName = trim((string) ($sku['sku_name'] ?? ''));
                        $products[$skuCode] = $productName . ($skuName !== '' ? ' - ' . $skuName : '');
                        if ($productId !== '') {
                            $pmi[$productId . ':' . ($sku['sku_id'] ?? '')] = $skuCode;
                        }
                    }
                } elseif ($productId !== '') {
                    try {
                        $detail = $this->client->productDetail($conn, $productId);
                        $this->extractSkus($detail, $products, $pmi, $productName);
                    } catch (TokpedTikTokApiException) {
                    }
                }
            }
            $total = (int) ($res['total_count'] ?? $res['total'] ?? 0);
            $page++;
        } while (count($products) < $total && count($list) > 0);

        $ins = 0;
        foreach (array_chunk(array_keys($products), 300) as $skus) {
            $rows = [];
            foreach ($skus as $sku) {
                $rows[] = ['organization_id' => $orgId, 'sku' => mb_substr($sku, 0, 100),
                    'name' => mb_substr($products[$sku], 0, 255), 'created_at' => $now, 'updated_at' => $now];
            }
            $ins += \DB::table('products')->upsert($rows, ['organization_id', 'sku'], ['name', 'updated_at']);
        }
        foreach (array_chunk(array_keys($pmi), 300) as $idsChunk) {
            $rows = [];
            foreach ($idsChunk as $mpKey => $sku) {
                $rows[] = ['organization_id' => $orgId,
                    'marketplace_product_id' => mb_substr((string) $mpKey, 0, 64),
                    'sku' => mb_substr($sku, 0, 100), 'created_at' => $now, 'updated_at' => $now];
            }
            \DB::table('product_marketplace_ids')->upsert($rows, ['organization_id', 'marketplace_product_id'], ['sku', 'updated_at']);
        }

        return ['products' => count($products), 'mapped_ids' => count($pmi)];
    }

    private function extractSkus(array $detail, array &$products, array &$pmi, string $productName): void
    {
        $skus = $detail['skus'] ?? $detail['sku_list'] ?? [];
        $productId = (string) ($detail['product_id'] ?? $detail['id'] ?? '');
        foreach ($skus as $sku) {
            $skuCode = trim((string) ($sku['sku_code'] ?? $sku['seller_sku'] ?? ''));
            if ($skuCode === '') {
                continue;
            }
            $skuName = trim((string) ($sku['sku_name'] ?? ''));
            $products[$skuCode] = $productName . ($skuName !== '' ? ' - ' . $skuName : '');
            if ($productId !== '') {
                $pmi[$productId . ':' . ($sku['sku_id'] ?? '')] = $skuCode;
            }
        }
    }

    private function normalizeDetail(array $data): array
    {
        $order = $data['order'] ?? $data;
        $items = [];
        $revenue = 0.0;
        $orderItems = $order['order_items'] ?? $order['items'] ?? [];

        foreach ($orderItems as $it) {
            $qty = (int) ($it['quantity'] ?? $it['qty'] ?? 1);
            $price = (float) ($it['unit_price'] ?? $it['price'] ?? 0);
            $revenue += $price * $qty;
            $items[] = [
                'sku' => trim((string) ($it['sku_code'] ?? $it['seller_sku'] ?? $it['sku'] ?? '')),
                'name' => trim((string) ($it['product_name'] ?? $it['name'] ?? '')),
                'qty' => max(1, $qty),
                'qtyAssumed' => false,
                'unitPrice' => $price,
            ];
        }

        return [
            'externalNo' => (string) ($order['order_id'] ?? ''),
            'orderDate' => $this->ts($order['create_time'] ?? null),
            'status' => self::STATUS_MAP[(string) ($order['order_status'] ?? $data['order_status'] ?? '')] ?? 'PENDING',
            'buyerName' => (string) ($order['buyer_name'] ?? ''),
            'shippingChargedToBuyer' => 0.0,
            'adminFee' => 0.0,
            'shippingCostSeller' => 0.0,
            'voucherSellerBorne' => 0.0,
            'otherIncome' => 0.0,
            'otherCost' => 0.0,
            'productRevenue' => $revenue,
            'note' => null,
            'items' => $items,
            '_hasIncome' => false,
        ];
    }

    private function normalizeSettlement(array $data, array $detail): ?array
    {
        $sett = $data['settlement'] ?? $data;
        if (empty($sett)) {
            return null;
        }

        $revenue = (float) ($sett['original_price'] ?? $sett['total_amount'] ?? 0);
        $net = (float) ($sett['net_amount'] ?? $sett['settlement_amount'] ?? 0);

        $admin = (float) ($sett['platform_commission'] ?? $sett['commission_fee'] ?? 0)
            + (float) ($sett['transaction_fee'] ?? 0)
            + (float) ($sett['service_fee'] ?? 0);

        $voucher = (float) ($sett['seller_voucher'] ?? $sett['voucher_seller'] ?? 0);
        $other = ($revenue - $net) - ($admin + $voucher);

        $refund = abs((float) ($sett['refund_amount'] ?? 0));
        $isReturn = $refund != 0.0 || ($revenue <= 0 && $net <= 0);

        return [
            'externalNo' => $detail['externalNo'],
            'orderDate' => $detail['orderDate'],
            'status' => $isReturn ? 'RETURNED' : 'COMPLETED',
            'buyerName' => $detail['buyerName'],
            'shippingChargedToBuyer' => (float) ($sett['buyer_shipping_fee'] ?? 0),
            'adminFee' => $admin,
            'shippingCostSeller' => 0.0,
            'voucherSellerBorne' => $voucher,
            'otherIncome' => 0.0,
            'otherCost' => $other,
            'productRevenue' => $revenue,
            'items' => [],
            '_hasIncome' => true,
        ];
    }

    private function ts(mixed $epoch): string
    {
        return $epoch
            ? Carbon::createFromTimestamp((int) $epoch, self::TZ)->format('Y-m-d H:i:s')
            : now(self::TZ)->format('Y-m-d H:i:s');
    }
}