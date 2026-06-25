<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Organization;

/**
 * Estimasi biaya marketplace untuk pesanan yang BELUM punya Laporan Penghasilan.
 * Meniru struktur biaya nyata 2026 yang punya BEBERAPA komponen:
 *
 *   Shopee          = Biaya Administrasi (komisi % kategori)
 *                   + Biaya Layanan (% dari subtotal, ada batas)
 *                   + Biaya Proses Pesanan (Rp1.250)
 *   Tokopedia/TikTok= Komisi platform (komisi % kategori)
 *                   + Komisi Dinamis (% dari subtotal)
 *                   + Biaya Proses Pesanan (Rp1.250)
 *
 * Aturan kelengkapan:
 *  - Pesanan tanpa omzet (mis. dibatalkan, product_revenue = 0) → biaya 0 (tak ada transaksi).
 *  - Produk tanpa kategori / SKU tak dikenal → pakai tarif komisi DEFAULT (rata-rata tarif
 *    kategori org) agar pesanan tetap diestimasi (tidak ada pesanan berjalan yang terlewat).
 *  - Saat Laporan Penghasilan resmi masuk, biaya admin asli menggantikan estimasi (income_verified).
 */
class AdminFeeEstimator
{
    /** Biaya proses pesanan per pesanan (Rp). Resmi 2026: Shopee & Tokopedia/TikTok = Rp1.250. */
    public const ORDER_PROCESSING_FEE = 1250.0;

    /** Tarif komisi cadangan bila org belum punya kategori sama sekali (%). */
    public const DEFAULT_COMMISSION_PCT = 8.0;

    /** Kalibrasi UTAMAKAN data N bulan terakhir (biaya lama bisa beda); fallback semua data bila kurang. */
    public const CALIBRATION_RECENT_MONTHS = 6;

    /** Estimasi biaya marketplace satu pesanan (Rp). $fees = setelan biaya org (opsional, untuk batch). */
    public function estimate(Order $order, ?array $fees = null): float
    {
        // Tanpa omzet (dibatalkan / belum ada nilai) → tidak ada biaya.
        $revenue = (float) $order->product_revenue;
        if ($revenue <= 0) {
            return 0.0;
        }

        $order->loadMissing('items.product.category');
        $fees ??= $this->feesForOrg((int) $order->organization_id);
        $isShopee = $order->marketplace === 'SHOPEE';
        $defaultRate = $isShopee ? $fees['default_shopee_pct'] : $fees['default_tokotiktok_pct'];

        // Tarif komisi rata-rata tertimbang dari item (kategori → tarif kategori; tak dikenal → tarif default).
        $base = 0.0;
        $weighted = 0.0;
        foreach ($order->items as $item) {
            $itemRev = (float) $item->qty * (float) $item->unit_price;
            if ($itemRev <= 0) {
                continue;
            }
            $category = $item->product?->category;
            $rate = $category ? $category->feeForMarketplace($order->marketplace) : $defaultRate;
            $base += $itemRev;
            $weighted += $itemRev * $rate;
        }
        $commissionRate = $base > 0 ? $weighted / $base : $defaultRate;
        $commission = $revenue * $commissionRate / 100;

        // Komponen % kedua: Biaya Layanan (Shopee, ada batas) atau Komisi Dinamis (Tokped/TikTok).
        if ($isShopee) {
            $extra = $revenue * $fees['shopee_service_pct'] / 100;
            if ($fees['shopee_service_cap'] > 0) {
                $extra = min($extra, $fees['shopee_service_cap']);
            }
        } else {
            $extra = $revenue * $fees['tokotiktok_dynamic_pct'] / 100;
        }

        return round($commission + $extra + self::ORDER_PROCESSING_FEE, 2);
    }

    /** Setelan biaya tambahan + tarif default per-organisasi (default aman bila kolom/baris belum ada). */
    public function feesForOrg(int $orgId): array
    {
        // Jaminan kategori ada (sama spt OrderImporter) — mis. org di-restore tanpa kategori.
        if (! Category::withoutGlobalScopes()->where('organization_id', $orgId)->exists()) {
            app(DefaultCategories::class)->seedForOrg($orgId);
        }

        $org = Organization::find($orgId);
        $avgShopee = (float) Category::withoutGlobalScopes()->where('organization_id', $orgId)->avg('fee_shopee');
        $avgToko = (float) Category::withoutGlobalScopes()->where('organization_id', $orgId)->avg('fee_tokotiktok');

        return [
            'shopee_service_pct' => (float) ($org->fee_shopee_service_pct ?? 10),
            'shopee_service_cap' => (float) ($org->fee_shopee_service_cap ?? 10000),
            'tokotiktok_dynamic_pct' => (float) ($org->fee_tokotiktok_dynamic_pct ?? 6.5),
            'default_shopee_pct' => $avgShopee > 0 ? $avgShopee : self::DEFAULT_COMMISSION_PCT,
            'default_tokotiktok_pct' => $avgToko > 0 ? $avgToko : self::DEFAULT_COMMISSION_PCT,
        ];
    }

    /** Pesanan layak diestimasi: belum punya Laporan Penghasilan resmi. */
    public function isEligible(Order $order): bool
    {
        return ! $order->income_verified;
    }

    /**
     * Tarif biaya EFEKTIF per channel (%) dari pesanan ber-Laporan Penghasilan, TERTIMBANG OMZET:
     * (Σadmin_fee − Rp1.250×n) / Σproduct_revenue × 100. UTAMAKAN data N bulan terakhir
     * (fallback semua data bila < $minOrders pesanan). null bila belum ada data.
     *
     * SUMBER TUNGGAL angka "Tarif efektif rata-rata" — dipakai notifikasi kalibrasi DAN halaman
     * Pengaturan, agar keduanya selalu sama. Mengembalikan nilai MENTAH (belum dibulatkan).
     *
     * @return array{SHOPEE: float|null, TIKTOKTOKO: float|null}
     */
    public function effectiveChannelRates(int $orgId, int $minOrders = 30): array
    {
        $verified = fn (string $ch) => Order::withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('income_verified', true)
            ->where('marketplace', $ch)->where('status', 'COMPLETED')
            ->where('product_revenue', '>', 0)->where('admin_fee', '>', 0);

        $recentSince = now()->subMonths(self::CALIBRATION_RECENT_MONTHS)->toDateString();
        $aggSql = 'count(*) n, sum(product_revenue) rev, sum(admin_fee) fee';
        $rateOf = fn ($row) => ($row && $row->n > 0 && $row->rev > 0)
            ? max(0.0, ($row->fee - self::ORDER_PROCESSING_FEE * $row->n) / $row->rev * 100) : null;

        $rates = [];
        foreach (['SHOPEE', 'TIKTOKTOKO'] as $ch) {
            $recent = $verified($ch)->where('order_date', '>=', $recentSince)->selectRaw($aggSql)->first();
            $use = ($recent && $recent->n >= $minOrders) ? $recent : $verified($ch)->selectRaw($aggSql)->first();
            $rates[$ch] = $rateOf($use);
        }

        return $rates;
    }

    /**
     * KALIBRASI tarif dari data NYATA: untuk pesanan yang sudah punya Laporan Penghasilan
     * (income_verified, biaya asli), hitung tarif biaya EFEKTIF (admin_fee/omzet) lalu
     * tulis ke tarif per kategori. Tarif kategori jadi tarif ALL-IN (komisi + biaya layanan +
     * komisi dinamis sudah termasuk), maka komponen tambahan (service/dynamic) DINOLKAN agar
     * tak dobel. Per-kategori dipakai bila datanya cukup (single-kategori ≥ $minOrders), selain
     * itu pakai rata-rata channel. Hanya pesanan COMPLETED beromzet & berbiaya yang dipakai.
     */
    public function calibrateFromIncome(int $orgId, int $minOrders = 30): array
    {
        // Tarif efektif per channel — SUMBER TUNGGAL (sama persis dgn yang ditampilkan halaman Pengaturan).
        $channelRate = $this->effectiveChannelRates($orgId, $minOrders);
        $recentSince = now()->subMonths(self::CALIBRATION_RECENT_MONTHS)->toDateString(); // dipakai per-kategori di bawah

        // Belum ada data Laporan Penghasilan sama sekali → JANGAN ubah apa pun (no-op aman).
        if (($channelRate['SHOPEE'] ?? null) === null && ($channelRate['TIKTOKTOKO'] ?? null) === null) {
            return ['categories' => 0, 'from_data' => 0, 'shopee_avg' => 0, 'tokotiktok_avg' => 0, 'reestimated' => 0, 'total' => 0.0];
        }

        // Tarif per (channel, kategori) dari pesanan single-kategori.
        $orders = Order::withoutGlobalScopes()->where('organization_id', $orgId)
            ->where('income_verified', true)->where('status', 'COMPLETED')
            ->where('product_revenue', '>', 0)->where('admin_fee', '>', 0)
            ->select('id', 'marketplace', 'product_revenue', 'admin_fee', 'order_date')->get();

        $catSet = [];
        foreach ($orders->pluck('id')->chunk(3000) as $chunk) {
            $rows = \DB::table('order_items as oi')->join('products as p', 'p.id', '=', 'oi.product_id')
                ->whereIn('oi.order_id', $chunk->all())->whereNotNull('p.category_id')
                ->select('oi.order_id', 'p.category_id')->distinct()->get();
            foreach ($rows as $x) {
                $catSet[$x->order_id][$x->category_id] = true;
            }
        }

        // Kumpulkan dua ember: SEMUA & TERBARU (≤ N bulan). Pilih terbaru bila cukup.
        $agg = [];
        $aggRecent = [];
        foreach ($orders as $o) {
            $cats = array_keys($catSet[$o->id] ?? []);
            if (count($cats) !== 1) {
                continue; // hanya pesanan satu-kategori (atribusi bersih)
            }
            $key = $o->marketplace . '|' . $cats[0];
            $rev = (float) $o->product_revenue;
            $fee = (float) $o->admin_fee;
            $agg[$key]['n'] = ($agg[$key]['n'] ?? 0) + 1;
            $agg[$key]['rev'] = ($agg[$key]['rev'] ?? 0) + $rev;
            $agg[$key]['fee'] = ($agg[$key]['fee'] ?? 0) + $fee;
            if ((string) $o->order_date >= $recentSince) {
                $aggRecent[$key]['n'] = ($aggRecent[$key]['n'] ?? 0) + 1;
                $aggRecent[$key]['rev'] = ($aggRecent[$key]['rev'] ?? 0) + $rev;
                $aggRecent[$key]['fee'] = ($aggRecent[$key]['fee'] ?? 0) + $fee;
            }
        }
        $perCat = [];
        foreach ($agg as $key => $all) {
            $src = (isset($aggRecent[$key]) && $aggRecent[$key]['n'] >= $minOrders) ? $aggRecent[$key] : $all;
            if ($src['n'] < $minOrders || $src['rev'] <= 0) {
                continue;
            }
            [$ch, $cid] = explode('|', $key);
            $perCat[$cid][$ch] = max(0.0, ($src['fee'] - self::ORDER_PROCESSING_FEE * $src['n']) / $src['rev'] * 100);
        }

        // Tulis tarif ke tiap kategori (per-kategori bila ada; selain itu rata-rata channel).
        $cats = 0;
        $detailCats = 0;
        foreach (\DB::table('categories')->where('organization_id', $orgId)->get() as $cat) {
            $s = $perCat[$cat->id]['SHOPEE'] ?? $channelRate['SHOPEE'] ?? (float) $cat->fee_shopee;
            $t = $perCat[$cat->id]['TIKTOKTOKO'] ?? $channelRate['TIKTOKTOKO'] ?? (float) $cat->fee_tokotiktok;
            \DB::table('categories')->where('id', $cat->id)->update([
                'fee_shopee' => round($s, 2), 'fee_tokotiktok' => round($t, 2), 'updated_at' => now(),
            ]);
            $cats++;
            if (isset($perCat[$cat->id])) {
                $detailCats++;
            }
        }

        // Tarif kategori kini ALL-IN → nolkan komponen tambahan agar tak dobel-hitung.
        \DB::table('organizations')->where('id', $orgId)->update([
            'fee_shopee_service_pct' => 0, 'fee_tokotiktok_dynamic_pct' => 0, 'updated_at' => now(),
        ]);

        $re = $this->applyToOrg($orgId);

        return [
            'categories' => $cats,
            'from_data' => $detailCats,
            'shopee_avg' => round($channelRate['SHOPEE'] ?? 0, 2),
            'tokotiktok_avg' => round($channelRate['TIKTOKTOKO'] ?? 0, 2),
            'reestimated' => $re['updated'],
            'total' => $re['total'],
        ];
    }

    /**
     * Hitung ulang estimasi untuk SEMUA pesanan org yang BELUM final (overwrite estimasi lama
     * agar formula terbaru diterapkan; pesanan batal otomatis jadi 0). Pesanan dengan Laporan
     * Penghasilan (income_verified) TIDAK disentuh. saveQuietly agar tak membanjiri log.
     */
    public function applyToOrg(int $orgId): array
    {
        $fees = $this->feesForOrg($orgId);

        $orders = Order::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('income_verified', false)
            ->with('items.product.category')
            ->get();

        $updated = 0;
        $total = 0.0;

        foreach ($orders as $order) {
            $estimate = $this->estimate($order, $fees);
            if ((float) $order->admin_fee !== $estimate) {
                $order->admin_fee = $estimate;
                $order->saveQuietly();
            }
            if ($estimate > 0) {
                $updated++;
                $total += $estimate;
            }
        }

        return ['updated' => $updated, 'total' => round($total, 2), 'eligible' => $orders->count()];
    }
}
