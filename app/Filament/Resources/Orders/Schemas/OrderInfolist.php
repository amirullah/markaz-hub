<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    private static function rp(): \Closure
    {
        return fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.');
    }

    public static function configure(Schema $schema): Schema
    {
        $rp = self::rp();

        // Layout MENIRU halaman Ubah (v1): info lengkap di kiri + sidebar "Ringkasan Laba" kanan.
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            // Tabel item produk (atas, full width) — ala v1.
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('items_table')
                        ->hiddenLabel()
                        ->state(fn ($record): \Illuminate\Support\HtmlString => OrdersTable::itemsTableHtml($record)),
                ]),

            // === KIRI: informasi lengkap ===
            Group::make([
                Section::make('Laba Belum Pasti')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->description('Hal berikut membuat laba pesanan ini belum akurat')
                    ->visible(fn ($record): bool => $record !== null && ! empty($record->incompleteness()))
                    ->schema([
                        TextEntry::make('kelengkapan')
                            ->hiddenLabel()
                            ->state(fn ($record): array => $record->incompleteness())
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->color('warning'),
                    ]),

                Section::make('Catatan')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('gray')
                    ->visible(fn ($record): bool => $record !== null && $record->lacksItemDetail())
                    ->schema([
                        TextEntry::make('item_note')
                            ->hiddenLabel()
                            ->state('Rincian item produk belum tercatat. Ini TIDAK memengaruhi laba — untuk dropship laba dihitung dari biaya dropship, dan untuk packing sendiri dari HPP. Impor File/Laporan Pesanan bila ingin melihat rincian produknya.')
                            ->color('gray'),
                    ]),

                Section::make('Informasi Pesanan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('external_no')->label('No. Pesanan')->copyable()->copyMessage('No. pesanan disalin')->weight('bold'),
                        TextEntry::make('order_date')->label('Tanggal')->dateTime('d M Y H:i'),
                        TextEntry::make('store.name')->label('Toko'),
                        TextEntry::make('marketplace')->label('Channel')->badge()
                            ->formatStateUsing(fn ($state) => OrderForm::CHANNEL[$state] ?? $state)
                            ->color(fn ($state) => $state === 'SHOPEE' ? 'warning' : 'success'),
                        TextEntry::make('status')->label('Status')->badge()
                            ->formatStateUsing(fn ($state) => OrderForm::STATUS[$state] ?? $state)
                            ->color(fn ($state): string => match ($state) {
                                'COMPLETED' => 'success', 'CANCELLED' => 'danger', 'RETURNED' => 'warning', 'SHIPPED' => 'info', default => 'gray',
                            }),
                        TextEntry::make('fulfillment')->label('Pemenuhan')->badge()->color('gray')
                            ->formatStateUsing(fn ($state) => OrderForm::FULFILLMENT[$state] ?? $state)
                            ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                        TextEntry::make('buyer_name')->label('Pembeli')->placeholder('—'),
                        TextEntry::make('status_laba')->label('Status Laba')->badge()
                            ->state(fn ($record): string => OrdersTable::statusLaba($record))
                            ->color(fn (string $state): string => OrdersTable::statusLabaColor($state))
                            ->icon(fn (string $state): ?string => OrdersTable::statusLabaIcon($state)),
                    ]),

                Section::make('Pendapatan')
                    ->description('Uang masuk')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('product_revenue')->label('Omzet Produk')->formatStateUsing($rp),
                        TextEntry::make('shipping_charged_to_buyer')->label('Ongkir dari Pembeli')->formatStateUsing($rp),
                        TextEntry::make('other_income')->label('Pendapatan Lain')->formatStateUsing($rp),
                    ]),

                Section::make('Biaya')
                    ->description('Pengurang laba')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cogs')->label('HPP / Modal')
                            ->state(function ($record): float {
                                $cogs = (float) $record->cogs;
                                if (\App\Models\Organization::currentUsesDropship()) {
                                    return $cogs;
                                }
                                $modal = (float) $record->dropship_modal;

                                return $cogs + ($modal > 0 ? $modal : (float) $record->dropship_cost);
                            })
                            ->formatStateUsing($rp),
                        TextEntry::make('admin_fee')->label('Biaya Admin')->formatStateUsing($rp)
                            ->tooltip('Untuk pesanan estimasi, sudah termasuk biaya proses Rp1.250/pesanan.'),
                        TextEntry::make('shipping_cost_seller')->label('Ongkir Ditanggung Seller')->formatStateUsing($rp),
                        TextEntry::make('voucher_seller_borne')->label('Voucher Ditanggung Seller')->formatStateUsing($rp),
                        TextEntry::make('dropship_cost')->label('Biaya Dropship')->formatStateUsing($rp)
                            ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                        TextEntry::make('other_cost')->label('Biaya Lain')->formatStateUsing($rp),
                    ]),

                Section::make('Catatan Pesanan')
                    ->visible(fn ($record): bool => filled($record->note))
                    ->schema([
                        TextEntry::make('note')->hiddenLabel(),
                    ]),
            ])->columnSpan(['default' => 1, 'lg' => 2]),

            // === KANAN: Ringkasan Laba (sticky) ===
            Section::make('Ringkasan Laba')
                ->columnSpan(1)
                ->extraAttributes(['style' => 'position:sticky;top:5rem'])
                ->schema([
                    TextEntry::make('product_revenue')->label('Pendapatan')->inlineLabel()
                        ->formatStateUsing(fn ($record): string => 'Rp ' . number_format((float) $record->product_revenue + (float) $record->other_income, 0, ',', '.')),
                    TextEntry::make('net')->label('Uang Bersih Marketplace')->inlineLabel()->formatStateUsing($rp),
                    TextEntry::make('profit')->label('Laba Bersih')->inlineLabel()
                        ->formatStateUsing($rp)
                        ->weight('bold')
                        ->size('lg')
                        ->color(fn ($state): string => (float) $state < 0 ? 'danger' : 'success'),
                    TextEntry::make('margin')->label('Margin')->inlineLabel()
                        ->state(function ($record): string {
                            $rev = (float) $record->product_revenue + (float) $record->other_income;
                            $m = $rev > 0 ? (float) $record->profit / $rev * 100 : 0;

                            return number_format($m, 1, ',', '.') . '%';
                        })
                        ->color('gray'),
                ]),
        ]);
    }
}
