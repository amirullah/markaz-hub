<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'category']))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('cost_price')
                    ->label('HPP / Modal')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('cost_changed_at')
                    ->label('Harga Diubah')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable()
                    ->tooltip('Tanggal harga master terakhir berubah (dari kolom "Perubahan Terakhir" Jakmall). Klik "Riwayat Harga" untuk detail.')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('dropship_cost')
                    ->label('Modal Dropship')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesJakmall()),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('category.fee_shopee')
                    ->label('Admin Shopee')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%' : '—')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('category.fee_tokotiktok')
                    ->label('Admin Toped/TikTok')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%' : '—')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('harga_berubah')
                    ->label('Hanya yang harganya pernah berubah')
                    ->query(fn ($query) => $query->whereIn('products.sku', function ($q): void {
                        $q->select('sku')->from('product_price_changes')
                            ->where('organization_id', (int) auth()->user()->organization_id)
                            ->whereNotNull('old_price');
                    })),
            ])
            ->recordActions([
                Action::make('riwayatHarga')
                    ->label('Riwayat Harga')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Riwayat Harga — ' . $record->sku)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn ($record) => view('filament.products.price-history', [
                        'record' => $record,
                        'changes' => DB::table('product_price_changes')
                            ->where('organization_id', $record->organization_id)
                            ->where('sku', $record->sku)
                            ->orderByDesc('changed_at')->orderByDesc('id')
                            ->get(),
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
