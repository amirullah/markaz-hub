<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ShopeeAuthController;
use App\Http\Controllers\TokpedTikTokAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Sudah login → langsung ke aplikasi; selain itu tampilkan landing page.
    return auth()->check() ? redirect('/admin') : view('landing');
});

// Login dengan Google (Gmail)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

// Hubungkan toko ke Shopee (OAuth Open Platform) — wajib login; Store ter-scope org.
Route::middleware('auth')->group(function () {
    Route::get('/shopee/connect/{store}', [ShopeeAuthController::class, 'connect'])->name('shopee.connect');
    Route::get('/shopee/callback/{store}', [ShopeeAuthController::class, 'callback'])->name('shopee.callback');

    // Hubungkan toko ke Tokopedia/TikTok (OAuth TikTok Shop)
    Route::get('/tokpedtiktok/connect/{store}', [TokpedTikTokAuthController::class, 'connect'])->name('tokpedtiktok.connect');
    Route::get('/tokpedtiktok/callback/{store}', [TokpedTikTokAuthController::class, 'callback'])->name('tokpedtiktok.callback');

    // Cetak packing slip (per-order)
    Route::get('/print/packing-slip/{order}', function (\App\Models\Order $order) {
        abort_if((int) $order->organization_id !== (int) auth()->user()->organization_id, 403);
        return view('print.packing-slip', compact('order'));
    })->name('print.packing-slip');

    // Cetak invoice (per-order)
    Route::get('/print/invoice/{order}', function (\App\Models\Order $order) {
        abort_if((int) $order->organization_id !== (int) auth()->user()->organization_id, 403);
        return view('print.invoice', compact('order'));
    })->name('print.invoice');

    // Cetak invoice batch
    Route::get('/print/invoice/batch', function (\Illuminate\Http\Request $request) {
        $ids = collect(explode(',', $request->str('ids', '')))->filter()->values();
        if ($ids->isEmpty()) {
            abort(400, 'Tidak ada pesanan dipilih.');
        }
        $orders = \App\Models\Order::whereIn('id', $ids)
            ->where('organization_id', auth()->user()->organization_id)
            ->with('items', 'store')
            ->get();
        if ($orders->isEmpty()) {
            abort(404);
        }
        return view('print.invoice-batch', compact('orders'));
    })->name('print.invoice.batch');

    // Cetak packing slip batch (dari halaman Pesanan → Cetak Packing Slip)
    Route::get('/print/packing-slip/batch', function (\Illuminate\Http\Request $request) {
        $ids = collect(explode(',', $request->str('ids', '')))->filter()->values();
        if ($ids->isEmpty()) {
            abort(400, 'Tidak ada pesanan dipilih.');
        }
        $orders = \App\Models\Order::whereIn('id', $ids)
            ->where('organization_id', auth()->user()->organization_id)
            ->with('items', 'store')
            ->get();
        if ($orders->isEmpty()) {
            abort(404);
        }
        return view('print.packing-slip-batch', compact('orders'));
    })->name('print.packing-slip.batch');
});
