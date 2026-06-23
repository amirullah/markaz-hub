<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use App\Services\AdminFeeEstimator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Pengaturan extends Page
{
    protected string $view = 'filament.pages.pengaturan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan Organisasi';

    protected static ?int $navigationSort = 8;

    public function getViewData(): array
    {
        $orgId = (int) auth()->user()->organization_id;

        return [
            'org' => Organization::find($orgId),
            'avgShopee' => round((float) \App\Models\Category::withoutGlobalScopes()->where('organization_id', $orgId)->avg('fee_shopee'), 2),
            'avgToko' => round((float) \App\Models\Category::withoutGlobalScopes()->where('organization_id', $orgId)->avg('fee_tokotiktok'), 2),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kalibrasi')
                ->label('Kalibrasi Tarif dari Laporan')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Kalibrasi tarif biaya dari Laporan Penghasilan')
                ->modalDescription('Menghitung tarif biaya EFEKTIF dari pesanan Anda yang SUDAH punya Laporan Penghasilan (biaya asli), lalu memakainya untuk mengestimasi pesanan yang belum ada laporannya. Ini PALING AKURAT karena memakai data toko Anda sendiri (tarif per kategori = komisi + biaya layanan + komisi dinamis, sudah jadi satu). Butuh cukup banyak pesanan ber-Laporan Penghasilan.')
                ->modalSubmitActionLabel('Kalibrasi sekarang')
                ->action(function (): void {
                    $res = app(AdminFeeEstimator::class)->calibrateFromIncome((int) auth()->user()->organization_id);
                    if (($res['shopee_avg'] ?? 0) <= 0 && ($res['tokotiktok_avg'] ?? 0) <= 0) {
                        Notification::make()
                            ->title('Belum bisa dikalibrasi')
                            ->body('Belum ada cukup pesanan ber-Laporan Penghasilan untuk menghitung tarif. Impor Laporan Penghasilan dulu, lalu coba lagi.')
                            ->warning()->send();
                        return;
                    }
                    \App\Support\Bell::send(Notification::make()
                        ->title('Tarif berhasil dikalibrasi dari data Anda')
                        ->body("Biaya efektif rata-rata: Shopee {$res['shopee_avg']}% · Tokopedia/TikTok {$res['tokotiktok_avg']}% (+ Rp1.250/pesanan). "
                            . "{$res['from_data']} kategori memakai tarif spesifik dari data, sisanya rata-rata channel. Estimasi {$res['reestimated']} pesanan diperbarui.")
                        ->success());
                }),

            Action::make('ubah')
                ->label('Ubah Pengaturan')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalWidth('lg')
                ->fillForm(function (): array {
                    $org = Organization::find(auth()->user()->organization_id);

                    return [
                        'uses_dropship' => (bool) ($org?->uses_dropship ?? true),
                        'fee_shopee_service_pct' => (float) ($org?->fee_shopee_service_pct ?? 10),
                        'fee_shopee_service_cap' => (int) ($org?->fee_shopee_service_cap ?? 10000),
                        'fee_tokotiktok_dynamic_pct' => (float) ($org?->fee_tokotiktok_dynamic_pct ?? 6.5),
                    ];
                })
                ->schema([
                    Toggle::make('uses_dropship')
                        ->label('Saya berjualan dropship')
                        ->helperText('Aktif jika sebagian/semua pesanan Anda dropship (dari sumber mana pun). Nonaktif: tampilan dropship disembunyikan & laba dihitung sebagai packing sendiri.'),
                    TextInput::make('fee_shopee_service_pct')
                        ->label('Shopee — Biaya Layanan (%)')
                        ->numeric()->minValue(0)->maxValue(100)->step('0.01')->suffix('%')->required()
                        ->helperText('Biaya program (mis. Gratis Ongkir XTRA). Dari struktur Shopee ±10%. Isi 0 jika tidak ikut program.'),
                    TextInput::make('fee_shopee_service_cap')
                        ->label('Shopee — Batas Biaya Layanan (Rp)')
                        ->numeric()->minValue(0)->prefix('Rp')->required()
                        ->helperText('Batas maksimum Biaya Layanan per pesanan. Umumnya Rp10.000. Isi 0 jika tanpa batas.'),
                    TextInput::make('fee_tokotiktok_dynamic_pct')
                        ->label('Tokopedia/TikTok — Komisi Dinamis (%)')
                        ->numeric()->minValue(0)->maxValue(100)->step('0.01')->suffix('%')->required()
                        ->helperText('Komisi tambahan di luar komisi kategori. Dari struktur TikTok ±6,5%. Isi 0 jika tidak berlaku.'),
                ])
                ->action(function (array $data): void {
                    $org = Organization::find(auth()->user()->organization_id);
                    $org->uses_dropship = (bool) ($data['uses_dropship'] ?? false);
                    $org->fee_shopee_service_pct = (float) ($data['fee_shopee_service_pct'] ?? 10);
                    $org->fee_shopee_service_cap = (int) ($data['fee_shopee_service_cap'] ?? 10000);
                    $org->fee_tokotiktok_dynamic_pct = (float) ($data['fee_tokotiktok_dynamic_pct'] ?? 6.5);
                    $org->save();

                    // Terapkan tarif baru ke estimasi pesanan yang belum final.
                    $res = app(AdminFeeEstimator::class)->applyToOrg((int) $org->id);

                    \App\Support\Bell::send(Notification::make()
                        ->title('Pengaturan biaya disimpan')
                        ->body("Estimasi biaya diperbarui untuk {$res['updated']} pesanan (total Rp" . number_format($res['total'], 0, ',', '.') . '). Muat ulang halaman lain agar tampilan diperbarui.')
                        ->success());
                }),
        ];
    }
}
