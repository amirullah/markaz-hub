<?php

namespace App\Services;

use App\Models\Order;

/**
 * Estimasi biaya admin marketplace untuk pesanan yang BELUM punya biaya admin
 * (belum ada Laporan Penghasilan). Dihitung per item dari kategori produk:
 *   estimasi = Σ (qty × harga jual item) × (% biaya admin kategori untuk channel)
 *
 * Tarif % per kategori berasal dari dokumentasi resmi marketplace (bisa disesuaikan
 * di menu Kategori). Saat Laporan Penghasilan resmi masuk, biaya admin asli
 * menggantikan estimasi ini (income_verified jadi true).
 */
class AdminFeeEstimator
{
    /** Estimasi biaya admin satu pesanan (Rp). 0 jika tak ada kategori yang cocok. */
    public function estimate(Order $order): float
    {
        $order->loadMissing('items.product.category');
        $fee = 0.0;

        foreach ($order->items as $item) {
            $category = $item->product?->category;
            if (! $category) {
                continue;
            }
            $rate = $category->feeForMarketplace($order->marketplace);
            $revenue = (float) $item->qty * (float) $item->unit_price;
            $fee += $revenue * $rate / 100;
        }

        return round($fee, 2);
    }

    /** Pesanan layak diestimasi: belum final & belum ada biaya admin. */
    public function isEligible(Order $order): bool
    {
        return ! $order->income_verified && (float) $order->admin_fee === 0.0;
    }

    /**
     * Terapkan estimasi ke SEMUA pesanan org yang layak. Mengembalikan jumlah
     * pesanan terisi + total estimasi. Pakai saveQuietly agar tidak membanjiri log.
     */
    public function applyToOrg(int $orgId): array
    {
        $orders = Order::query()
            ->where('income_verified', false)
            ->where('admin_fee', 0)
            ->with('items.product.category')
            ->get();

        $updated = 0;
        $total = 0.0;

        foreach ($orders as $order) {
            $estimate = $this->estimate($order);
            if ($estimate <= 0) {
                continue;
            }
            $order->admin_fee = $estimate;
            $order->saveQuietly();
            $updated++;
            $total += $estimate;
        }

        return ['updated' => $updated, 'total' => round($total, 2), 'eligible' => $orders->count()];
    }
}
