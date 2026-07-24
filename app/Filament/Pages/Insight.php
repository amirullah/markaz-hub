<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ProfitService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class Insight extends Page
{
    protected string $view = 'filament.pages.insight';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Insight';

    protected static ?string $title = 'Insight & Produk Merugi';

    protected static ?int $navigationSort = 7;

    /** Detail produk yang sedang dibuka (klik baris). */
    public ?array $detail = null;

    public function getViewData(): array
    {
        $profit = ProfitService::sqlProfit();

        $pesananRugi = Order::query()
            ->where('status', 'COMPLETED')
            ->whereRaw("($profit) < 0")
            ->orderByRaw("($profit) asc")
            ->limit(25)->get();

        // Produk yang HARGA JUAL-nya masih DI BAWAH MODAL — pakai data TERKINI (bukan modal beku):
        // ambil pesanan TERBARU tiap produk → harga jual terkini vs modal terkini (modal katalog
        // untuk packing sendiri, biaya dropship pesanan terbaru untuk dropship). Jadi bila harga
        // sudah dinaikkan ATAU modal sudah turun, produk OTOMATIS hilang dari daftar.
        $orgId = (int) auth()->user()->organization_id;
        $catCost = Product::query()->pluck('cost_price', 'sku')->all();
        // Ambil semua penjualan (urut terbaru dulu), lalu pakai BARIS PERTAMA tiap SKU =
        // pesanan terbaru (tanpa window function, aman di MySQL versi lama).
        $rows = DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('o.organization_id', $orgId)
            ->whereNotIn('o.status', ['CANCELLED', 'RETURNED'])
            ->where('o.product_revenue', '>', 0)
            ->whereNotNull('i.sku')->where('i.sku', '!=', '')
            ->orderByDesc('o.order_date')->orderByDesc('o.id')
            ->get(['i.order_id', 'i.sku', 'i.name', 'i.unit_price', 'i.qty', 'o.fulfillment', 'o.dropship_cost', 'o.order_date']);
        // Total qty & jumlah jenis produk per pesanan (biaya dropship = level pesanan).
        $ordQty = [];
        $ordSku = [];
        foreach ($rows as $r) {
            $ordQty[$r->order_id] = ($ordQty[$r->order_id] ?? 0) + (int) $r->qty;
            $ordSku[$r->order_id][$r->sku] = true;
        }
        $seen = [];
        $bawahModal = collect();
        foreach ($rows as $r) {
            if (isset($seen[$r->sku])) {
                continue; // sudah diambil yang terbaru utk SKU ini
            }
            $seen[$r->sku] = true;
            $jual = (float) $r->unit_price;
            if ($r->fulfillment === 'DROPSHIP') {
                // Biaya dropship per-unit hanya bisa diatribusi bila pesanan 1 JENIS produk.
                if (count($ordSku[$r->order_id]) !== 1) {
                    continue;
                }
                $modal = (float) $r->dropship_cost / max($ordQty[$r->order_id], 1);
            } else {
                $modal = (float) ($catCost[$r->sku] ?? 0); // modal katalog TERKINI
            }
            if ($modal > 0 && $jual > 0 && $jual < $modal) {
                $bawahModal->push((object) [
                    'sku' => $r->sku, 'name' => (string) $r->name, 'jual' => $jual, 'modal' => $modal,
                    'selisih' => $modal - $jual, 'fulfillment' => $r->fulfillment, 'tgl' => (string) $r->order_date,
                ]);
            }
        }
        $bawahModal = $bawahModal->sortByDesc('selisih')->values()->take(25);

        // Produk PALING UNTUNG (margin × qty), pesanan selesai.
        $palingUntung = OrderItem::query()
            ->whereColumn('unit_price', '>', 'unit_cost')
            ->where('unit_cost', '>', 0)
            ->whereNotNull('sku')
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED'))
            ->selectRaw('sku, MAX(name) AS name, SUM(qty) AS qty_terjual, ROUND(SUM((unit_price - unit_cost) * qty)) AS total_untung')
            ->groupBy('sku')
            ->orderByDesc('total_untung')
            ->limit(10)->get();

        $terlaris = OrderItem::query()
            ->whereNotNull('sku')
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED'))
            ->selectRaw('sku, MAX(name) AS name, SUM(qty) AS total_qty')
            ->groupBy('sku')->orderByDesc('total_qty')
            ->limit(20)->get();

        // Statistik
        $selesai = Order::query()->where('status', 'COMPLETED');
        $totalLaba = (float) (clone $selesai)->sum(DB::raw($profit));
        $totalOmzet = (float) (clone $selesai)->sum(DB::raw('product_revenue + other_income'));
        $margin = $totalOmzet > 0 ? round($totalLaba / $totalOmzet * 100, 1) : 0;
        $jmlRugi = (int) (clone $selesai)->whereRaw("($profit) < 0")->count();
        $nilaiRugi = (float) (clone $selesai)->whereRaw("($profit) < 0")->sum(DB::raw($profit));
        $totalPesanan = Order::query()->count();
        $jmlRetur = Order::query()->where('status', 'RETURNED')->count();
        $jmlBatal = Order::query()->where('status', 'CANCELLED')->count();
        $rasioRetur = $totalPesanan > 0 ? round($jmlRetur / $totalPesanan * 100, 1) : 0;
        $jmlProdukRugi = $bawahModal->count();

        // KENAPA rugi — 1 sebab dominan per pesanan (saling lepas, total = $jmlRugi).
        $sb = (clone $selesai)->whereRaw("($profit) < 0")->selectRaw(
            'SUM(CASE WHEN cogs > product_revenue THEN 1 ELSE 0 END) AS bawah_modal,'
            . ' SUM(CASE WHEN NOT (cogs > product_revenue) AND product_revenue > 0 AND admin_fee / product_revenue > 0.3 THEN 1 ELSE 0 END) AS admin_tinggi,'
            . ' SUM(CASE WHEN NOT (cogs > product_revenue) AND NOT (product_revenue > 0 AND admin_fee / product_revenue > 0.3) AND (voucher_seller_borne + shipping_cost_seller) > product_revenue * 0.2 THEN 1 ELSE 0 END) AS voucher_besar'
        )->first();
        $sebab = [
            'bawahModal' => (int) ($sb->bawah_modal ?? 0),
            'adminTinggi' => (int) ($sb->admin_tinggi ?? 0),
            'voucherBesar' => (int) ($sb->voucher_besar ?? 0),
        ];
        $sebab['marginTipis'] = max(0, $jmlRugi - $sebab['bawahModal'] - $sebab['adminTinggi'] - $sebab['voucherBesar']);

        // "Laba semu" (HPP kosong) — laba ter-overstate sampai HPP diimpor; dipakai utk tindakan.
        $labaSemu = (int) Order::query()->labaSemu()->count();

        // URL pintasan untuk tombol tindakan + kartu (filter auto-terpilih lewat URL).
        $urlOrders = \App\Filament\Resources\Orders\OrderResource::getUrl('index');
        $f = fn (array $filters): string => $urlOrders . '?' . http_build_query(['filters' => $filters]);
        $urlLabaSemu = $f(['status_laba' => ['value' => 'laba_semu']]);
        $urlSelesai = $f(['status' => ['values' => ['COMPLETED']]]);
        $urlRugi = $f(['hasil_laba' => ['value' => 'rugi']]);
        $urlRetur = $f(['status' => ['values' => ['RETURNED']]]);
        $urlImpor = \App\Filament\Pages\ImportData::getUrl();
        $urlProduk = \App\Filament\Resources\Products\ProductResource::getUrl('index');

        // Pipeline processing stats.
        $pipeline = [
            'baru' => Order::query()->where('processing_status', 'PENDING')->count(),
            'diproses' => Order::query()->where('processing_status', 'PROCESSING')->count(),
            'dikemas' => Order::query()->where('processing_status', 'PACKED')->count(),
            'dikirim' => Order::query()->where('processing_status', 'SHIPPED')->count(),
            'gagal' => Order::query()->where('processing_status', 'FAILED')->count(),
        ];

        return compact(
            'pesananRugi', 'bawahModal', 'palingUntung', 'terlaris',
            'jmlRugi', 'nilaiRugi', 'jmlRetur', 'jmlBatal', 'rasioRetur', 'totalPesanan',
            'totalLaba', 'margin', 'jmlProdukRugi',
            'sebab', 'labaSemu', 'urlOrders', 'urlLabaSemu', 'urlImpor', 'urlProduk',
            'urlSelesai', 'urlRugi', 'urlRetur',
            'pipeline',
        );
    }

    /** Buka detail satu produk (dipanggil saat baris diklik). */
    public function showDetail(string $sku): void
    {
        $items = OrderItem::query()->where('sku', $sku)
            ->where('unit_cost', '>', 0)->where('unit_price', '>', 0)->get();
        if ($items->isEmpty()) {
            return;
        }
        $product = Product::query()->where('sku', $sku)->with('category')->first();
        $rugi = $items->filter(fn ($i) => (float) $i->unit_price < (float) $i->unit_cost);

        $this->detail = [
            'sku' => $sku,
            'name' => (string) $items->first()->name,
            'kategori' => $product?->category?->name,
            'hpp' => $product ? (float) $product->cost_price : null,
            'total_terjual' => (int) $items->sum('qty'),
            'transaksi_rugi' => $rugi->count(),
            'qty_rugi' => (int) $rugi->sum('qty'),
            'total_rugi' => (float) $rugi->sum(fn ($i) => ((float) $i->unit_cost - (float) $i->unit_price) * (int) $i->qty),
            'avg_jual' => round((float) $items->avg('unit_price')),
            'avg_modal' => round((float) $items->avg('unit_cost')),
        ];
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }
}
