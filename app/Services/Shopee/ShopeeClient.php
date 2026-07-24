<?php

namespace App\Services\Shopee;

use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;

/**
 * Klien Shopee Open Platform v2 (open.shopee.com).
 *
 * Aturan tanda tangan (HMAC-SHA256, hex, kunci = partner_key):
 *  - Public API  (auth/token)   : sign = HMAC(partner_id . path . timestamp)
 *  - Shop API    (order/escrow) : sign = HMAC(partner_id . path . timestamp . access_token . shop_id)
 * Timestamp = epoch detik; tiap request memuat partner_id, timestamp, sign di query string.
 *
 * Token: access_token ±4 jam; refresh_token ±30 hari dan DIGANTI setiap refresh —
 * simpan keduanya setiap kali refresh (lihat withFreshToken()).
 */
class ShopeeClient
{
    private const BASE = [
        'live' => 'https://partner.shopeemobile.com',
        'test' => 'https://partner.test-stable.shopeemobile.com',
    ];

    public function configured(): bool
    {
        return (string) config('services.shopee.partner_id') !== ''
            && (string) config('services.shopee.partner_key') !== '';
    }

    public function baseUrl(): string
    {
        $env = config('services.shopee.env', 'test');

        return self::BASE[$env === 'live' ? 'live' : 'test'];
    }

    public function partnerId(): int
    {
        return (int) config('services.shopee.partner_id');
    }

    private function sign(string $path, int $timestamp, string $accessToken = '', string $shopId = ''): string
    {
        $base = $this->partnerId() . $path . $timestamp . $accessToken . $shopId;

        return hash_hmac('sha256', $base, (string) config('services.shopee.partner_key'));
    }

    // =========================================================================
    // OTORISASI TOKO (OAuth)
    // =========================================================================

    /** URL halaman izin resmi Shopee — seller login DI SHOPEE (kita tak pernah pegang password). */
    public function authUrl(string $redirect): string
    {
        $path = '/api/v2/shop/auth_partner';
        $ts = time();

        return $this->baseUrl() . $path . '?' . http_build_query([
            'partner_id' => $this->partnerId(),
            'timestamp' => $ts,
            'sign' => $this->sign($path, $ts),
            'redirect' => $redirect,
        ]);
    }

    /** Tukar code (dari callback) → access_token + refresh_token. */
    public function tokenFromCode(string $code, int $shopId): array
    {
        return $this->publicPost('/api/v2/auth/token/get', [
            'code' => $code,
            'shop_id' => $shopId,
            'partner_id' => $this->partnerId(),
        ]);
    }

    /** Refresh access_token (refresh_token ikut BERGANTI — simpan keduanya). */
    public function refreshToken(string $refreshToken, int $shopId): array
    {
        return $this->publicPost('/api/v2/auth/access_token/get', [
            'refresh_token' => $refreshToken,
            'shop_id' => $shopId,
            'partner_id' => $this->partnerId(),
        ]);
    }

    private function publicPost(string $path, array $json): array
    {
        $ts = time();
        $url = $this->baseUrl() . $path . '?' . http_build_query([
            'partner_id' => $this->partnerId(),
            'timestamp' => $ts,
            'sign' => $this->sign($path, $ts),
        ]);

        return $this->decode(Http::timeout(30)->asJson()->post($url, $json)->json(), $path);
    }

    // =========================================================================
    // PANGGILAN SHOP API (dengan refresh token otomatis)
    // =========================================================================

    /** Pastikan token segar; refresh + SIMPAN bila hampir kedaluwarsa. */
    public function withFreshToken(MarketplaceConnection $c): MarketplaceConnection
    {
        if (! $c->tokenStale()) {
            return $c;
        }
        try {
            $res = $this->refreshToken((string) $c->refresh_token, (int) $c->shop_id);
        } catch (ShopeeApiException $e) {
            $c->forceFill([
                'status' => 'ERROR',
                'last_error' => 'Refresh token gagal: ' . $e->getMessage(),
            ])->save();
            throw $e;
        }
        $c->forceFill([
            'access_token' => $res['access_token'],
            // refresh_token BERGANTI tiap refresh — wajib simpan yang baru.
            'refresh_token' => $res['refresh_token'] ?? $c->refresh_token,
            'access_expires_at' => now()->addSeconds((int) ($res['expire_in'] ?? 14400) - 60),
            'status' => 'CONNECTED',
            'last_error' => null,
        ])->save();

        return $c;
    }

    /** GET/POST shop API. $query utk GET-params, $json utk body POST (null = GET). */
    public function shopCall(MarketplaceConnection $c, string $path, array $query = [], ?array $json = null): array
    {
        $c = $this->withFreshToken($c);
        $ts = time();
        $qs = http_build_query(array_merge([
            'partner_id' => $this->partnerId(),
            'timestamp' => $ts,
            'access_token' => (string) $c->access_token,
            'shop_id' => (int) $c->shop_id,
            'sign' => $this->sign($path, $ts, (string) $c->access_token, (string) $c->shop_id),
        ], $query));
        $url = $this->baseUrl() . $path . '?' . $qs;

        $resp = $json === null
            ? Http::timeout(45)->get($url)
            : Http::timeout(45)->asJson()->post($url, $json);

        return $this->decode($resp->json(), $path);
    }

    private function decode(?array $body, string $path): array
    {
        if (! is_array($body)) {
            throw new ShopeeApiException('empty_response', "Respons kosong/bukan JSON dari {$path}.");
        }
        if (($body['error'] ?? '') !== '') {
            throw new ShopeeApiException((string) $body['error'], (string) ($body['message'] ?? 'tanpa pesan'), $body['request_id'] ?? null);
        }

        return $body['response'] ?? $body;
    }

    // =========================================================================
    // ENDPOINT DATA (dipakai ShopeeSync)
    // =========================================================================

    /** Daftar order_sn dlm rentang waktu (cursor; Shopee membatasi rentang ±15 hari per panggilan). */
    public function orderList(MarketplaceConnection $c, int $timeFrom, int $timeTo, string $cursor = '', string $timeField = 'update_time'): array
    {
        return $this->shopCall($c, '/api/v2/order/get_order_list', [
            'time_range_field' => $timeField,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'page_size' => 100,
            'cursor' => $cursor,
            'response_optional_fields' => 'order_status',
        ]);
    }

    /** Detail pesanan (≤50 order_sn per panggilan) — item, SKU, pembeli, nilai. */
    public function orderDetail(MarketplaceConnection $c, array $orderSns): array
    {
        return $this->shopCall($c, '/api/v2/order/get_order_detail', [
            'order_sn_list' => implode(',', array_slice($orderSns, 0, 50)),
            'response_optional_fields' => implode(',', [
                'buyer_username', 'item_list', 'total_amount', 'order_status', 'create_time',
                'update_time', 'pay_time', 'actual_shipping_fee', 'payment_method', 'note',
            ]),
        ]);
    }

    /** Rincian settlement per pesanan (komisi, biaya layanan, dana cair) = pengganti Laporan Penghasilan. */
    public function escrowDetail(MarketplaceConnection $c, string $orderSn): array
    {
        return $this->shopCall($c, '/api/v2/payment/get_escrow_detail', ['order_sn' => $orderSn]);
    }

    /** Daftar item katalog (cursor offset). */
    public function itemList(MarketplaceConnection $c, int $offset = 0, int $pageSize = 100): array
    {
        return $this->shopCall($c, '/api/v2/product/get_item_list', [
            'offset' => $offset,
            'page_size' => $pageSize,
            'item_status' => 'NORMAL',
        ]);
    }

    /** Info dasar item (nama, item_sku) ≤50 id. */
    public function itemBaseInfo(MarketplaceConnection $c, array $itemIds): array
    {
        return $this->shopCall($c, '/api/v2/product/get_item_base_info', [
            'item_id_list' => implode(',', array_slice($itemIds, 0, 50)),
        ]);
    }

    /** Varian/model item (model_sku per varian). */
    public function modelList(MarketplaceConnection $c, int $itemId): array
    {
        return $this->shopCall($c, '/api/v2/product/get_model_list', ['item_id' => $itemId]);
    }

    // =========================================================================
    // PUSH / WEBHOOK
    // =========================================================================

    /**
     * Verifikasi signature push Shopee: header Authorization = HMAC-SHA256(url . '|' . raw_body).
     * Return false bila tak cocok — JANGAN proses body-nya.
     */
    public function verifyPushSignature(string $url, string $rawBody, ?string $authorization): bool
    {
        if (! $authorization) {
            return false;
        }
        $expected = hash_hmac('sha256', $url . '|' . $rawBody, (string) config('services.shopee.partner_key'));

        return hash_equals($expected, trim($authorization));
    }
}
