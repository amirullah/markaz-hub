<?php

namespace App\Http\Controllers;

use App\Services\TokpedTikTok\TokpedTikTokClient;
use App\Services\TokpedTikTok\TokpedTikTokSync;
use Illuminate\Http\Request;

class TokpedTikTokWebhookController extends Controller
{
    public function handle(Request $request, TokpedTikTokClient $client, TokpedTikTokSync $sync)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('x-tts-signature', '');

        if (! $client->verifyPushSignature($rawBody, $signature)) {
            \Log::warning('TikTok push: signature mismatch');
            return response()->json(['code' => 1, 'message' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();
        $shopId = (int) ($data['shop_id'] ?? 0);
        $orderId = (string) ($data['order_id'] ?? $data['data']['order_id'] ?? '');

        if (! $shopId || ! $orderId) {
            return response()->json(['code' => 1, 'message' => 'Missing shop_id/order_id']);
        }

        $conn = \App\Models\MarketplaceConnection::withoutGlobalScopes()
            ->where('platform', 'TIKTOKTOKO')
            ->where('shop_id', $shopId)
            ->first();

        if (! $conn) {
            \Log::warning('TikTok push: unknown shop_id', ['shop_id' => $shopId]);
            return response()->json(['code' => 1, 'message' => 'Unknown shop']);
        }

        try {
            $detail = $client->orderDetail($conn, $orderId);
            $orders = [$detail['order'] ?? $detail];
            $sync->syncOrders($conn, $orders);

            return response()->json(['code' => 0, 'message' => 'OK']);
        } catch (\Throwable $e) {
            \Log::error('TikTok push handler error: ' . $e->getMessage());
            return response()->json(['code' => 1, 'message' => 'Internal error'], 500);
        }
    }
}