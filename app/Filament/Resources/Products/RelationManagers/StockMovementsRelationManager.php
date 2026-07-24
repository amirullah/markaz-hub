<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Riwayat Stok';

    protected static ?string $modelLabel = 'Mutasi';

    protected static ?string $pluralModelLabel = 'Mutasi Stok';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'IN' => 'Masuk',
                        'OUT' => 'Keluar',
                        'ADJUSTMENT' => 'Penyesuaian',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'IN' => 'success',
                        'OUT' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Referensi')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Mutasi')
                    ->modalHeading('Tambah Mutasi Stok')
                    ->form([
                        Select::make('type')
                            ->label('Jenis')
                            ->options(['IN' => 'Masuk', 'OUT' => 'Keluar', 'ADJUSTMENT' => 'Penyesuaian'])
                            ->required()
                            ->native(false),
                        TextInput::make('qty')
                            ->label('Jumlah')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('reference')
                            ->label('Referensi (opsional)')
                            ->placeholder('No. pesanan, faktur, dll')
                            ->maxLength(100),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['organization_id'] = (int) auth()->user()->organization_id;
                        // Update product stock.
                        $product = $this->getOwnerRecord();
                        if ($data['type'] === 'IN') {
                            $product->increment('stock', (int) $data['qty']);
                        } elseif ($data['type'] === 'OUT') {
                            $product->decrement('stock', (int) $data['qty']);
                        } else {
                            // ADJUSTMENT: set stock to qty value
                            $product->update(['stock' => (int) $data['qty']]);
                            $data['qty'] = (int) $data['qty']; // store adjusted value
                        }
                        return $data;
                    }),
            ]);
    }
}
