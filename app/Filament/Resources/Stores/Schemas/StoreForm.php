<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('marketplace')
                    ->options(['SHOPEE' => 'S h o p e e', 'TOKOPEDIA' => 'T o k o p e d i a', 'TIKTOK' => 'T i k t o k'])
                    ->required(),
                Toggle::make('active')
                    ->required(),
                TextInput::make('note')
                    ->default(null),
            ]);
    }
}
