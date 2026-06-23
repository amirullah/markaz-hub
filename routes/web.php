<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Sudah login → langsung ke aplikasi; selain itu tampilkan landing page.
    return auth()->check() ? redirect('/admin') : view('landing');
});

// Login dengan Google (Gmail)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
