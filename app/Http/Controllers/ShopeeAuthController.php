<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceConnection;
use App\Models\Store;
use App\Services\Shopee\ShopeeClient;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Alur hubungkan toko Shopee (OAuth Shopee Open Platform):
 * connect  → arahkan seller ke halaman izin RESMI Shopee (login di Shopee, bukan di kita),
 * callback → Shopee kembali membawa ?code=...&shop_id=... → tukar jadi token → simpan.
 * Kedua route ber-middleware auth; Store terlindung global scope org (toko org lain = 404).
 */
class ShopeeAuthController extends Controller
{
    public function connect(Store $store, ShopeeClient $shopee): RedirectResponse
    {
        if (! $shopee->configured()) {
            Notification::make()->title('Shopee API belum dikonfigurasi')
                ->body('Isi SHOPEE_PARTNER_ID & SHOPEE_PARTNER_KEY di .env terlebih dahulu (dari console open.shopee.com).')
                ->danger()->send();

            return redirect()->route('filament.admin.resources.stores.index');
        }

        // Redirect balik memuat id toko agar callback tahu koneksi ini milik toko mana.
        return redirect()->away($shopee->authUrl(route('shopee.callback', ['store' => $store->id])));
    }

    public function callback(Request $request, Store $store, ShopeeClient $shopee): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        $shopId = (int) $request->query('shop_id', 0);

        try {
            if ($code === '' || $shopId === 0) {
                throw new \RuntimeException('Otorisasi dibatalkan atau tidak lengkap (code/shop_id kosong).');
            }

            $res = $shopee->tokenFromCode($code, $shopId);
            if (empty($res['access_token'])) {
                throw new \RuntimeException('Shopee tidak mengembalikan access_token.');
            }

            MarketplaceConnection::updateOrCreate(
                ['store_id' => $store->id, 'platform' => 'SHOPEE'],
                [
                    'shop_id' => $shopId,
                    'access_token' => $res['access_token'],
                    'refresh_token' => $res['refresh_token'] ?? null,
                    'access_expires_at' => now()->addSeconds((int) ($res['expire_in'] ?? 14400) - 60),
                    'authorized_at' => now(),
                    'status' => 'CONNECTED',
                    'last_error' => null,
                ],
            );

            Notification::make()->title("Toko \"{$store->name}\" terhubung ke Shopee ✅")
                ->body('Shop ID: ' . $shopId . '. Silakan jalankan "Sinkron Shopee" untuk menarik data, atau tunggu push realtime.')
                ->success()->send();
        } catch (\Throwable $e) {
            report($e);
            MarketplaceConnection::updateOrCreate(
                ['store_id' => $store->id, 'platform' => 'SHOPEE'],
                ['status' => 'ERROR', 'last_error' => mb_substr($e->getMessage(), 0, 500)],
            );
            Notification::make()->title('Gagal menghubungkan Shopee')
                ->body($e->getMessage())->danger()->send();
        }

        return redirect()->route('filament.admin.resources.stores.index');
    }
}
