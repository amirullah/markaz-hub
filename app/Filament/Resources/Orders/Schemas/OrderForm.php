<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class OrderForm
{
    /** Map status & pemenuhan — SAMA dengan tampilan tabel agar konsisten. */
    public const STATUS = [
        'PENDING' => 'Menunggu',
        'PAID' => 'Dibayar',
        'SHIPPED' => 'Dikirim',
        'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan',
        'RETURNED' => 'Dikembalikan',
    ];

    public const FULFILLMENT = [
        'SELF' => 'Packing Sendiri',
        'DROPSHIP' => 'Dropship',
    ];

    public const CHANNEL = [
        'SHOPEE' => 'Shopee',
        'TIKTOKTOKO' => 'Tokopedia/TikTok',
    ];

    public static function configure(Schema $schema): Schema
    {
        // Layout meniru MarkazHub v1: 2 kolom (form kiri + sidebar "Ringkasan Laba"
        // sticky kanan dgn laba LIVE saat mengetik). No.Pesanan/Toko/Tanggal/Channel di header.
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            // Tabel item produk (atas, full width) — ala v1.
            Section::make()
                ->visible(fn ($record): bool => $record !== null)
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('items_table')
                        ->hiddenLabel()
                        ->content(fn ($record): HtmlString => OrdersTable::itemsTableHtml($record)),
                ]),

            // === KIRI: form ===
            Group::make([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            Select::make('status')->label('Status')->options(self::STATUS)->required()->native(false)->live(),
                            Select::make('fulfillment')->label('Jenis Pemenuhan')->options(self::FULFILLMENT)->required()->native(false)
                                ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                        ]),
                    Section::make('Pendapatan')
                        ->columns(3)
                        ->schema([
                            self::rpInput('product_revenue', 'Harga Produk'),
                            self::rpInput('shipping_charged_to_buyer', 'Ongkir Dibayar Pembeli'),
                            self::rpInput('other_income', 'Pendapatan Lain'),
                        ]),
                    Section::make('Biaya')
                        ->columns(3)
                        ->schema([
                            self::rpInput('cogs', 'HPP / Modal'),
                            self::rpInput('admin_fee', 'Biaya Admin Marketplace'),
                            self::rpInput('shipping_cost_seller', 'Ongkir Ditanggung Penjual'),
                            self::rpInput('voucher_seller_borne', 'Voucher Ditanggung Penjual'),
                            self::rpInput('dropship_cost', 'Biaya Dropship (Jakmall)')
                                ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                            self::rpInput('other_cost', 'Biaya Lain'),
                        ]),
                    Section::make()
                        ->schema([
                            Textarea::make('note')->label('Catatan')->rows(2),
                        ]),
                ])->columnSpan(['default' => 1, 'lg' => 2]),

                // === KANAN: Ringkasan Laba (sticky, laba live) ===
                Section::make('Ringkasan Laba')
                    ->columnSpan(1)
                    ->extraAttributes(['style' => 'position:sticky;top:5rem'])
                    ->schema([
                        Placeholder::make('warn')
                            ->hiddenLabel()
                            ->visible(fn ($record): bool => $record !== null)
                            ->content(fn ($record, Get $get): HtmlString => self::warningNote($record, $get)),
                        Placeholder::make('sum_rev')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::row('Pendapatan', 'Rp ' . self::n(self::revenue($get)))),
                        Placeholder::make('sum_cost')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::row('Total Biaya', '-Rp ' . self::n(self::cost($get)), '#dc2626')),
                        Placeholder::make('sum_profit')
                            ->hiddenLabel()
                            ->content(function (Get $get): HtmlString {
                                $rev = self::revenue($get);
                                $profit = $rev - self::cost($get);
                                $margin = $rev > 0 ? $profit / $rev * 100 : 0;
                                $color = $profit < 0 ? '#dc2626' : '#16a34a';

                                return new HtmlString(
                                    '<div style="border-top:1px solid #eef2f7;margin-top:.25rem;padding-top:.6rem;display:flex;justify-content:space-between;align-items:baseline">'
                                    . '<span style="font-weight:700">Laba Bersih</span>'
                                    . '<span style="font-size:1.4rem;font-weight:800;color:' . $color . '">Rp ' . self::n($profit) . '</span></div>'
                                    . '<div style="text-align:right;font-size:.72rem;color:#94a3b8">Margin ' . number_format($margin, 1, ',', '.') . '%</div>'
                                );
                            }),
                        Actions::make([
                            Action::make('simpan')
                                ->label('Simpan Perubahan')
                                ->color('primary')
                                ->size('lg')
                                ->action(fn ($livewire) => $livewire->save()),
                            Action::make('batal')
                                ->label('Batal')
                                ->color('gray')
                                ->url(fn (): string => \App\Filament\Resources\Orders\OrderResource::getUrl('index')),
                        ])->fullWidth(),
                    ]),
        ]);
    }

    /** Input rupiah reaktif (live) — dipakai semua field pendapatan & biaya. */
    private static function rpInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->prefix('Rp')
            ->live(onBlur: true);
    }

    private static function n(float $v): string
    {
        return number_format($v, 0, ',', '.');
    }

    private static function num(Get $get, string $key): float
    {
        return (float) ($get($key) ?: 0);
    }

    /** Pendapatan = harga produk + pendapatan lain (ongkir pembeli pass-through, tak masuk laba). */
    private static function revenue(Get $get): float
    {
        return self::num($get, 'product_revenue') + self::num($get, 'other_income');
    }

    private static function cost(Get $get): float
    {
        return self::num($get, 'cogs') + self::num($get, 'admin_fee') + self::num($get, 'shipping_cost_seller')
            + self::num($get, 'voucher_seller_borne') + self::num($get, 'dropship_cost') + self::num($get, 'other_cost');
    }

    private static function row(string $label, string $value, string $color = '#0f172a'): HtmlString
    {
        return new HtmlString(
            '<div style="display:flex;justify-content:space-between;padding:.1rem 0">'
            . '<span style="color:#64748b">' . e($label) . '</span>'
            . '<span style="font-weight:600;color:' . $color . '">' . e($value) . '</span></div>'
        );
    }

    private static function warningNote($record, Get $get): HtmlString
    {
        $status = $get('status') ?: $record->status;
        $box = fn (string $bg, string $fg, string $html): string =>
            '<div style="background:' . $bg . ';border-radius:.6rem;padding:.6rem .75rem;font-size:.78rem;line-height:1.5;color:' . $fg . ';margin-bottom:.5rem">' . $html . '</div>';

        if ($status === 'CANCELLED') {
            return new HtmlString($box('#fffbeb', '#92400e', '🚫 <strong>Pesanan dibatalkan</strong> — tidak ada transaksi uang, laba 0.'));
        }
        if ($status === 'RETURNED') {
            return new HtmlString($box('#fffbeb', '#92400e', '↩️ <strong>Pesanan dikembalikan.</strong> Modal (HPP) tidak dihitung sebagai rugi.'));
        }
        if (! empty($record->income_verified)) {
            return new HtmlString($box('#f0fdf4', '#166534', '✓ Laba <strong>final</strong> dari Laporan Penghasilan — uang bersih riil setelah semua potongan marketplace.'));
        }

        return new HtmlString($box('#fffbeb', '#92400e', '💰 <strong>Belum ada Laporan Penghasilan</strong> untuk pesanan ini, jadi biaya potongan marketplace (admin/komisi/layanan) belum dihitung — laba di bawah masih terlalu tinggi (belum final). Impor Laporan Penghasilan periode ini agar final.'));
    }
}
