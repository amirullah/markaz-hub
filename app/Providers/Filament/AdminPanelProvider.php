<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // SPA + prefetch: pindah menu via AJAX (tanpa reload penuh / unduh ulang aset);
            // hasPrefetching → halaman di-preload saat kursor mendekati menu, jadi saat
            // diklik terasa instan. Klik menu tak lagi loading beberapa detik.
            ->spa(hasPrefetching: true)
            ->login()
            ->brandName('MarkazHub')
            ->font('Inter')
            ->darkMode(false) // tema biru/clean terang; halaman custom memakai gaya terang
            ->colors([
                'primary' => Color::Blue,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            // Sidebar lebih lega agar label panjang ("Riwayat Perubahan") tak terpotong.
            ->sidebarWidth('16rem')
            // Konten memakai lebar penuh — tabel data & dashboard tak menyisakan celah
            // kiri-kanan di layar lebar. Halaman form kustom tetap dibatasi sendiri (760px).
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('auth.google-button')->render(),
            )
            // Teks select pada filter tabel (mis. daftar Toko + channel) sedikit lebih kecil
            // agar ringkas & muat di kolom yang lebih lebar. Tak perlu rebuild tema.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>.fi-ta-filters .fi-fo-select,.fi-ta-filters .fi-fo-select [role="option"]{font-size:.8rem}</style>',
            )
            // Tombol "Salin No. Pesanan" tepat di KIRI kotak Cari pada daftar Pesanan
            // (memanggil ListOrders::salinNoPesanan). Hook resmi tables::toolbar.search.before.
            ->renderHook(
                \Filament\Tables\View\TablesRenderHook::TOOLBAR_SEARCH_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render(
                    '<x-filament::button wire:click="salinNoPesanan" wire:loading.attr="disabled" icon="heroicon-o-clipboard-document" color="gray" size="sm">Salin No. Pesanan</x-filament::button>'
                ),
                scopes: \App\Filament\Resources\Orders\Pages\ListOrders::class,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
