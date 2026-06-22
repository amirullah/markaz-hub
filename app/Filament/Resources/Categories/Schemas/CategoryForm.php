<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kategori')
                    ->placeholder('mis. Fashion, Elektronik, FMCG')
                    ->required()
                    ->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('organization_id', auth()->user()->organization_id),
                    )
                    ->validationMessages(['unique' => 'Kategori dengan nama ini sudah ada.'])
                    ->columnSpanFull(),
                TextInput::make('fee_shopee')
                    ->label('% Biaya Admin Shopee')
                    ->helperText('Persentase biaya admin Shopee untuk kategori ini (lihat dokumentasi resmi Shopee).')
                    ->required()->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                TextInput::make('fee_tokotiktok')
                    ->label('% Biaya Admin Tokopedia/TikTok')
                    ->helperText('Persentase biaya admin Tokopedia/TikTok (komisinya sama untuk kedua platform).')
                    ->required()->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
            ]);
    }
}
