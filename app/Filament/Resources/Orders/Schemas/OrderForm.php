<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required(),
                TextInput::make('external_no')
                    ->required(),
                Select::make('marketplace')
                    ->options(['SHOPEE' => 'S h o p e e', 'TOKOPEDIA' => 'T o k o p e d i a', 'TIKTOK' => 'T i k t o k'])
                    ->required(),
                Select::make('status')
                    ->options([
            'PENDING' => 'P e n d i n g',
            'PAID' => 'P a i d',
            'SHIPPED' => 'S h i p p e d',
            'COMPLETED' => 'C o m p l e t e d',
            'CANCELLED' => 'C a n c e l l e d',
            'RETURNED' => 'R e t u r n e d',
        ])
                    ->default('PAID')
                    ->required(),
                Select::make('fulfillment')
                    ->options(['SELF' => 'S e l f', 'DROPSHIP' => 'D r o p s h i p'])
                    ->default('SELF')
                    ->required(),
                DateTimePicker::make('order_date')
                    ->required(),
                TextInput::make('buyer_name')
                    ->default(null),
                TextInput::make('product_revenue')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('shipping_charged_to_buyer')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('other_income')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('cogs')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('admin_fee')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('shipping_cost_seller')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('voucher_seller_borne')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('dropship_cost')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('other_cost')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                Toggle::make('income_verified')
                    ->required(),
                TextInput::make('note')
                    ->default(null),
            ]);
    }
}
