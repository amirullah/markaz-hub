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
                // Status koneksi API (Shopee / TikTok).
                TextColumn::make('api_status')
                    ->label('API')
                    ->badge()
                    ->state(function ($record): string {
                        if ($record->marketplace === 'SHOPEE') {
                            $c = $record->shopeeConnection;
                            return match (true) {
                                $c === null => 'Belum terhubung',
                                $c->status === 'CONNECTED' => 'Terhubung',
                                $c->status === 'ERROR' => 'Error',
                                default => 'Terputus',
                            };
                        }
                        if ($record->marketplace === 'TIKTOKTOKO') {
                            $c = $record->tikTokConnection;
                            return match (true) {
                                $c === null => 'Belum terhubung',
                                $c->status === 'CONNECTED' => 'Terhubung',
                                $c->status === 'ERROR' => 'Error',
                                default => 'Terputus',
                            };
                        }
                        return '—';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Terhubung' => 'success', 'Error' => 'danger', 'Belum terhubung', 'Terputus' => 'warning', default => 'gray',
                    })
                    ->tooltip(function ($record): ?string {
                        $c = $record->marketplace === 'SHOPEE' ? $record->shopeeConnection : $record->tikTokConnection;
                        return $c?->last_synced_at
                            ? 'Sinkron terakhir: ' . $c->last_synced_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                            : null;
                    }),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->recordActions([
                // === SHOPEE ===
                \Filament\Actions\Action::make('shopeeConnect')
                    ->label('Hubungkan Shopee')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLink)
                    ->color('warning')
                    ->url(fn ($record): string => route('shopee.connect', $record))
                    ->visible(fn ($record): bool => $record->marketplace === 'SHOPEE' && ! $record->shopeeConnected()),
                \Filament\Actions\Action::make('shopeeSync')
                    ->label('Sinkron Shopee')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->visible(fn ($record): bool => $record->marketplace === 'SHOPEE' && $record->shopeeConnected())
                    ->requiresConfirmation()
                    ->modalHeading('Sinkron data dari Shopee?')
                    ->modalDescription('Menarik pesanan & settlement terbaru dari Shopee API lalu menggabungkannya (aman diulang, tidak dobel).')
                    ->action(function ($record): void {
                        try {
                            $res = app(\App\Services\Shopee\ShopeeSync::class)->syncStore($record->shopeeConnection);
                            \Filament\Notifications\Notification::make()
                                ->title('Sinkron Shopee selesai')
                                ->body(($res['message'] ?? '') . ' (' . ($res['orders'] ?? 0) . ' pesanan, ' . ($res['escrow'] ?? 0) . ' settlement)')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Sinkron Shopee gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('shopeeCatalog')
                    ->label('Sinkron Katalog')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedRectangleStack)
                    ->color('info')
                    ->visible(fn ($record): bool => $record->marketplace === 'SHOPEE' && $record->shopeeConnected())
                    ->requiresConfirmation()
                    ->modalHeading('Sinkron katalog produk dari Shopee?')
                    ->modalDescription('Menarik daftar produk & SKU dari Shopee. Harga MODAL tidak diubah (tetap dari Impor Daftar Produk).')
                    ->action(function ($record): void {
                        try {
                            $res = app(\App\Services\Shopee\ShopeeSync::class)->syncCatalog($record->shopeeConnection);
                            \Filament\Notifications\Notification::make()
                                ->title('Katalog Shopee tersinkron')
                                ->body(($res['products'] ?? 0) . ' produk/varian, ' . ($res['mapped_ids'] ?? 0) . ' pemetaan ID.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Sinkron katalog gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // === TOKOPEDIA/TIKTOK ===
                \Filament\Actions\Action::make('tikTokConnect')
                    ->label('Hubungkan Tokopedia/TikTok')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLink)
                    ->color('warning')
                    ->url(fn ($record): string => route('tokpedtiktok.connect', $record))
                    ->visible(fn ($record): bool => $record->marketplace === 'TIKTOKTOKO' && ! $record->tikTokConnected()),
                \Filament\Actions\Action::make('tikTokSync')
                    ->label('Sinkron Tokopedia/TikTok')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->visible(fn ($record): bool => $record->marketplace === 'TIKTOKTOKO' && $record->tikTokConnected())
                    ->requiresConfirmation()
                    ->modalHeading('Sinkron data dari Tokopedia/TikTok?')
                    ->modalDescription('Menarik pesanan & settlement terbaru dari Tokopedia/TikTok API lalu menggabungkannya.')
                    ->action(function ($record): void {
                        try {
                            $res = app(\App\Services\TokpedTikTok\TokpedTikTokSync::class)->syncStore($record->tikTokConnection);
                            \Filament\Notifications\Notification::make()
                                ->title('Sinkron Tokopedia/TikTok selesai')
                                ->body(($res['message'] ?? '') . ' (' . ($res['orders'] ?? 0) . ' pesanan, ' . ($res['settlement'] ?? 0) . ' settlement)')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Sinkron Tokopedia/TikTok gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('tikTokCatalog')
                    ->label('Sinkron Katalog')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedRectangleStack)
                    ->color('info')
                    ->visible(fn ($record): bool => $record->marketplace === 'TIKTOKTOKO' && $record->tikTokConnected())
                    ->requiresConfirmation()
                    ->modalHeading('Sinkron katalog produk dari Tokopedia/TikTok?')
                    ->modalDescription('Menarik daftar produk & SKU dari Tokopedia/TikTok. Harga MODAL tidak diubah.')
                    ->action(function ($record): void {
                        try {
                            $res = app(\App\Services\TokpedTikTok\TokpedTikTokSync::class)->syncCatalog($record->tikTokConnection);
                            \Filament\Notifications\Notification::make()
                                ->title('Katalog Tokopedia/TikTok tersinkron')
                                ->body(($res['products'] ?? 0) . ' produk/varian, ' . ($res['mapped_ids'] ?? 0) . ' pemetaan ID.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Sinkron katalog gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
