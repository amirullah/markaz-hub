<?php

namespace App\Services\Shopee;

use App\Models\MarketplaceConnection;
use App\Services\Import\OrderImporter;
use Illuminate\Support\Carbon;

/**
 * Sinkron data Shopee API → pipeline impor yang SUDAH teruji.
 *
 * Prinsip: API hanya "sumber file lain". Detail pesanan dipetakan ke bentuk
 * normalized yang sama dengan file "Order Completed", escrow dipetakan MENIRU
 * PERSIS mp_income_to_orders() (residual → otherCost), lalu keduanya digabung
 * mp_merge_orders() dan masuk OrderImporter::importFromApi() — sehingga aturan
 * merge status, HPP historis, estimasi→final, dan dropship terpakai ulang utuh.
 */
class ShopeeSync
{
    /** Status Shopee → status kanonik importer (JANGAN andalkan regex utk kata 'UNPAID'). */
    private const STATUS = [
        'UNPAID' => 'PENDING',
        'READY_TO_SHIP' => 'PAID',
        'PROCESSED' => 'PAID',
        'RETRY_SHIP' => 'PAID',
        'SHIPPED' => 'SHIPPED',
        'TO_CONFIRM_RECEIVE' => 'SHIPPED',
        'COMPLETED' => 'COMPLETED',
        'IN_CANCEL' => 'CANCELLED',
        'CANCELLED' => 'CANCELLED',
        'TO_RETURN' => 'RETURNED',
    ];

    /** Komponen biaya platform di escrow (cermin daftar platformFees file income). */
    private const ESCROW_FEES = [
        'commission_fee', 'service_fee', 'seller_transaction_fee', 'order_ams_commission_fee',
        'campaign_fee', 'delivery_seller_protection_fee_premium_amount', 'credit_card_transaction_fee',
    ];

    /** Diskon ditanggung penjual di escrow (cermin sellerDiscounts file income). */
    private const ESCROW_SELLER_DISCOUNTS = ['voucher_from_seller', 'seller_coin_cash_back'];

    /** Zona waktu tanggal pesanan (file ekspor seller memakai WIB; epoch API = UTC). */
    private const TZ = 'Asia/Jakarta';

    public function __construct(private readonly ShopeeClient $client)
    {
    }

    /**
     * Sinkron penuh satu toko sejak watermark (atau $daysBack hari bila belum pernah).
     * Rentang dipotong per ≤15 hari (batas get_order_list) + jendela tumpang 30 menit.
     */
    public function syncStore(MarketplaceConnection $conn, int $daysBack = 15): array
    {
        $from = $conn->last_synced_at
            ? $conn->last_synced_at->clone()->subMinutes(30)->getTimestamp()
            : now()->subDays($daysBack)->getTimestamp();
        $until = now()->getTimestamp();

        $sns = [];
        for ($start = $from; $start < $until; $start += 15 * 86400 - 60) {
            $end = min($start + 15 * 86400 - 60, $until);
            $cursor = '';
            do {
                $res = $this->client->orderList($conn, $start, $end, $cursor);
                foreach (($res['order_list'] ?? []) as $o) {
                    $sns[] = (string) $o['order_sn'];
                }
                $cursor = (string) ($res['next_cursor'] ?? '');
            } while (($res['more'] ?? false) && $cursor !== '');
        }
        $sns = array_values(array_unique($sns));

        $summary = $this->syncOrderSns($conn, $sns);
        $conn->forceFill(['last_synced_at' => now(), 'status' => 'CONNECTED', 'last_error' => null])->save();

        return $summary;
    }

    /** Tarik detail (+escrow) sekumpulan pesanan lalu masukkan ke pipeline impor. */
    public function syncOrderSns(MarketplaceConnection $conn, array $orderSns): array
    {
        $orderSns = array_values(array_filter(array_unique($orderSns)));
        if (! $orderSns) {
            return ['orders' => 0, 'message' => 'Tidak ada pesanan pada rentang ini.'];
        }

        $fromDetail = [];  // sumber 1: setara file "Order Completed" (item/SKU/status)
        $fromEscrow = [];  // sumber 2: setara file "Laporan Penghasilan" (biaya final)

        foreach (array_chunk($orderSns, 50) as $chunk) {
            $res = $this->client->orderDetail($conn, $chunk);
            foreach (($res['order_list'] ?? []) as $od) {
                $norm = $this->normalizeDetail($od);
                $fromDetail[] = $norm;

                // Escrow tersedia & final utk pesanan selesai/retur → tarik agar laba FINAL.
                if (in_array($norm['status'], ['COMPLETED', 'RETURNED'], true)) {
                    try {
                        $esc = $this->client->escrowDetail($conn, $norm['externalNo']);
                        if ($row = $this->normalizeEscrow($esc, $norm)) {
                            $fromEscrow[] = $row;
                        }
                    } catch (ShopeeApiException $e) {
                        // Escrow belum siap utk pesanan ini — biarkan estimasi; reconciliation menyusul.
                        \Log::info('Escrow belum tersedia', ['order_sn' => $norm['externalNo'], 'err' => $e->getMessage()]);
                    }
                }
            }
        }

        if (! $fromDetail) {
            return ['orders' => 0, 'message' => 'Detail pesanan tidak ditemukan di Shopee.'];
        }

        $sources = [$fromDetail];
        if ($fromEscrow) {
            $sources[] = $fromEscrow;
        }

        $msg = (new OrderImporter((int) $conn->organization_id))
            ->importFromApi($sources, (int) $conn->store_id, 'SHOPEE');

        return ['orders' => count($fromDetail), 'escrow' => count($fromEscrow), 'message' => $msg];
    }

    /**
     * Sinkron katalog produk: item & varian Shopee → products (sku, nama) +
     * product_marketplace_ids (item_id → sku). HARGA MODAL TIDAK DISENTUH —
     * Shopee tidak tahu modal Anda; modal tetap dari Impor Daftar Produk.
     */
    public function syncCatalog(MarketplaceConnection $conn): array
    {
        $orgId = (int) $conn->organization_id;
        $now = now();
        $products = [];   // sku => name
        $pmi = [];        // item_id => sku (hanya item ber-item_sku; varian resolve via model_sku)

        $offset = 0;
        do {
            $page = $this->client->itemList($conn, $offset, 100);
            $items = $page['item'] ?? [];
            $ids = array_values(array_filter(array_map(fn ($i) => (int) ($i['item_id'] ?? 0), $items)));

            foreach (array_chunk($ids, 50) as $chunk) {
                $info = $this->client->itemBaseInfo($conn, $chunk);
                foreach (($info['item_list'] ?? []) as $it) {
                    $itemId = (string) ($it['item_id'] ?? '');
                    $itemName = trim((string) ($it['item_name'] ?? ''));
                    $itemSku = trim((string) ($it['item_sku'] ?? ''));

                    if ($itemSku !== '') {
                        $products[$itemSku] = $itemName;
                        if ($itemId !== '') {
                            $pmi[$itemId] = $itemSku;
                        }
                    }
                    if (! empty($it['has_model'])) {
                        $models = $this->client->modelList($conn, (int) $itemId);
                        foreach (($models['model'] ?? []) as $m) {
                            $mSku = trim((string) ($m['model_sku'] ?? ''));
                            if ($mSku === '') {
                                continue;
                            }
                            $mName = trim((string) ($m['model_name'] ?? ''));
                            $products[$mSku] = $itemName . ($mName !== '' ? ' - ' . $mName : '');
                        }
                    }
                }
            }

            $offset = (int) ($page['next_offset'] ?? 0);
        } while (! empty($page['has_next_page']) && $offset > 0);

        // Upsert products per (org, sku) — nama diperbarui, cost_price TIDAK diubah.
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
            foreach ($idsChunk as $mpId) {
                $rows[] = ['organization_id' => $orgId, 'marketplace_product_id' => mb_substr((string) $mpId, 0, 64),
                    'sku' => mb_substr($pmi[$mpId], 0, 100), 'created_at' => $now, 'updated_at' => $now];
            }
            \DB::table('product_marketplace_ids')->upsert($rows, ['organization_id', 'marketplace_product_id'], ['sku', 'updated_at']);
        }

        return ['products' => count($products), 'mapped_ids' => count($pmi)];
    }

    /**
     * Susulkan settlement: pesanan COMPLETED yang labanya masih estimasi
     * (income_verified=false) dicoba tarik escrow-nya lagi (dana mungkin baru cair).
     * Dipakai reconciliation terjadwal. $limit membatasi panggilan API per run.
     */
    public function retryPendingEscrow(MarketplaceConnection $conn, int $limit = 200): array
    {
        $orders = \DB::table('orders')
            ->where('organization_id', $conn->organization_id)
            ->where('store_id', $conn->store_id)
            ->where('status', 'COMPLETED')
            ->where('income_verified', false)
            ->whereNull('deleted_at')
            ->where('order_date', '>=', now()->subDays(90))
            ->orderByDesc('order_date')
            ->limit($limit)
            ->get(['external_no', 'order_date', 'buyer_name']);

        $rows = [];
        foreach ($orders as $o) {
            try {
                $esc = $this->client->escrowDetail($conn, (string) $o->external_no);
            } catch (ShopeeApiException) {
                continue; // belum cair — coba lagi run berikutnya
            }
            $row = $this->normalizeEscrow($esc, [
                'externalNo' => (string) $o->external_no,
                'orderDate' => (string) $o->order_date,
                'buyerName' => (string) ($o->buyer_name ?? ''),
            ]);
            if ($row) {
                $rows[] = $row;
            }
        }

        $msg = '';
        if ($rows) {
            $msg = (new OrderImporter((int) $conn->organization_id))
                ->importFromApi([$rows], (int) $conn->store_id, 'SHOPEE');
        }

        return ['checked' => $orders->count(), 'settled' => count($rows), 'message' => $msg];
    }

    // =========================================================================
    // PEMETAAN → bentuk normalized (samakan dgn pipeline file)
    // =========================================================================

    private function normalizeDetail(array $od): array
    {
        $items = [];
        $revenue = 0.0;
        foreach (($od['item_list'] ?? []) as $it) {
            $qty = (int) ($it['model_quantity_purchased'] ?? 1);
            $price = (float) ($it['model_discounted_price'] ?? $it['model_original_price'] ?? 0);
            $revenue += $price * $qty;
            $items[] = [
                'sku' => trim((string) (($it['model_sku'] ?? '') !== '' ? $it['model_sku'] : ($it['item_sku'] ?? ''))),
                'name' => trim((string) ($it['item_name'] ?? '') . ((string) ($it['model_name'] ?? '') !== '' ? ' - ' . $it['model_name'] : '')),
                'qty' => max(1, $qty),
                'qtyAssumed' => false,
                'unitPrice' => $price,
                // item_id Shopee → resolusi SKU via product_marketplace_ids (pipeline lama).
                'shopeeId' => (string) ($it['item_id'] ?? '') ?: null,
            ];
        }

        return [
            'externalNo' => (string) ($od['order_sn'] ?? ''),
            'orderDate' => $this->ts($od['create_time'] ?? null),
            'status' => self::STATUS[(string) ($od['order_status'] ?? '')] ?? 'PAID',
            'buyerName' => (string) ($od['buyer_username'] ?? ''),
            'shippingChargedToBuyer' => 0.0,
            'adminFee' => 0.0,
            'shippingCostSeller' => 0.0,
            'voucherSellerBorne' => 0.0,
            'otherIncome' => 0.0,
            'otherCost' => 0.0,
            'productRevenue' => $revenue, // estimasi dari item; escrow menimpa dgn angka final
            'note' => ((string) ($od['note'] ?? '')) ?: null,
            'items' => $items,
            '_hasIncome' => false,
        ];
    }

    /** Cermin mp_income_to_orders(): revenue=harga asli, net=dana cair, residual→otherCost. */
    private function normalizeEscrow(array $esc, array $detail): ?array
    {
        $inc = (array) ($esc['order_income'] ?? []);
        if (! $inc) {
            return null;
        }

        $revenue = (float) ($inc['original_price'] ?? 0);
        $net = (float) ($inc['escrow_amount'] ?? 0);

        $admin = 0.0;
        foreach (self::ESCROW_FEES as $f) {
            $admin += abs((float) ($inc[$f] ?? 0));
        }
        $voucher = 0.0;
        foreach (self::ESCROW_SELLER_DISCOUNTS as $f) {
            $voucher += abs((float) ($inc[$f] ?? 0));
        }
        // Residual: potongan yang tak terdaftar jatuh ke "biaya lain" — identik file income.
        $other = ($revenue - $net) - ($admin + $voucher);

        $refund = abs((float) ($inc['seller_return_refund'] ?? 0));
        $isReturn = $refund != 0.0 || ($revenue <= 0 && $net <= 0);

        return [
            'externalNo' => $detail['externalNo'],
            'orderDate' => $detail['orderDate'],
            'status' => $isReturn ? 'RETURNED' : 'COMPLETED',
            'buyerName' => $detail['buyerName'],
            'shippingChargedToBuyer' => (float) ($inc['buyer_paid_shipping_fee'] ?? 0),
            'adminFee' => $admin,
            'shippingCostSeller' => 0.0,
            'voucherSellerBorne' => $voucher,
            'otherIncome' => 0.0,
            'otherCost' => $other,
            'productRevenue' => $revenue,
            'items' => [], // item dari sumber detail; merge mengisi
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
