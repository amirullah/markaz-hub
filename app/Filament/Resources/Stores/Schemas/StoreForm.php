<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        // organization_id TIDAK ditampilkan: otomatis di-set ke organisasi user
        // (trait BelongsToOrganization) — mencegah salah assign antar-tenant.
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Toko')
                    ->placeholder('mis. MarkazMall SBY')
                    ->required()
                    ->maxLength(190)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('organization_id', auth()->user()->organization_id)
                            ->where('marketplace', $get('marketplace')),
                    )
                    ->validationMessages(['unique' => 'Toko dengan nama & channel ini sudah ada.']),
                Select::make('marketplace')
                    ->label('Channel')
                    ->options(['SHOPEE' => 'Shopee', 'TIKTOKTOKO' => 'Tokopedia/TikTok'])
                    ->required()
                    ->native(false),
                Toggle::make('active')
                    ->label('Aktif')
                    ->helperText('Nonaktifkan untuk menyembunyikan toko tanpa menghapus data.')
                    ->default(true),
                TextInput::make('note')
                    ->label('Catatan')
                    ->maxLength(255),
            ]);
    }
}
