<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * API ber-versi untuk app mobile (Android/iOS). Auth via Sanctum (token).
 * Logika tetap di app/Services — controller/endpoint hanya lapisan tipis.
 */
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Cek kesehatan (publik)
    Route::get('/health', fn () => [
        'ok' => true,
        'app' => 'MarkazHub API',
        'version' => 'v1',
    ])->name('health');

    // Butuh token Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', fn (Request $r) => $r->user()->only(['id', 'name', 'email', 'organization_id']))->name('me');
    });
});
