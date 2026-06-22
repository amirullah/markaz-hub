<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Login dengan Google (Gmail). Login pertama otomatis membuat Organization (tenant)
 * baru untuk seller, lalu masuk ke panel.
 */
class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $g = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            // Catat detail untuk diagnosa; tampilkan pesan ringkas ke user (jangan bocorkan internal).
            \Log::error('Google login gagal: ' . $e->getMessage(), ['type' => get_class($e)]);
            return redirect('/admin/login')->withErrors(['email' => 'Login Google gagal. Silakan coba lagi.']);
        }

        // Hanya percayai email yang SUDAH diverifikasi Google.
        $emailVerified = (bool) ($g->user['email_verified'] ?? $g->user['verified_email'] ?? false);
        if (! $g->getEmail() || ! $emailVerified) {
            return redirect('/admin/login')->withErrors(['email' => 'Email Google Anda belum terverifikasi.']);
        }

        // 1) Login normal: cocokkan HANYA via google_id.
        $user = User::where('google_id', $g->getId())->first();

        if (! $user) {
            // 2) Email sudah dipakai akun lain (mis. akun berpassword) → JANGAN ambil
            //    alih otomatis. Cegah account takeover lewat pencocokan email.
            if (User::where('email', $g->getEmail())->exists()) {
                return redirect('/admin/login')->withErrors([
                    'email' => 'Email ini sudah terdaftar dengan metode lain. Hubungi admin untuk menautkan akun.',
                ]);
            }

            // 3) Seller baru: buat organisasi (tenant) + user.
            $org = Organization::create([
                'name' => $g->getName() ?: 'Toko Saya',
                'active' => true,
            ]);
            $user = (new User)->forceFill([
                'organization_id' => $org->id,
                'name' => $g->getName() ?: 'Pengguna',
                'email' => $g->getEmail(),
                'google_id' => $g->getId(),
                'avatar' => $g->getAvatar(),
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => now(),
            ]);
            $user->save();
        } else {
            // Login berulang: perbarui avatar saja.
            $user->forceFill(['avatar' => $g->getAvatar()])->save();
        }

        // 4) Pastikan organisasi AKTIF sebelum membuat sesi (jangan andalkan panel saja).
        if (! $user->organization_id || ! optional($user->organization)->active) {
            return redirect('/admin/login')->withErrors([
                'email' => 'Akun/organisasi Anda tidak aktif. Hubungi admin.',
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }
}
