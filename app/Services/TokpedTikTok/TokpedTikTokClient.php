<?php

namespace App\Services\TokpedTikTok;

use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Shop Open API v2 client (open-api.tiktokglobalshop.com).
 *
 * Signing: HMAC-SHA256, base string = app_key . path . timestamp . access_token
 * Auth header: x-tts-access-token (bukan query param seperti Shopee).
 * Access token berlaku 7 hari; refresh_token punya expiry sendiri.
 */
class TokpedTikTokClient
{
    private const BASE_LIVE = 'https://open-api.tiktokglobalshop.com';
    private const BASE_SANDBOX = 'https://open-api-sandbox.tiktokglobalshop.com';

    public function configured(): bool
    {
        return (string) config('services.tokpedtiktok.app_key') !== ''
            && (string) config('services.tokpedtiktok.app_secret') !== '';
    }

    public function baseUrl(): string
    {
        return config('services.tokpedtiktok.env', 'sandbox') === 'live'
            ? self::BASE_LIVE
            : self::BASE_SANDBOX;
    }

    public function appKey(): string
    {
        return (string) config('services.tokpedtiktok.app_key');
    }

    public function appSecret(): string
    {
        return (string) config('services.tokpedtiktok.app_secret');
    }

    public function serviceId(): string
    {
        return (string) config('services.tokpedtiktok.service_id');
    }

    private function sign(string $path, int $timestamp, string $accessToken = ''): string
    {
        $base = $this->appKey() . $path . $timestamp . $accessToken;
        return hash_hmac('sha256', $base, $this->appSecret());
    }

    // =========================================================================
    // AUTH LINK (OAuth)
    // =========================================================================

    public function authUrl(string $redirect, string $state = ''): string
    {
        $base = $this->baseUrl();
        $ts = time();
        $sign = $this->sign('/api/token/get', $ts);

        $url = "{$base}/authorization?app_key={$this->appKey()}&timestamp={$ts}&sign={$sign}&redirect={$redirect}";
        if ($state !== '') {
            $url .= "&state=" . urlencode($state);
        }
        return $url;
    }

    // =========================================================================
    // TOKEN
    // =========================================================================

    public function tokenFromCode(string $code): array
    {
        return $this->publicPost('/api/token/get', [
            'app_key' => $this->appKey(),
            'code' => $code,
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->publicPost('/api/token/refresh', [
            'app_key' => $this->appKey(),
            'refresh_token' => $refreshToken,
        ]);
    }

    private function publicPost(string $path, array $json): array
    {
        $ts = time();
        $sign = $this->sign($path, $ts);
        $url = $this->baseUrl() . $path . '?' . http_build_query([
            'app_key' => $this->appKey(),
            'timestamp' => $ts,
            'sign' => $sign,
        ]);

        return $this->decode(Http::timeout(30)->asJson()->post($url, $json)->json(), $path);
    }

    // =========================================================================
    // SHOP API (with access token)
    // =========================================================================

    public function withFreshToken(MarketplaceConnection $c): MarketplaceConnection
    {
        if (! $c->tokenStale()) {
            return $c;
        }
        try {
            $res = $this->refreshToken((string) $c->refresh_token);
        } catch (TokpedTikTokApiException $e) {
            $c->forceFill([
                'status' => 'ERROR',
                'last_error' => 'Refresh token gagal: ' . $e->getMessage(),
            ])->save();
            throw $e;
        }
        $c->forceFill([
            'access_token' => $res['access_token'],
            'refresh_token' => $res['refresh_token'] ?? $c->refresh_token,
            'access_expires_at' => now()->addSeconds((int) ($res['expire_in'] ?? 604800) - 60),
            'refresh_token_expires_at' => isset($res['refresh_token_expires_in'])
                ? now()->addSeconds((int) $res['refresh_token_expires_in'])
                : null,
            'status' => 'CONNECTED',
            'last_error' => null,
        ])->save();

        return $c;
    }

    public function shopCall(MarketplaceConnection $c, string $path, array $params = [], string $method = 'GET'): array
    {
        $c = $this->withFreshToken($c);
        $ts = time();
        $accessToken = (string) $c->access_token;
        $sign = $this->sign($path, $ts, $accessToken);
        $url = $this->baseUrl() . $path . '?' . http_build_query([
            'app_key' => $this->appKey(),
            'timestamp' => $ts,
            'sign' => $sign,
        ]);

        $request = Http::timeout(45)
            ->withHeaders(['x-tts-access-token' => $accessToken]);

        $resp = $method === 'POST'
            ? $request->asJson()->post($url, $params)->json()
            : ($params ? $request->get($url, $params)->json() : $request->get($url)->json());

        return $this->decode($resp, $path);
    }

    private function decode(?array $body, string $path): array
    {
        if (! is_array($body)) {
            throw new TokpedTikTokApiException('empty_response', "Respons kosong/bukan JSON dari {$path}.");
        }
        $code = $body['code'] ?? 0;
        if ($code !== 0) {
            $msg = (string) ($body['message'] ?? $body['msg'] ?? 'tanpa pesan');
            throw new TokpedTikTokApiException((string) $code, $msg, $body['request_id'] ?? null);
        }
        return $body['data'] ?? $body;
    }

    // =========================================================================
    // PUSH / WEBHOOK
    // =========================================================================

    /**
     * Verifikasi signature push TikTok Shop: header x-tts-signature = HMAC-SHA256(raw_body).
     */
    public function verifyPushSignature(string $rawBody, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $this->appSecret());
        return hash_equals($expected, trim($signature));
    }

    // =========================================================================
    // ENDPOINT DATA
    // =========================================================================

    public function orderList(MarketplaceConnection $c, int $createTimeFrom, int $createTimeTo, int $pageSize = 100, int $page = 1): array
    {
        return $this->shopCall($c, '/order/202309/orders', [
            'create_time_from' => $createTimeFrom,
            'create_time_to' => $createTimeTo,
            'page_size' => min($pageSize, 100),
            'page_number' => $page,
        ]);
    }

    public function orderDetail(MarketplaceConnection $c, string $orderId): array
    {
        return $this->shopCall($c, "/order/202309/orders/{$orderId}");
    }

    public function settlementDetail(MarketplaceConnection $c, string $orderId): array
    {
        return $this->shopCall($c, '/finance/202309/settlements', ['order_id' => $orderId]);
    }

    public function productList(MarketplaceConnection $c, int $page = 1, int $pageSize = 100): array
    {
        return $this->shopCall($c, '/product/202309/products', [
            'page_number' => $page,
            'page_size' => min($pageSize, 100),
        ]);
    }

    public function productDetail(MarketplaceConnection $c, string $productId): array
    {
        return $this->shopCall($c, "/product/202309/products/{$productId}");
    }

    public function authorizedShops(MarketplaceConnection $c): array
    {
        return $this->shopCall($c, '/authorization/202309/shops');
    }
}