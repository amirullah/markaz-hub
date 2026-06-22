<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('fee_shopee')
                    ->label('% Admin Shopee')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%')
                    ->alignEnd(),
                TextColumn::make('fee_tokotiktok')
                    ->label('% Admin Tokopedia/TikTok')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%')
                    ->alignEnd(),
                TextColumn::make('products_count')
                    ->label('Jumlah Produk')
                    ->counts('products')
                    ->alignEnd()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
