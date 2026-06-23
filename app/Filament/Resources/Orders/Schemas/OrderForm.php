<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
        // Pesanan hanya masuk lewat Import (canCreate=false). Form ini untuk
        // MELIHAT / koreksi. organization_id otomatis (BelongsToOrganization).
        return $schema
            ->components([
                Section::make('Informasi Pesanan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('external_no')
                            ->label('No. Pesanan')
                            ->helperText('Nomor dari marketplace (tidak dapat diubah).')
                            ->disabled(),
                        Select::make('store_id')
                            ->label('Toko')
                            ->relationship('store', 'name')
                            ->required()
                            ->native(false),
                        Select::make('marketplace')
                            ->label('Channel')
                            ->options(self::CHANNEL)
                            // Ditetapkan saat impor — TIDAK boleh diubah (menentukan estimasi
                            // komisi/biaya). disabled() => tak ikut tersimpan, tak menimpa data.
                            ->disabled()
                            ->helperText('Ditetapkan saat impor; tidak dapat diubah.')
                            ->native(false),
                        DateTimePicker::make('order_date')
                            ->label('Tanggal Pesanan')
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label('Status')
                            ->options(self::STATUS)
                            ->default('PAID')
                            ->required()
                            ->native(false),
                        Select::make('fulfillment')
                            ->label('Pemenuhan')
                            ->options(self::FULFILLMENT)
                            ->default('SELF')
                            ->required()
                            ->native(false)
                            ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                        TextInput::make('buyer_name')
                            ->label('Nama Pembeli')
                            ->maxLength(255),
                    ]),

                Section::make('Pendapatan')
                    ->description('Uang masuk dari pesanan ini.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('product_revenue')
                            ->label('Omzet Produk')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                        TextInput::make('shipping_charged_to_buyer')
                            ->label('Ongkir dari Pembeli')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                        TextInput::make('other_income')
                            ->label('Pendapatan Lain')
                            ->required()->numeric()->default(0)->prefix('Rp'),
                    ]),

                Section::make('Biaya')
                    ->description('Pengurang laba. HPP = modal produk.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('cogs')
                            ->label('HPP (Modal)')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                        TextInput::make('admin_fee')
                            ->label('Biaya Admin Marketplace')
                            ->helperText('Untuk pesanan estimasi, angka ini sudah termasuk biaya proses Rp1.250/pesanan.')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                        TextInput::make('shipping_cost_seller')
                            ->label('Ongkir Ditanggung Penjual')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                        TextInput::make('voucher_seller_borne')
                            ->label('Voucher Ditanggung Penjual')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                        TextInput::make('dropship_cost')
                            ->label('Biaya Dropship')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp')
                            ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                        TextInput::make('other_cost')
                            ->label('Biaya Lain')
                            ->required()->numeric()->minValue(0)->default(0)->prefix('Rp'),
                    ]),

                Section::make('Status Laba & Catatan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('income_verified')
                            ->label('Laba sudah final')
                            ->helperText('Tercentang bila Laporan Penghasilan marketplace sudah masuk (angka final).'),
                        TextInput::make('note')
                            ->label('Catatan')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
