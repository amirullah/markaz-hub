<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Services\ProfitService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_date', 'desc')
            ->columns([
                TextColumn::make('external_no')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Toko')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('marketplace')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SHOPEE' => 'warning',
                        'TOKOPEDIA' => 'success',
                        'TIKTOK' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('fulfillment')
                    ->label('Pemenuhan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'DROPSHIP' ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'DROPSHIP' ? 'Dropship' : 'Packing Sendiri'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'COMPLETED' => 'success',
                        'CANCELLED' => 'danger',
                        'RETURNED' => 'warning',
                        'SHIPPED' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'COMPLETED' => 'Selesai',
                        'CANCELLED' => 'Dibatalkan',
                        'RETURNED' => 'Dikembalikan',
                        'SHIPPED' => 'Dikirim',
                        'PAID' => 'Dibayar',
                        'PENDING' => 'Menunggu',
                        default => $state,
                    }),
                TextColumn::make('product_revenue')
                    ->label('Omzet')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('profit')
                    ->label('Laba')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state): string => (float) $state < 0 ? 'danger' : 'success')
                    ->sortable(query: fn (Builder $q, string $direction): Builder => $q->orderByRaw(
                        ProfitService::SQL_PROFIT . ' ' . $direction
                    )),
                TextColumn::make('income_verified')
                    ->label('Laba Final')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Final' : 'Estimasi')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->tooltip(fn ($state): string => $state
                        ? 'Angka final (Laporan Penghasilan marketplace sudah masuk).'
                        : 'Masih estimasi (belum ada Laporan Penghasilan).'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'COMPLETED' => 'Selesai',
                        'SHIPPED' => 'Dikirim',
                        'CANCELLED' => 'Dibatalkan',
                        'RETURNED' => 'Dikembalikan',
                        'PAID' => 'Dibayar',
                        'PENDING' => 'Menunggu',
                    ]),
                SelectFilter::make('marketplace')
                    ->label('Channel')
                    ->options([
                        'SHOPEE' => 'Shopee',
                        'TOKOPEDIA' => 'Tokopedia',
                        'TIKTOK' => 'TikTok',
                    ]),
                SelectFilter::make('fulfillment')
                    ->label('Pemenuhan')
                    ->options([
                        'SELF' => 'Packing Sendiri',
                        'DROPSHIP' => 'Dropship',
                    ]),
                TernaryFilter::make('income_verified')
                    ->label('Laba Final')
                    ->placeholder('Semua')
                    ->trueLabel('Final')
                    ->falseLabel('Estimasi (belum ada Laporan Penghasilan)'),
                Filter::make('order_date')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('order_date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('order_date', '<=', $d))),
                TrashedFilter::make()->label('Terhapus'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
