<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceConnection;
use App\Services\Shopee\ShopeeClient;
use App\Services\Shopee\ShopeeSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Penerima push realtime Shopee (dikonfigurasi di console open.shopee.com →
 * App → Push Mechanism, URL: https://markazhub.mkz.my.id/api/shopee/push).
 *
 * Shopee menandatangani tiap push: Authorization = HMAC-SHA256(url . '|' . raw_body).
 * Push tak bertanda tangan sah → 401 (jangan pernah proses).
 * Balas CEPAT (Shopee menganggap lambat = gagal & retry); kerja berat minimal.
 */
class ShopeeWebhookController extends Controller
{
    public function handle(Request $request, ShopeeClient $shopee, ShopeeSync $sync): JsonResponse
    {
        $raw = $request->getContent();

        if (! $shopee->verifyPushSignature($request->fullUrl(), $raw, $request->header('Authorization'))) {
            Log::warning('Shopee push: signature tidak cocok', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $payload = json_decode($raw, true) ?: [];
        $code = (int) ($payload['code'] ?? 0);
        $shopId = (int) ($payload['shop_id'] ?? 0);
        $data = (array) ($payload['data'] ?? []);
        // Nama field nomor pesanan bervariasi antar tipe push.
        $orderSn = (string) ($data['ordersn'] ?? $data['order_sn'] ?? '');

        Log::info('Shopee push diterima', ['code' => $code, 'shop_id' => $shopId, 'order_sn' => $orderSn]);

        // Push uji/verifikasi dari console (tanpa shop) → cukup 200.
        if ($shopId === 0) {
            return response()->json(['ok' => true]);
        }

        $conn = MarketplaceConnection::withoutGlobalScopes()
            ->where('platform', 'SHOPEE')->where('shop_id', $shopId)
            ->where('status', 'CONNECTED')->first();

        if (! $conn) {
            Log::warning('Shopee push: shop_id tak dikenal/terputus', ['shop_id' => $shopId]);

            return response()->json(['ok' => true]); // tetap 200 agar Shopee tak retry membabi buta
        }

        if ($orderSn !== '') {
            try {
                // Tarik detail (+escrow bila sudah selesai) untuk 1 pesanan ini → realtime.
                $sync->syncOrderSns($conn, [$orderSn]);
            } catch (\Throwable $e) {
                report($e); // jangan gagalkan respons — reconciliation terjadwal akan menyusul
            }
        }

        return response()->json(['ok' => true]);
    }
}
