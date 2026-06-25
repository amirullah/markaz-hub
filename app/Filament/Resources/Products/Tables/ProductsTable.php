<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'category']))
            ->columns([
                // SKU (tebal, urut). Nama produk muncul saat kursor diarahkan (tooltip), tetap bisa dicari.
                TextColumn::make('sku')
                    ->label('SKU')
                    ->disabledClick()
                    ->weight('bold')
                    ->searchable(['sku', 'name'])
                    ->copyable()
                    ->copyMessage('SKU disalin')
                    ->copyMessageDuration(1500)
                    ->sortable()
                    ->tooltip(fn ($record): ?string => $record->name),
                // Modal HPP (urut) + modal dropship di bawah (jika dropship).
                TextColumn::make('cost_price')
                    ->label('Modal')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd()
                    ->description(fn ($record): ?string => \App\Models\Organization::currentUsesDropship() && (float) $record->dropship_cost > 0
                        ? 'Dropship Rp ' . number_format((float) $record->dropship_cost, 0, ',', '.') : null),
                // Tanggal harga diubah (urut).
                TextColumn::make('cost_changed_at')
                    ->label('Diubah')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                // Kategori (badge, urut via subquery) + supplier di bawah.
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Category::select('name')->whereColumn('categories.id', 'products.category_id'), $direction
                    ))
                    ->description(fn ($record): ?string => $record->supplier?->name ? 'Supplier: ' . $record->supplier->name : null),
                // Admin Shopee % (urut via subquery).
                TextColumn::make('category.fee_shopee')
                    ->label('Admin Shopee')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%' : '—')
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Category::select('fee_shopee')->whereColumn('categories.id', 'products.category_id'), $direction
                    )),
                // Admin Tokopedia/TikTok % (urut via subquery).
                TextColumn::make('category.fee_tokotiktok')
                    ->label('Admin Toped/TikTok')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',') . '%' : '—')
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Category::select('fee_tokotiktok')->whereColumn('categories.id', 'products.category_id'), $direction
                    )),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                // Harga: pernah / tidak pernah berubah (pill).
                Filter::make('harga_berubah')
                    ->schema([
                        ToggleButtons::make('value')->label('Harga')->hiddenLabel()->inline()
                            ->options(['semua' => 'Semua harga', 'berubah' => 'Pernah berubah', 'tetap' => 'Tidak pernah berubah'])->default('semua'),
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        function ($query) use ($v) {
                            $changed = function ($q): void {
                                $q->select('sku')->from('product_price_changes')
                                    ->where('organization_id', (int) auth()->user()->organization_id)
                                    ->whereNotNull('old_price');
                            };

                            return $v === 'berubah'
                                ? $query->whereIn('products.sku', $changed)
                                : $query->whereNotIn('products.sku', $changed);
                        },
                    ))
                    ->indicateUsing(fn (array $data): ?string => match ($data['value'] ?? null) {
                        'berubah' => 'Harga pernah berubah',
                        'tetap' => 'Harga tidak pernah berubah',
                        default => null,
                    }),
                // Status aktif (pill).
                Filter::make('active')
                    ->schema([
                        ToggleButtons::make('value')->label('Status')->hiddenLabel()->inline()
                            ->options(['semua' => 'Semua status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('semua'),
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        fn ($query) => $query->where('active', $v === 'aktif'),
                    ))
                    ->indicateUsing(fn (array $data): ?string => match ($data['value'] ?? null) {
                        'aktif' => 'Hanya aktif',
                        'nonaktif' => 'Hanya nonaktif',
                        default => null,
                    }),
                // Supplier (dropdown).
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->placeholder('Semua supplier')
                    ->relationship('supplier', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload(),
                // Kategori (dropdown).
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->placeholder('Semua kategori')
                    ->relationship('category', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload(),
                // Rentang tanggal harga diubah.
                Filter::make('tgl_diubah')
                    ->schema([
                        DatePicker::make('dari')->label('Diubah dari')->hiddenLabel()->placeholder('Diubah dari')->native(false),
                        DatePicker::make('sampai')->label('Diubah sampai')->hiddenLabel()->placeholder('Diubah sampai')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn ($query, array $data) => $query
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->whereDate('cost_changed_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->whereDate('cost_changed_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $ind = [];
                        if ($data['dari'] ?? null) {
                            $ind[] = 'Diubah dari ' . $data['dari'];
                        }
                        if ($data['sampai'] ?? null) {
                            $ind[] = 'Diubah sampai ' . $data['sampai'];
                        }

                        return $ind;
                    }),
                // Rentang modal (HPP).
                Filter::make('modal')
                    ->schema([
                        TextInput::make('min')->label('Modal min')->hiddenLabel()->placeholder('Modal min')->numeric()->prefix('Rp'),
                        TextInput::make('max')->label('Modal maks')->hiddenLabel()->placeholder('Modal maks')->numeric()->prefix('Rp'),
                    ])
                    ->columns(2)
                    ->query(fn ($query, array $data) => $query
                        ->when($data['min'] ?? null, fn ($q, $v) => $q->where('cost_price', '>=', $v))
                        ->when($data['max'] ?? null, fn ($q, $v) => $q->where('cost_price', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $ind = [];
                        if (($data['min'] ?? '') !== '') {
                            $ind[] = 'Modal ≥ Rp ' . number_format((float) $data['min'], 0, ',', '.');
                        }
                        if (($data['max'] ?? '') !== '') {
                            $ind[] = 'Modal ≤ Rp ' . number_format((float) $data['max'], 0, ',', '.');
                        }

                        return $ind;
                    }),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 2, 'md' => 3, 'lg' => 4])
            ->deferFilters(false)
            // Klik baris → Ubah produk. SKU dikecualikan (->disabledClick) agar bisa DISALIN, bukan navigasi.
            ->recordUrl(fn (\App\Models\Product $record): string => \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('riwayatHarga')
                    ->label('Riwayat Harga')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Riwayat Harga — ' . $record->sku)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn ($record) => view('filament.products.price-history', [
                        'record' => $record,
                        'changes' => DB::table('product_price_changes')
                            ->where('organization_id', $record->organization_id)
                            ->where('sku', $record->sku)
                            ->orderByDesc('changed_at')->orderByDesc('id')
                            ->get(),
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \App\Filament\Actions\CopyBulkAction::make('salinSku', 'Salin SKU', 'sku', 'SKU'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
