<?php

namespace App\Services;

use App\Models\MarketplaceConnection;
use App\Models\Order;
use App\Services\Shopee\ShopeeApiException;
use App\Services\Shopee\ShopeeClient;
use App\Services\TokpedTikTok\TokpedTikTokApiException;
use App\Services\TokpedTikTok\TokpedTikTokClient;

/**
 * Mengirim nomor resi ke API marketplace saat pesanan ditandai Dikirim.
 * Mendukung Shopee (ship_order) dan TikTok Shop (fulfillment/ship).
 */
class ShippingService
{
    public function __construct(
        private readonly ShopeeClient $shopee,
        private readonly TokpedTikTokClient $tikTok,
    ) {}

    /**
     * Kirim resi ke marketplace untuk satu pesanan.
     *
     * @return array{success: bool, message: string}
     */
    public function ship(Order $order): array
    {
        if (! $order->tracking_number) {
            return ['success' => false, 'message' => 'Nomor resi kosong.'];
        }

        $connection = $this->getConnection($order);
        if (! $connection) {
            return ['success' => false, 'message' => 'Toko tidak terhubung ke marketplace.'];
        }

        return match ($order->marketplace) {
            'SHOPEE' => $this->shipShopee($connection, $order),
            'TIKTOK', 'TIKTOKTOKO' => $this->shipTikTok($connection, $order),
            default => ['success' => false, 'message' => 'Marketplace "' . $order->marketplace . '" belum didukung.']
        };
    }

    private function getConnection(Order $order): ?MarketplaceConnection
    {
        $store = $order->store;
        if (! $store) {
            return null;
        }

        return match ($order->marketplace) {
            'SHOPEE' => $store->shopeeConnection,
            'TIKTOK', 'TIKTOKTOKO' => $store->tikTokConnection,
            default => null,
        };
    }

    private function shipShopee(MarketplaceConnection $c, Order $order): array
    {
        if (! $this->shopee->configured()) {
            return ['success' => false, 'message' => 'Kredensial Shopee belum dikonfigurasi (SHOPEE_PARTNER_ID/SHOPEE_PARTNER_KEY).'];
        }
        if (! $c->isConnected()) {
            return ['success' => false, 'message' => 'Toko Shopee belum terhubung.'];
        }
        try {
            $this->shopee->shipOrder($c, $order->external_no, $order->tracking_number);
            return ['success' => true, 'message' => 'Resi berhasil dikirim ke Shopee.'];
        } catch (ShopeeApiException $e) {
            return ['success' => false, 'message' => 'Shopee: ' . $e->getMessage()];
        }
    }

    private function shipTikTok(MarketplaceConnection $c, Order $order): array
    {
        if (! $this->tikTok->configured()) {
            return ['success' => false, 'message' => 'Kredensial TikTok Shop belum dikonfigurasi (TIKTOK_APP_KEY/TIKTOK_APP_SECRET).'];
        }
        if (! $c->isConnected()) {
            return ['success' => false, 'message' => 'Toko TikTok belum terhubung.'];
        }
        $carrierCode = $this->resolveCarrierCode($order->courier);
        try {
            $this->tikTok->shipOrder($c, $order->external_no, $order->tracking_number, $carrierCode);
            return ['success' => true, 'message' => 'Resi berhasil dikirim ke TikTok Shop.'];
        } catch (TokpedTikTokApiException $e) {
            return ['success' => false, 'message' => 'TikTok: ' . $e->getMessage()];
        }
    }

    /**
     * Dapatkan URL label pengiriman untuk satu pesanan.
     *
     * @return array{success: bool, url: ?string, message: string}
     */
    public function getShippingLabel(Order $order): array
    {
        $connection = $this->getConnection($order);
        if (! $connection) {
            return ['success' => false, 'url' => null, 'message' => 'Toko tidak terhubung ke marketplace.'];
        }

        return match ($order->marketplace) {
            'SHOPEE' => $this->getShopeeLabel($connection, $order),
            'TIKTOK', 'TIKTOKTOKO' => $this->getTikTokLabel($connection, $order),
            default => ['success' => false, 'url' => null, 'message' => 'Marketplace "' . $order->marketplace . '" belum didukung.']
        };
    }

    private function getShopeeLabel(MarketplaceConnection $c, Order $order): array
    {
        if (! $this->shopee->configured()) {
            return ['success' => false, 'url' => null, 'message' => 'Kredensial Shopee belum dikonfigurasi.'];
        }
        if (! $c->isConnected()) {
            return ['success' => false, 'url' => null, 'message' => 'Toko Shopee belum terhubung.'];
        }
        try {
            $res = $this->shopee->getShippingDocument($c, $order->external_no);
            $url = $res['document']['url'] ?? $res['document_url'] ?? $res['url'] ?? null;
            if (! $url) {
                return ['success' => false, 'url' => null, 'message' => 'URL label tidak ditemukan di respons Shopee.'];
            }
            return ['success' => true, 'url' => $url, 'message' => 'Label siap dicetak.'];
        } catch (ShopeeApiException $e) {
            return ['success' => false, 'url' => null, 'message' => 'Shopee: ' . $e->getMessage()];
        }
    }

    private function getTikTokLabel(MarketplaceConnection $c, Order $order): array
    {
        if (! $this->tikTok->configured()) {
            return ['success' => false, 'url' => null, 'message' => 'Kredensial TikTok belum dikonfigurasi.'];
        }
        if (! $c->isConnected()) {
            return ['success' => false, 'url' => null, 'message' => 'Toko TikTok belum terhubung.'];
        }
        try {
            $res = $this->tikTok->getShippingDocument($c, $order->external_no);
            $url = $res['document_url'] ?? $res['url'] ?? $res['shipping_document']['url'] ?? null;
            if (! $url) {
                return ['success' => false, 'url' => null, 'message' => 'URL label tidak ditemukan di respons TikTok.'];
            }
            return ['success' => true, 'url' => $url, 'message' => 'Label siap dicetak.'];
        } catch (TokpedTikTokApiException $e) {
            return ['success' => false, 'url' => null, 'message' => 'TikTok: ' . $e->getMessage()];
        }
    }

    /** Map nama ekspedisi umum ke kode kurir TikTok. */
    private function resolveCarrierCode(?string $courier): string
    {
        $map = [
            'jne' => 'JNE',
            'j&t' => 'J&T',
            'jt' => 'J&T',
            'sicepat' => 'SICEPAT',
            'ninja' => 'NINJA',
            'grab' => 'GRAB',
            'gosend' => 'GOSEND',
            'pos' => 'POS',
            'tiki' => 'TIKI',
            'wahana' => 'WAHANA',
            'lion' => 'LION',
            'ide' => 'IDE',
            'rex' => 'REX',
            'anteraja' => 'ANTERAJA',
            'spx' => 'SPX',
        ];
        $key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $courier));
        return $map[$key] ?? strtoupper((string) $courier);
    }
}
