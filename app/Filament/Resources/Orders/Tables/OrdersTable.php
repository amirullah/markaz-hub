<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Services\ProfitService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'SHOPEE' => 'Shopee',
                        'TIKTOKTOKO' => 'Tokopedia/TikTok',
                        'TOKOPEDIA' => 'Tokopedia',
                        'TIKTOK' => 'TikTok',
                        default => $state,
                    })
                    ->color(fn (string $state): string => $state === 'SHOPEE' ? 'warning' : 'success'),
                TextColumn::make('fulfillment')
                    ->label('Pemenuhan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'DROPSHIP' ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'DROPSHIP' ? 'Dropship' : 'Packing Sendiri')
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
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
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        ProfitService::sqlProfit() . ' ' . $direction
                    )),
                TextColumn::make('income_verified')
                    ->label('Laba Final')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Final' : 'Estimasi')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->tooltip(fn ($state): string => $state
                        ? 'Angka final (Laporan Penghasilan marketplace sudah masuk).'
                        : 'Masih estimasi (belum ada Laporan Penghasilan).'),
                TextColumn::make('kelengkapan')
                    ->label('Kelengkapan')
                    ->badge()
                    ->state(fn (\App\Models\Order $record): string => ($g = $record->incompleteness())
                        ? count($g) . ' perlu data'
                        : 'Lengkap')
                    ->color(fn (string $state): string => $state === 'Lengkap' ? 'success' : 'warning')
                    ->icon(fn (string $state): string => $state === 'Lengkap' ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                    ->tooltip(fn (\App\Models\Order $record): string => ($g = $record->incompleteness())
                        ? 'Belum lengkap: ' . implode(' · ', $g)
                        : 'Data pesanan lengkap')
                    ->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('items:id,order_id,product_id'))
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Toko')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
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
                Filter::make('channel')
                    ->schema([
                        Select::make('value')
                            ->label('Channel')
                            ->options([
                                'SHOPEE' => 'Shopee',
                                'TIKTOKTOKO' => 'Tokopedia/TikTok',
                            ])
                            ->placeholder('Semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $v): Builder => $v === 'SHOPEE'
                            ? $query->where('marketplace', 'SHOPEE')
                            : $query->whereIn('marketplace', ['TIKTOKTOKO', 'TOKOPEDIA', 'TIKTOK']),
                    )),
                SelectFilter::make('fulfillment')
                    ->label('Pemenuhan')
                    ->options([
                        'SELF' => 'Packing Sendiri',
                        'DROPSHIP' => 'Dropship',
                    ])
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                TernaryFilter::make('income_verified')
                    ->label('Laba Final')
                    ->placeholder('Semua')
                    ->trueLabel('Final')
                    ->falseLabel('Estimasi (belum ada Laporan Penghasilan)'),
                Filter::make('periode')
                    ->schema([
                        Select::make('value')
                            ->label('Periode')
                            ->options([
                                'minggu_ini' => 'Minggu ini',
                                'bulan_ini' => 'Bulan ini',
                                'tahun_ini' => 'Tahun ini',
                                '30hari' => '30 hari terakhir',
                                'minggu_lalu' => 'Minggu lalu',
                                'bulan_lalu' => 'Bulan lalu',
                                'tahun_lalu' => 'Tahun lalu',
                            ])
                            ->placeholder('Semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $v): Builder => self::applyPeriode($query, $v),
                    )),
                Filter::make('order_date')
                    ->label('Rentang tanggal kustom')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $d): Builder => $query->whereDate('order_date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $query, $d): Builder => $query->whereDate('order_date', '<=', $d))),
                Filter::make('tanpa_item')
                    ->label('Belum ada item produk')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('items')),
                TrashedFilter::make()->label('Terhapus'),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn ($action) => $action->label('Filter')->icon('heroicon-m-funnel'))
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Terapkan preset periode ke query — filter cepat tanpa pilih tanggal manual. */
    private static function applyPeriode(Builder $q, string $v): Builder
    {
        return match ($v) {
            'minggu_ini' => $q->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]),
            'bulan_ini' => $q->whereBetween('order_date', [now()->startOfMonth(), now()->endOfMonth()]),
            'tahun_ini' => $q->whereBetween('order_date', [now()->startOfYear(), now()->endOfYear()]),
            '30hari' => $q->where('order_date', '>=', now()->subDays(30)->startOfDay()),
            'minggu_lalu' => $q->whereBetween('order_date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
            'bulan_lalu' => $q->whereBetween('order_date', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()]),
            'tahun_lalu' => $q->whereBetween('order_date', [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()]),
            default => $q,
        };
    }
}
