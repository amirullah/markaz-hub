<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('cost_price')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('dropship_cost')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->default(null),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
