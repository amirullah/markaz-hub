<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Services\ProfitService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    /** Preset periode — dipakai opsi Select & indikator filter aktif (DRY). */
    private const PERIODE = [
        'minggu_ini' => 'Minggu ini',
        'bulan_ini' => 'Bulan ini',
        'tahun_ini' => 'Tahun ini',
        '30hari' => '30 hari terakhir',
        '90hari' => '90 hari terakhir',
        'minggu_lalu' => 'Minggu lalu',
        'bulan_lalu' => 'Bulan lalu',
        'tahun_lalu' => 'Tahun lalu',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_date', 'desc')
            ->columns([
                // 1. No. Pesanan (atas) + jumlah item · pemesan (bawah).
                TextColumn::make('external_no')
                    ->label('No. Pesanan')
                    ->disabledClick()
                    ->weight('bold')
                    ->searchable(['external_no', 'buyer_name'])
                    ->copyable()
                    ->copyMessage('No. pesanan disalin')
                    ->copyMessageDuration(1500)
                    ->description(fn (\App\Models\Order $record): string => ((int) ($record->items_count ?? 0)) . ' item'
                        . ($record->buyer_name ? ' · ' . $record->buyer_name : ''))
                    ->sortable(),
                // 2. Tanggal (atas) + status pesanan BERWARNA (bawah).
                TextColumn::make('order_date')
                    ->label('Tanggal')                    ->sortable()
                    ->formatStateUsing(fn ($state, \App\Models\Order $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                        '<div>' . e($record->order_date?->translatedFormat('d M Y')) . '</div>'
                        . '<div style="margin-top:3px">' . self::statusBadge($record->status)->toHtml() . '</div>'
                    )),
                // 3. "Toko" — channel (atas) + nama toko (bawah).
                TextColumn::make('marketplace')
                    ->label('Toko')                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'SHOPEE' => 'Shopee',
                        'TIKTOKTOKO' => 'Tokopedia/TikTok',
                        'TOKOPEDIA' => 'Tokopedia',
                        'TIKTOK' => 'TikTok',
                        default => $state,
                    })
                    ->color(fn (string $state): string => $state === 'SHOPEE' ? 'warning' : 'success')
                    ->description(fn (\App\Models\Order $record): string => $record->store?->name ?? '-'),
                // 4. Omzet (atas) + pemenuhan BERWARNA (bawah).
                TextColumn::make('product_revenue')
                    ->label('Omzet')                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state, \App\Models\Order $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                        '<div>Rp ' . number_format((float) $state, 0, ',', '.') . '</div>'
                        . '<div style="margin-top:3px">' . self::pemenuhanBadge($record->fulfillment)->toHtml() . '</div>'
                    )),
                // 5. Laba (Rp).
                TextColumn::make('profit')
                    ->label('Laba')                    ->formatStateUsing(fn ($state, \App\Models\Order $record): string => (! empty($record->incompleteness()) ? '≈ ' : '') . 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->weight('bold')
                    // Belum final (HPP/dropship/estimasi/settlement) → abu-abu + "≈": jangan terlihat laba pasti.
                    ->color(fn ($state, \App\Models\Order $record): string => ! empty($record->incompleteness()) ? 'gray' : ((float) $state < 0 ? 'danger' : 'success'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        ProfitService::sqlProfit() . ' ' . $direction
                    ))
                    ->alignEnd(),
                // 6. Margin % — kolom sendiri agar BISA DIURUTKAN (klik header).
                TextColumn::make('margin')
                    ->label('Margin')                    ->alignEnd()
                    ->state(function (\App\Models\Order $record): ?float {
                        $omzet = (float) $record->product_revenue;

                        return $omzet > 0 ? $record->profit / $omzet * 100 : null;
                    })
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 0, ',', '.') . '%')
                    ->color(fn (?float $state): string => $state !== null && $state < 0 ? 'danger' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        '(' . ProfitService::sqlProfit() . ') / NULLIF(product_revenue, 0) ' . $direction
                    )),
                // Status laba sebagai IKON ringkas (hemat lebar) — warna & arti tetap, detail di tooltip.
                IconColumn::make('status_laba')
                    ->label('Status Laba')                    ->alignCenter()
                    ->state(fn (\App\Models\Order $record): string => self::statusLaba($record))
                    ->icon(fn (string $state): string => self::statusLabaIcon($state) ?? 'heroicon-m-minus-small')
                    ->color(fn (string $state): string => self::statusLabaColor($state))
                    ->tooltip(function (\App\Models\Order $record): string {
                        if (in_array($record->status, ['CANCELLED', 'RETURNED'], true)) {
                            return 'Pesanan batal/retur — tidak dihitung dalam laba.';
                        }
                        $g = $record->incompleteness();
                        if ($g) {
                            return 'Belum: ' . implode(' · ', $g);
                        }
                        if ($record->lacksItemDetail()) {
                            return 'Laba sudah final & akurat, TAPI rincian item produk belum tercatat (tak memengaruhi laba). Impor File/Laporan Pesanan untuk detail produk.';
                        }

                        return 'Laba final & akurat — biaya asli, modal, & rincian item lengkap.';
                    })
                    ->toggleable(),
            ])
            // PENTING: items di-load TANPA kolom sku (hemat) — cuma butuh count & product_id untuk deskripsi.
            // Akibatnya, kode tabel/bulk yang butuh sku TIDAK boleh andalkan relasi ini ($record->items->...->sku
            // akan null; loadMissing = no-op karena relasi sudah ter-load). Query order_items langsung
            // (lihat bulk "Salin SKU Produk"). itemsTableHtml() aman karena dipakai di halaman View (record terpisah).
            // store ikut di-eager-load: kolom "marketplace" menampilkan nama toko per baris —
            // tanpa ini jadi N+1 (1 query stores per baris; terasa di paginasi 250 + MySQL remote).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['items:id,order_id,product_id', 'store:id,name,marketplace'])->withCount('items'))
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Toko')
                    ->placeholder('Semua toko')
                    ->multiple() // bisa pilih beberapa toko sekaligus (whereIn store_id)
                    ->columnSpan(2) // lebih lebar agar nama toko + channel muat
                    // Label sertakan channel agar jelas toko ini dari mana, mis. "KlikStore — Shopee".
                    ->options(fn (): array => \App\Models\Store::query()->orderBy('name')->get()
                        ->mapWithKeys(fn (\App\Models\Store $s): array => [$s->id => $s->name . ' — ' . $s->channel_label])->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('Semua status')
                    ->multiple() // bisa pilih beberapa status sekaligus, mis. Dibatalkan + Dikirim (whereIn)
                    ->native(false) // pakai select kustom Filament (mulus) — seragam dgn dropdown Toko
                    // Opsi MENYESUAIKAN data: hanya status yang benar-benar ada di pesanan toko ini.
                    ->options(function (): array {
                        $labels = [
                            'COMPLETED' => 'Selesai', 'SHIPPED' => 'Dikirim', 'CANCELLED' => 'Dibatalkan',
                            'RETURNED' => 'Dikembalikan', 'PAID' => 'Dibayar', 'PENDING' => 'Menunggu',
                        ];

                        return \App\Models\Order::query()->distinct()->orderBy('status')->pluck('status')
                            ->mapWithKeys(fn ($s): array => [$s => $labels[$s] ?? $s])->all();
                    }),
                // Channel — pill sekali-klik.
                Filter::make('channel')
                    ->schema([
                        ToggleButtons::make('value')->label('Channel')->hiddenLabel()
                            ->inline()
                            ->options(['semua' => 'Semua channel'] + OrderForm::CHANNEL)->default('semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        fn (Builder $query): Builder => $v === 'SHOPEE'
                            ? $query->where('marketplace', 'SHOPEE')
                            : $query->whereIn('marketplace', ['TIKTOKTOKO', 'TOKOPEDIA', 'TIKTOK']),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($v = $data['value'] ?? null) && $v !== 'semua'
                        ? 'Channel: ' . (OrderForm::CHANNEL[$v] ?? $v) : null),
                // Pemenuhan — pill sekali-klik.
                Filter::make('fulfillment')
                    ->schema([
                        ToggleButtons::make('value')->label('Pemenuhan')->hiddenLabel()
                            ->inline()
                            ->options(['semua' => 'Semua pemenuhan', 'SELF' => 'Packing Sendiri', 'DROPSHIP' => 'Dropship'])->default('semua'),
                    ])
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        fn (Builder $query): Builder => $query->where('fulfillment', $v),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($v = $data['value'] ?? null) && $v !== 'semua'
                        ? 'Pemenuhan: ' . ($v === 'DROPSHIP' ? 'Dropship' : 'Packing Sendiri') : null),
                // Status Laba — pill sekali-klik (cermin kolom Status Laba).
                Filter::make('status_laba')
                    ->schema([
                        ToggleButtons::make('value')->label('Status Laba')->hiddenLabel()
                            ->inline()
                            ->options(['semua' => 'Semua status laba', 'final' => 'Final', 'belum_final' => 'Belum final', 'belum_cair' => 'Belum cair (settlement)', 'laba_semu' => 'Laba semu (HPP kosong)', 'perlu_data' => 'Perlu data (HPP/dropship)', 'estimasi' => 'Estimasi'])->default('semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        fn (Builder $query): Builder => self::applyStatusLaba($query, $v),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($v = $data['value'] ?? null) && $v !== 'semua'
                        ? 'Status laba: ' . ucfirst(str_replace('_', ' ', $v)) : null),
                // Hasil laba — pesanan selesai yang untung / rugi (untuk kartu "Pesanan Rugi" dst).
                Filter::make('hasil_laba')
                    ->schema([
                        ToggleButtons::make('value')->label('Hasil')->hiddenLabel()->inline()
                            ->options(['semua' => 'Semua hasil', 'untung' => 'Untung', 'rugi' => 'Rugi'])->default('semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        fn (Builder $query): Builder => self::applyHasilLaba($query, $v),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($v = $data['value'] ?? null) && $v !== 'semua'
                        ? 'Hasil: ' . ucfirst($v) : null),
                // Jual di bawah modal — harga jual produk < HPP/biaya dropship (anomali harga/biaya).
                Filter::make('bawah_modal')
                    ->schema([
                        ToggleButtons::make('value')->label('Bawah modal')->hiddenLabel()->inline()
                            ->options(['semua' => 'Semua', 'bawah' => 'Jual di bawah modal'])->default('semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($data['value'] ?? null) === 'bawah',
                        fn (Builder $query): Builder => $query->bawahModal(),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['value'] ?? null) === 'bawah'
                        ? 'Jual di bawah modal' : null),
                // Pesanan janggal — pemenuhan tak sesuai mode toko.
                Filter::make('janggal')
                    ->schema([
                        ToggleButtons::make('value')->label('Kejanggalan')->hiddenLabel()->inline()
                            ->options(['semua' => 'Semua', 'janggal' => 'Hanya janggal'])->default('semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($data['value'] ?? null) === 'janggal',
                        fn (Builder $query): Builder => $query->janggal(),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['value'] ?? null) === 'janggal'
                        ? 'Hanya pesanan janggal' : null)
                    // Janggal hanya relevan bila berjualan dropship (sesuai toggle Pengaturan).
                    ->visible(fn (): bool => \App\Models\Organization::currentUsesDropship()),
                // Periode — pill cepat sekali-klik.
                Filter::make('periode')
                    ->schema([
                        ToggleButtons::make('value')->label('Periode')->hiddenLabel()
                            ->inline()
                            ->options(['semua' => 'Semua periode', 'bulan_ini' => 'Bulan ini', '30hari' => '30 hari', '90hari' => '90 hari', 'tahun_ini' => 'Tahun ini'])->default('semua'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($v = $data['value'] ?? null) && $v !== 'semua',
                        fn (Builder $query): Builder => self::applyPeriode($query, $v),
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($v = $data['value'] ?? null) && $v !== 'semua'
                        ? 'Periode: ' . (self::PERIODE[$v] ?? $v) : null),
                // Rentang tanggal kustom (dari–sampai).
                Filter::make('rentang')
                    ->schema([
                        DatePicker::make('dari')->label('Dari')->hiddenLabel()->placeholder('Dari')->native(false),
                        DatePicker::make('sampai')->label('Sampai')->hiddenLabel()->placeholder('Sampai')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('order_date', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('order_date', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $ind = [];
                        if ($data['dari'] ?? null) {
                            $ind[] = 'Dari ' . $data['dari'];
                        }
                        if ($data['sampai'] ?? null) {
                            $ind[] = 'Sampai ' . $data['sampai'];
                        }

                        return $ind;
                    }),
                // Pintasan periode (bulan 'YYYY-MM' atau tahun 'YYYY') — dipakai tautan baris
                // halaman Laporan; tersembunyi (Hidden) tapi ter-hidrasi dari URL seperti filter lain.
                Filter::make('bulan_tahun')
                    ->schema([
                        \Filament\Forms\Components\Hidden::make('value'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        function (Builder $q, $v): Builder {
                            // Operasional (cocok dgn angka di halaman Laporan).
                            $q->whereNotIn('status', ['CANCELLED', 'RETURNED']);
                            if (preg_match('/^(\d{4})-(\d{2})$/', (string) $v, $m)) {
                                return $q->whereYear('order_date', $m[1])->whereMonth('order_date', (int) $m[2]);
                            }
                            if (preg_match('/^(\d{4})$/', (string) $v, $m)) {
                                return $q->whereYear('order_date', $m[1]);
                            }

                            return $q;
                        },
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($v = $data['value'] ?? null)
                        ? 'Periode: ' . $v : null),
                // Pintasan SARAN IMPOR (tersembunyi) — dipakai link "Lihat pesanan" di panel "File yang
                // perlu diupload". Query MENIRU PERSIS App\Services\ImportSuggestion agar JUMLAHNYA COCOK.
                Filter::make('saran')
                    ->schema([
                        \Filament\Forms\Components\Hidden::make('value'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        function (Builder $q, $v): Builder {
                            $q->whereNotIn('status', ['CANCELLED', 'RETURNED']);
                            $noDropship = fn (Builder $q): Builder => $q->whereNotExists(function ($s): void {
                                $s->selectRaw('1')->from('dropship_costs as dc')
                                    ->whereColumn('dc.external_no', 'orders.external_no')
                                    ->whereColumn('dc.organization_id', 'orders.organization_id');
                            });

                            return match ($v) {
                                'income' => $q->where('income_verified', false),
                                'no_item' => $q->doesntHave('items'),
                                'hpp' => $q->where('fulfillment', 'SELF')->where('product_revenue', '>', 0)->where('cogs', '<=', 0)->has('items'),
                                'dropship' => $noDropship($q->where('fulfillment', 'SELF')->whereHas('store', fn ($s) => $s->where('fulfillment_mode', 'dropship'))),
                                'dropship_cost' => $q->where('fulfillment', 'DROPSHIP')->where('dropship_cost', '<=', 0),
                                default => $q,
                            };
                        },
                    ))
                    ->indicateUsing(fn (array $data): ?string => match ($data['value'] ?? null) {
                        'income' => 'Butuh Laporan Penghasilan (biaya estimasi)',
                        'no_item' => 'Belum ada rincian item (File Pesanan)',
                        'hpp' => 'Modal/HPP belum ada (produk sudah tercatat)',
                        'dropship' => 'Belum ada data dropship',
                        'dropship_cost' => 'Biaya dropship belum terisi',
                        default => null,
                    }),
            ])
            // Filter tampil DI ATAS tabel (tak menutup data), ringkas & padat (sampai 4 kolom),
            // berlaku seketika. AboveContent agar user langsung lihat filter tersedia.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 2, 'md' => 3, 'lg' => 4])
            ->deferFilters(false)
            // Klik baris → Lihat. No. Pesanan dikecualikan (->disabledClick di kolomnya) agar bisa DISALIN, bukan navigasi.
            ->recordUrl(fn (\App\Models\Order $record): string => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record]))
            // Opsi jumlah per halaman lebih besar → mudah centang banyak sekaligus. Untuk memilih
            // SEMUA hasil filter (lintas halaman), centang kotak header lalu klik "Pilih semua".
            ->paginated([25, 50, 100, 250])
            ->toolbarActions([
                BulkActionGroup::make([
                    \App\Filament\Actions\CopyBulkAction::make('salinNoPesanan', 'Salin No. Pesanan', 'external_no', 'No. Pesanan'),
                    \App\Filament\Actions\CopyBulkAction::make('salinSkuPesanan', 'Salin SKU Produk', fn ($records) => \App\Models\OrderItem::query()->whereIn('order_id', $records->pluck('id'))->pluck('sku'), 'SKU', 'Pesanan terpilih belum punya rincian produk. Impor "File Pesanan" untuk pesanan tersebut dulu agar SKU-nya tersedia.'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Status Laba sebuah pesanan (SUMBER TUNGGAL untuk kolom & dipakai verifikasi).
     * '—' utk batal/retur (laba tak relevan), selain itu cermin incompleteness().
     */
    public static function statusLaba(\App\Models\Order $record): string
    {
        if (in_array($record->status, ['CANCELLED', 'RETURNED'], true)) {
            return '—';
        }
        $gaps = $record->incompleteness();
        if (empty($gaps)) {
            return $record->lacksItemDetail() ? 'Final*' : 'Final';
        }
        // Hanya menunggu pencairan settlement (data lain lengkap) → status khusus "Belum cair".
        $nonSettlement = array_filter($gaps, fn (string $g): bool => ! str_contains($g, 'Settlement'));
        if (empty($nonSettlement)) {
            return 'Belum cair';
        }
        $dataGaps = array_filter($nonSettlement, fn (string $g): bool => ! str_contains($g, 'ESTIMASI'));

        return $dataGaps ? 'Perlu data' : 'Estimasi';
    }

    /** Warna badge Status Laba — dipakai bersama oleh tabel & halaman detail (anti-melenceng). */
    public static function statusLabaColor(string $state): string
    {
        return match ($state) {
            'Final', 'Final*' => 'success',
            'Perlu data' => 'warning',
            'Belum cair' => 'info',
            default => 'gray', // 'Estimasi' & '—' (batal/retur)
        };
    }

    /** Ikon badge Status Laba — dipakai bersama oleh tabel & halaman detail. */
    public static function statusLabaIcon(string $state): ?string
    {
        return match ($state) {
            'Final' => 'heroicon-m-check-circle',
            'Final*' => 'heroicon-m-information-circle',
            'Perlu data' => 'heroicon-m-exclamation-triangle',
            'Belum cair' => 'heroicon-m-banknotes',
            'Estimasi' => 'heroicon-m-clock',
            default => null, // '—' tanpa ikon
        };
    }

    /** Badge berwarna (inline) — dipakai utk status & pemenuhan di sub-baris sel. */
    private static function badge(string $text, string $color): \Illuminate\Support\HtmlString
    {
        [$bg, $fg] = match ($color) {
            'success' => ['#dcfce7', '#166534'],
            'danger' => ['#fee2e2', '#991b1b'],
            'warning' => ['#fef3c7', '#92400e'],
            'info' => ['#dbeafe', '#1e40af'],
            default => ['#f1f5f9', '#475569'],
        };

        return new \Illuminate\Support\HtmlString(
            '<span style="display:inline-block;font-size:11px;line-height:1.45;padding:1px 8px;border-radius:6px;background:' . $bg . ';color:' . $fg . '">' . e($text) . '</span>'
        );
    }

    /** Badge status pesanan (warna sesuai arti, seperti badge channel). */
    public static function statusBadge(string $status): \Illuminate\Support\HtmlString
    {
        $label = match ($status) {
            'COMPLETED' => 'Selesai', 'CANCELLED' => 'Dibatalkan', 'RETURNED' => 'Dikembalikan',
            'SHIPPED' => 'Dikirim', 'PAID' => 'Dibayar', 'PENDING' => 'Menunggu', default => $status,
        };
        $color = match ($status) {
            'COMPLETED' => 'success', 'CANCELLED' => 'danger', 'RETURNED' => 'warning', 'SHIPPED' => 'info', default => 'gray',
        };

        return self::badge($label, $color);
    }

    /** Badge pemenuhan (berwarna). */
    public static function pemenuhanBadge(string $fulfillment): \Illuminate\Support\HtmlString
    {
        return $fulfillment === 'DROPSHIP' ? self::badge('Dropship', 'info') : self::badge('Packing Sendiri', 'gray');
    }

    /** Tabel item produk (Produk/SKU/Qty/Harga/HPP/Subtotal) — dipakai halaman Lihat & Ubah pesanan (ala v1). */
    public static function itemsTableHtml(\App\Models\Order $record): \Illuminate\Support\HtmlString
    {
        $items = $record->items;
        $th = 'text-align:left;padding:.5rem .6rem;font-size:.72rem;color:#64748b;text-transform:uppercase;border-bottom:1px solid #eef2f7';
        $thR = $th . ';text-align:right';
        $td = 'padding:.5rem .6rem;font-size:.85rem;border-top:1px solid #f1f5f9';
        $tdR = $td . ';text-align:right';
        $rp = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');

        if ($items->isEmpty()) {
            $rows = '<tr><td colspan="6" style="' . $td . ';text-align:center;color:#94a3b8;padding:1rem">'
                . 'Rincian produk belum tersedia — impor file pesanan (Order Completed / Pesanan Selesai) periode sama agar produk, SKU, qty &amp; HPP terisi otomatis.</td></tr>';
        } else {
            $rows = '';
            foreach ($items as $it) {
                $warn = ($it->sku && ! $it->product_id) ? ' <span title="SKU belum ada di katalog" style="color:#d97706">&#9888;</span>' : '';
                $assumed = ! empty($it->qty_assumed) ? ' <span title="Qty diasumsikan 1 dari Laporan Penghasilan" style="color:#d97706">&#8776;</span>' : '';
                $rows .= '<tr>'
                    . '<td style="' . $td . ';font-weight:600">' . e($it->name) . '</td>'
                    . '<td style="' . $td . ';font-family:monospace;font-size:.75rem;color:#64748b">' . e($it->sku ?: '—') . $warn . '</td>'
                    . '<td style="' . $tdR . '">' . (int) $it->qty . $assumed . '</td>'
                    . '<td style="' . $tdR . '">' . $rp($it->unit_price) . '</td>'
                    . '<td style="' . $tdR . '">' . $rp($it->unit_cost) . '</td>'
                    . '<td style="' . $tdR . ';font-weight:700">' . $rp($it->unit_price * $it->qty) . '</td>'
                    . '</tr>';
            }
        }

        return new \Illuminate\Support\HtmlString(
            '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">'
            . '<thead><tr><th style="' . $th . '">Produk</th><th style="' . $th . '">SKU</th>'
            . '<th style="' . $thR . '">Qty</th><th style="' . $thR . '">Harga</th><th style="' . $thR . '">HPP</th><th style="' . $thR . '">Subtotal</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
        );
    }

    /**
     * Query filter "Status Laba" — MENCERMINKAN persis self::statusLaba()/incompleteness().
     * Batal & retur (yang di kolom jadi '—') otomatis dikecualikan dari semua opsi.
     */
    private static function applyStatusLaba(Builder $q, string $v): Builder
    {
        // "gap data" = modal/HPP atau biaya dropship belum ada → laba belum akurat ("Perlu data").
        $dataGap = function (Builder $q): void {
            $q->where(fn (Builder $q) => $q->where('fulfillment', 'SELF')->where('product_revenue', '>', 0)->where('cogs', '<=', 0))
                ->orWhere(fn (Builder $q) => $q->where('fulfillment', 'DROPSHIP')->where('dropship_cost', '<=', 0));
        };
        // Selesai/verified tapi settlement belum cair (net <= 0) → "Belum cair".
        $belumCair = function (Builder $q): void {
            $q->where('income_verified', true)->where('product_revenue', '>', 0)->whereRaw(ProfitService::SQL_NET . ' <= 0');
        };
        $aktif = fn (Builder $q): Builder => $q->whereNotIn('status', ['CANCELLED', 'RETURNED']);

        return match ($v) {
            // "Final" MENCAKUP Final & Final* — verified, data lengkap, & settlement sudah cair.
            'final' => $aktif($q)->where('income_verified', true)->whereNot($dataGap)->whereNot($belumCair),
            'laba_semu' => $q->labaSemu(), // SELF, omzet>0, HPP kosong (cocok kartu "Laba Semu")
            'perlu_data' => $aktif($q)->where($dataGap),
            // "Belum cair" hanya bila settlement satu-satunya kekurangan (cermin prioritas statusLaba():
            // bila ada gap HPP/dropship, labelnya "Perlu data"). Sejalan dgn cabang 'final'.
            'belum_cair' => $aktif($q)->where($belumCair)->whereNot($dataGap),
            'estimasi' => $aktif($q)->where('income_verified', false)->whereNot($dataGap),
            // "Belum final" = gabungan perlu_data + estimasi + belum_cair (laba belum pasti).
            'belum_final' => $aktif($q)->where(fn (Builder $q) => $q->where($dataGap)->orWhere('income_verified', false)->orWhere($belumCair)),
            default => $q,
        };
    }

    /** Pesanan SELESAI yang untung / rugi (dipakai kartu "Pesanan Rugi" dll). */
    private static function applyHasilLaba(Builder $q, string $v): Builder
    {
        $pf = ProfitService::sqlProfit();

        return match ($v) {
            'rugi' => $q->where('status', 'COMPLETED')->whereRaw("($pf) < 0"),
            'untung' => $q->where('status', 'COMPLETED')->whereRaw("($pf) > 0"),
            default => $q,
        };
    }

    /** Terapkan preset periode ke query — filter cepat tanpa pilih tanggal manual. */
    private static function applyPeriode(Builder $q, string $v): Builder
    {
        return match ($v) {
            'minggu_ini' => $q->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]),
            'bulan_ini' => $q->whereBetween('order_date', [now()->startOfMonth(), now()->endOfMonth()]),
            'tahun_ini' => $q->whereBetween('order_date', [now()->startOfYear(), now()->endOfYear()]),
            '30hari' => $q->where('order_date', '>=', now()->subDays(30)->startOfDay()),
            '90hari' => $q->where('order_date', '>=', now()->subDays(90)->startOfDay()),
            'minggu_lalu' => $q->whereBetween('order_date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
            'bulan_lalu' => $q->whereBetween('order_date', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()]),
            'tahun_lalu' => $q->whereBetween('order_date', [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()]),
            default => $q,
        };
    }
}
