<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        // organization_id otomatis di-set ke organisasi user (BelongsToOrganization).
        return $schema
            ->components([
                Section::make('Identitas Produk')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->placeholder('Kode unik produk, mis. 7RRSDOBK')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('organization_id', auth()->user()->organization_id),
                            )
                            ->validationMessages(['unique' => 'SKU ini sudah dipakai produk lain.']),
                        TextInput::make('name')
                            ->label('Nama Produk')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Harga & Modal')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('HPP / Modal')
                            ->helperText('Harga modal per unit (untuk pesanan SELF).')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp'),
                        TextInput::make('dropship_cost')
                            ->label('Modal Dropship')
                            ->helperText('Harga modal per unit jika dropship (dari supplier).')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp')
                            ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                    ]),
                Section::make('Stok')
                    ->columns(2)
                    ->schema([
                        TextInput::make('stock')
                            ->label('Stok Saat Ini')
                            ->helperText('Jumlah barang tersedia. Berkurang otomatis saat pesanan ditandai Dikirim.')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('min_stock')
                            ->label('Stok Minimum')
                            ->helperText('Peringatan stok menipis (0 = nonaktif).')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
                Section::make('Klasifikasi & Status')
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('— opsional —'),
                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Untuk estimasi biaya admin. Jika dikosongkan, sistem memilih otomatis dari nama produk.')
                            ->placeholder('— otomatis dari nama produk —'),
                        Toggle::make('active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}
