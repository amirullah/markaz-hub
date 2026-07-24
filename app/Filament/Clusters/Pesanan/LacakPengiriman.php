<?php

namespace App\Filament\Clusters\Pesanan;

use App\Filament\Clusters\Pesanan;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LacakPengiriman extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Lacak Pengiriman';

    protected static ?string $title = 'Lacak Pengiriman';

    protected static ?string $slug = 'lacak';

    protected static ?string $cluster = Pesanan::class;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.lacak-pengiriman';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->whereIn('processing_status', ['SHIPPED'])
                    ->whereNotNull('tracking_number')
            )
            ->columns([
                TextColumn::make('external_no')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Tgl Kirim')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('buyer_name')
                    ->label('Pembeli')
                    ->searchable(),
                TextColumn::make('tracking_number')
                    ->label('Resi')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Resi disalin'),
                TextColumn::make('courier')
                    ->label('Kurir'),
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
                TextColumn::make('shipped_at')
                    ->label('Waktu Kirim')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('shipped_at', 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->filters([]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            Pesanan::getUrl() => 'Pesanan',
            static::getUrl() => 'Lacak Pengiriman',
        ];
    }
}
