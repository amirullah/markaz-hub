<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceConnection;
use App\Models\Store;
use App\Services\TokpedTikTok\TokpedTikTokClient;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TokpedTikTokAuthController extends Controller
{
    public function connect(Store $store, TokpedTikTokClient $client): RedirectResponse
    {
        if (! $client->configured()) {
            Notification::make()->title('TikTok Shop API belum dikonfigurasi')
                ->body('Isi TIKTOK_APP_KEY & TIKTOK_APP_SECRET di .env terlebih dahulu (dari console partner.tiktokshop.com).')
                ->danger()->send();

            return redirect()->route('filament.admin.resources.stores.index');
        }

        return redirect()->away($client->authUrl(route('tokpedtiktok.callback', ['store' => $store->id])));
    }

    public function callback(Request $request, Store $store, TokpedTikTokClient $client): RedirectResponse
    {
        $code = (string) $request->query('code', '');

        try {
            if ($code === '') {
                throw new \RuntimeException('Otorisasi dibatalkan atau tidak lengkap (code kosong).');
            }

            $res = $client->tokenFromCode($code);
            if (empty($res['access_token'])) {
                throw new \RuntimeException('TikTok Shop tidak mengembalikan access_token.');
            }

            $conn = MarketplaceConnection::updateOrCreate(
                ['store_id' => $store->id, 'platform' => 'TIKTOKTOKO'],
                [
                    'shop_id' => (int) ($res['shop_id'] ?? 0),
                    'access_token' => $res['access_token'],
                    'refresh_token' => $res['refresh_token'] ?? null,
                    'access_expires_at' => now()->addSeconds((int) ($res['expire_in'] ?? 604800) - 60),
                    'refresh_token_expires_at' => isset($res['refresh_token_expires_in'])
                        ? now()->addSeconds((int) $res['refresh_token_expires_in'])
                        : null,
                    'authorized_at' => now(),
                    'status' => 'CONNECTED',
                    'last_error' => null,
                ],
            );

            // Sync authorized shop info — ambil shop_cipher jika ada
            try {
                $shops = $client->authorizedShops($conn);
                $shopList = $shops['shops'] ?? $shops['list'] ?? [];
                if (! empty($shopList)) {
                    $shop = $shopList[0];
                    $conn->forceFill([
                        'shop_id' => (int) ($shop['shop_id'] ?? $conn->shop_id),
                        'shop_cipher' => (string) ($shop['shop_cipher'] ?? ''),
                    ])->save();
                }
            } catch (\Throwable) {
            }

            Notification::make()->title("Toko \"{$store->name}\" terhubung ke Tokopedia/TikTok")
                ->body('Silakan jalankan "Sinkron Tokopedia/TikTok" untuk menarik data, atau tunggu push realtime.')
                ->success()->send();
        } catch (\Throwable $e) {
            report($e);
            MarketplaceConnection::updateOrCreate(
                ['store_id' => $store->id, 'platform' => 'TIKTOKTOKO'],
                ['status' => 'ERROR', 'last_error' => mb_substr($e->getMessage(), 0, 500)],
            );
            Notification::make()->title('Gagal menghubungkan Tokopedia/TikTok')
                ->body($e->getMessage())->danger()->send();
        }

        return redirect()->route('filament.admin.resources.stores.index');
    }
}