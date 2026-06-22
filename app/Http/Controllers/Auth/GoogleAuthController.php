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

        $user = User::where('google_id', $g->getId())
            ->orWhere('email', $g->getEmail())
            ->first();

        if (! $user) {
            // Seller baru: buat organisasi (tenant) + user.
            $org = Organization::create([
                'name' => $g->getName() ?: 'Toko Saya',
                'active' => true,
            ]);
            $user = User::create([
                'organization_id' => $org->id,
                'name' => $g->getName() ?: 'Pengguna',
                'email' => $g->getEmail(),
                'google_id' => $g->getId(),
                'avatar' => $g->getAvatar(),
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $g->getId(),
                'avatar' => $g->getAvatar(),
            ])->save();
            if (! $user->organization_id) {
                $org = Organization::create(['name' => $user->name . ' — Toko', 'active' => true]);
                $user->update(['organization_id' => $org->id]);
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }
}
