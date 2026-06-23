<?php

namespace App\Services;

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
 * Komisi % kategori diatur di menu Kategori; Biaya Layanan / Komisi Dinamis (+ batas)
 * diatur per-organisasi di menu Pengaturan. Saat Laporan Penghasilan resmi masuk,
 * biaya admin asli menggantikan estimasi ini (income_verified jadi true).
 */
class AdminFeeEstimator
{
    /** Biaya proses pesanan per pesanan (Rp). Resmi 2026: Shopee & Tokopedia/TikTok = Rp1.250. */
    public const ORDER_PROCESSING_FEE = 1250.0;

    /** Estimasi biaya marketplace satu pesanan (Rp). $fees = setelan biaya org (opsional, untuk batch). */
    public function estimate(Order $order, ?array $fees = null): float
    {
        $order->loadMissing('items.product.category');
        $fees ??= $this->feesForOrg((int) $order->organization_id);

        $subtotal = 0.0;
        $commission = 0.0;
        $hasCategory = false;

        foreach ($order->items as $item) {
            $revenue = (float) $item->qty * (float) $item->unit_price;
            $subtotal += $revenue;
            $category = $item->product?->category;
            if ($category) {
                $hasCategory = true;
                $commission += $revenue * $category->feeForMarketplace($order->marketplace) / 100;
            }
        }

        // Tanpa kategori sama sekali → komisi tak diketahui → jangan estimasi (biar 0).
        if (! $hasCategory) {
            return 0.0;
        }

        // Komponen % kedua: Biaya Layanan (Shopee, ada batas) atau Komisi Dinamis (Tokped/TikTok).
        if ($order->marketplace === 'SHOPEE') {
            $extra = $subtotal * $fees['shopee_service_pct'] / 100;
            if ($fees['shopee_service_cap'] > 0) {
                $extra = min($extra, $fees['shopee_service_cap']);
            }
        } else {
            $extra = $subtotal * $fees['tokotiktok_dynamic_pct'] / 100;
        }

        return round($commission + $extra + self::ORDER_PROCESSING_FEE, 2);
    }

    /** Setelan biaya tambahan per-organisasi (default aman bila kolom/baris belum ada). */
    public function feesForOrg(int $orgId): array
    {
        $org = Organization::find($orgId);

        return [
            'shopee_service_pct' => (float) ($org->fee_shopee_service_pct ?? 10),
            'shopee_service_cap' => (float) ($org->fee_shopee_service_cap ?? 10000),
            'tokotiktok_dynamic_pct' => (float) ($org->fee_tokotiktok_dynamic_pct ?? 6.5),
        ];
    }

    /** Pesanan layak diestimasi: belum punya Laporan Penghasilan resmi. */
    public function isEligible(Order $order): bool
    {
        return ! $order->income_verified;
    }

    /**
     * Hitung ulang estimasi untuk SEMUA pesanan org yang BELUM final (overwrite estimasi
     * lama agar formula terbaru diterapkan). Pesanan dengan Laporan Penghasilan (income_verified)
     * TIDAK disentuh. Pakai saveQuietly agar tidak membanjiri log aktivitas.
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
            if ($estimate <= 0) {
                continue;
            }
            if ((float) $order->admin_fee !== $estimate) {
                $order->admin_fee = $estimate;
                $order->saveQuietly();
            }
            $updated++;
            $total += $estimate;
        }

        return ['updated' => $updated, 'total' => round($total, 2), 'eligible' => $orders->count()];
    }
}
