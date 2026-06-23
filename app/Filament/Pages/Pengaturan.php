<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
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
        return [
            'org' => Organization::find(auth()->user()->organization_id),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ubah')
                ->label('Ubah Pengaturan')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->fillForm(fn (): array => [
                    'uses_dropship' => (bool) (Organization::find(auth()->user()->organization_id)?->uses_dropship ?? true),
                ])
                ->schema([
                    Toggle::make('uses_dropship')
                        ->label('Saya berjualan dropship')
                        ->helperText('Aktif jika sebagian/semua pesanan Anda dropship (dari sumber mana pun — supplier/perusahaan lain, atau manual dari seller lain). Aktif: kolom & biaya dropship + pemenuhan tampil. Nonaktif: tampilan dropship disembunyikan dan laba dihitung sebagai packing sendiri.'),
                ])
                ->action(function (array $data): void {
                    $org = Organization::find(auth()->user()->organization_id);
                    $org->uses_dropship = (bool) ($data['uses_dropship'] ?? false);
                    $org->save();

                    Notification::make()
                        ->title('Pengaturan disimpan')
                        ->body('Muat ulang halaman lain agar perubahan tampilan diterapkan.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
