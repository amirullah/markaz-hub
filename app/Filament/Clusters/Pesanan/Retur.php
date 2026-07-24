<?php

namespace App\Filament\Clusters\Pesanan;

use App\Filament\Clusters\Pesanan;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class Retur extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $navigationLabel = 'Retur';

    protected static ?string $title = 'Retur & Pembatalan';

    protected static ?string $slug = 'retur';

    protected static ?string $cluster = Pesanan::class;

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.lacak-pengiriman';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()->whereIn('status', ['RETURNED', 'CANCELLED'])
            )
            ->columns([
                TextColumn::make('external_no')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('marketplace')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SHOPEE' => 'warning',
                        'TIKTOKTOKO', 'TIKTOK' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'SHOPEE' => 'Shopee',
                        'TIKTOKTOKO' => 'Tokopedia/TikTok',
                        'TIKTOK' => 'TikTok',
                        default => $state,
                    }),
                TextColumn::make('buyer_name')
                    ->label('Pembeli')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'RETURNED' => 'warning',
                        'CANCELLED' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'RETURNED' => 'Dikembalikan',
                        'CANCELLED' => 'Dibatalkan',
                        default => $state,
                    }),
                TextColumn::make('failed_reason')
                    ->label('Alasan')
                    ->limit(30)
                    ->tooltip(fn (Order $record): ?string => $record->failed_reason),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->defaultSort('order_date', 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->filters([]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            Pesanan::getUrl() => 'Pesanan',
            static::getUrl() => 'Retur',
        ];
    }
}
