<?php

namespace App\Filament\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    /** Badge channel berwarna (oranye Shopee / hijau lainnya) — seragam dgn Pesanan. */
    private static function channelBadge(string $marketplace): \Illuminate\Support\HtmlString
    {
        $label = \App\Models\Store::channelLabel($marketplace);
        [$bg, $fg] = $marketplace === 'SHOPEE' ? ['#fef3c7', '#92400e'] : ['#dcfce7', '#166534'];

        return new \Illuminate\Support\HtmlString(
            '<span style="display:inline-block;font-size:11px;line-height:1.45;padding:1px 8px;border-radius:6px;background:' . $bg . ';color:' . $fg . '">' . e($label) . '</span>'
        );
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                // Nama toko (tebal) + channel (badge berwarna) di bawah — padat, seragam dgn Pesanan.
                TextColumn::make('name')
                    ->label('Toko')
                    ->searchable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                        '<div>' . e($state) . '</div>'
                        . '<div style="margin-top:3px">' . self::channelBadge((string) $record->marketplace)->toHtml() . '</div>'
                    )),
                // Mode pemenuhan toko (badge).
                TextColumn::make('fulfillment_mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'dropship' => 'Dropship saja', 'self' => 'Packing sendiri saja', default => 'Keduanya',
                    })
                    ->color(fn ($state): string => match ($state) {
                        'dropship' => 'info', 'self' => 'warning', default => 'gray',
                    })
                    ->description(function ($record): ?string {
                        $a = \Illuminate\Support\Facades\DB::table('orders')
                            ->where('store_id', $record->id)->where('organization_id', $record->organization_id)
                            ->selectRaw("COUNT(*) total, SUM(fulfillment='DROPSHIP') d, SUM(fulfillment='SELF') s")->first();
                        $total = (int) ($a->total ?? 0);

                        return $total > 0
                            ? 'Dropship ' . round($a->d / $total * 100) . '% · Packing ' . round($a->s / $total * 100) . '%'
                            : null;
                    })
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                TextColumn::make('orders_count')
                    ->label('Jumlah Pesanan')
                    ->counts('orders')
                    ->alignEnd()
                    ->sortable(),
                // Pesanan JANGGAL: pemenuhan tak sesuai mode toko.
                TextColumn::make('janggal')
                    ->label('Janggal')
                    ->badge()
                    ->alignEnd()
                    ->state(fn ($record): int => $record->orders()->janggal()->count())
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray')
                    ->tooltip('Pesanan yang biaya dropship-nya sudah ada tapi belum jadi dropship (tak sinkron)')
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),
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
