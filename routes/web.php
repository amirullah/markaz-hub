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
});
