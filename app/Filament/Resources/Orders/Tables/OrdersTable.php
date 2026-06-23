<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Services\ProfitService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                // Status laba: "Final" HANYA bila biaya ASLI + modal lengkap. Pesanan packing
                // sendiri tanpa HPP → "Perlu data" (laba belum akurat), bukan Final.
                TextColumn::make('status_laba')
                    ->label('Status Laba')
                    ->badge()
                    ->state(function (\App\Models\Order $record): string {
                        $gaps = $record->incompleteness();
                        if (empty($gaps)) {
                            // Laba final. "Final*" bila rincian item belum ada (laba tetap pasti).
                            return $record->lacksItemDetail() ? 'Final*' : 'Final';
                        }
                        $dataGaps = array_filter($gaps, fn (string $g): bool => ! str_contains($g, 'ESTIMASI'));

                        return $dataGaps ? 'Perlu data' : 'Estimasi';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Final', 'Final*' => 'success',
                        'Perlu data' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Final' => 'heroicon-m-check-circle',
                        'Final*' => 'heroicon-m-information-circle',
                        'Perlu data' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-clock',
                    })
                    ->tooltip(function (\App\Models\Order $record): string {
                        $g = $record->incompleteness();
                        if ($g) {
                            return 'Belum: ' . implode(' · ', $g);
                        }
                        if ($record->lacksItemDetail()) {
                            return 'Laba sudah final & akurat, TAPI rincian item produk belum tercatat (tak memengaruhi laba). Impor File/Laporan Pesanan untuk detail produk.';
                        }

                        return 'Laba final & akurat — biaya asli, modal, & rincian item lengkap.';
                    }),
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
                    // Opsi MENYESUAIKAN data: hanya status yang benar-benar ada di pesanan toko ini.
                    ->options(function (): array {
                        $labels = [
                            'COMPLETED' => 'Selesai', 'SHIPPED' => 'Dikirim', 'CANCELLED' => 'Dibatalkan',
                            'RETURNED' => 'Dikembalikan', 'PAID' => 'Dibayar', 'PENDING' => 'Menunggu',
                        ];

                        return \App\Models\Order::query()->distinct()->orderBy('status')->pluck('status')
                            ->mapWithKeys(fn ($s): array => [$s => $labels[$s] ?? $s])->all();
                    }),
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
            ])
            // Filter tampil DI ATAS tabel (tak menutup data), ringkas & padat (sampai 4 kolom),
            // berlaku seketika. AboveContent agar user langsung lihat filter tersedia.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 2, 'md' => 3, 'lg' => 4])
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
