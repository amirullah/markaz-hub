<?php

namespace App\Services;

/**
 * Sumber kebenaran TUNGGAL perhitungan laba (dipakai web Filament & API mobile).
 *
 * Formula DIKUNCI dari v1 yang sudah diaudit (lihat golden test). Bila ada perubahan
 * di sini, golden test WAJIB tetap lulus (angka identik dgn v1, selisih 0).
 *
 *   profit = (product_revenue + other_income)
 *          - (cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + dropship_cost + other_cost)
 *   net    = (product_revenue + other_income)
 *          - (admin_fee + shipping_cost_seller + voucher_seller_borne + other_cost)   // sebelum modal
 *
 * Menerima array kolom finansial ATAU model Order (apa pun yang ArrayAccess-able).
 */
class ProfitService
{
    /**
     * Ekspresi SQL laba — HARUS identik dengan profit(). Dipakai untuk sort/agregat
     * (orderByRaw/whereRaw/selectRaw) agar formula TIDAK diketik ulang di banyak tempat
     * (cegah divergensi dari sumber kebenaran).
     */
    public const SQL_PROFIT = '(product_revenue + other_income - (cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + dropship_cost + other_cost))';

    /** Ekspresi SQL net (laba sebelum modal). */
    public const SQL_NET = '(product_revenue + other_income - (admin_fee + shipping_cost_seller + voucher_seller_borne + other_cost))';

    /**
     * Ekspresi SQL biaya dropship aktif sesuai toggle Dropship:
     * - Dropship AKTIF   → 'dropship_cost' (total ke Dropship = modal + biaya mitra)
     * - Dropship NONAKTIF → modal historis (seolah packing sendiri). Bila modal historis
     *   belum terisi (0, mis. laporan Dropship belum di-upload ulang), JATUH ke dropship_cost
     *   agar biaya tidak hilang & laba tidak menggelembung palsu.
     * Default 'dropship_cost' (aman tanpa auth / golden test = perilaku v1).
     */
    public static function dropshipExpr(): string
    {
        return \App\Models\Organization::currentUsesDropship()
            ? 'dropship_cost'
            : 'COALESCE(NULLIF(dropship_modal, 0), dropship_cost)';
    }

    /**
     * Ekspresi SQL laba MENGIKUTI toggle Dropship (untuk dashboard/list/insight).
     */
    public static function sqlProfit(): string
    {
        return '(product_revenue + other_income - (cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + '
            . self::dropshipExpr() . ' + other_cost))';
    }

    /** Biaya dropship efektif (PHP) sesuai toggle Dropship, dgn fallback aman. */
    private function effectiveDropship(array|object $o): float
    {
        if (\App\Models\Organization::currentUsesDropship()) {
            return $this->f($o, 'dropship_cost');
        }
        $modal = $this->f($o, 'dropship_modal');
        return $modal > 0 ? $modal : $this->f($o, 'dropship_cost');
    }

    /** Laba bersih per pesanan (setelah modal). */
    public function profit(array|object $o): float
    {
        return round($this->revenue($o) - $this->totalCost($o), 2);
    }

    /** Uang bersih marketplace (laba sebelum modal). Harus == settlement utk pesanan verified. */
    public function net(array|object $o): float
    {
        return round(
            $this->revenue($o)
            - $this->f($o, 'admin_fee') - $this->f($o, 'shipping_cost_seller')
            - $this->f($o, 'voucher_seller_borne') - $this->f($o, 'other_cost'),
            2
        );
    }

    public function revenue(array|object $o): float
    {
        return $this->f($o, 'product_revenue') + $this->f($o, 'other_income');
    }

    public function totalCost(array|object $o): float
    {
        return $this->f($o, 'cogs') + $this->f($o, 'admin_fee') + $this->f($o, 'shipping_cost_seller')
            + $this->f($o, 'voucher_seller_borne') + $this->effectiveDropship($o) + $this->f($o, 'other_cost');
    }

    public function margin(array|object $o): float
    {
        $rev = $this->revenue($o);
        return $rev > 0 ? round($this->profit($o) / $rev * 100, 2) : 0.0;
    }

    private function f(array|object $o, string $key): float
    {
        $v = is_array($o) ? ($o[$key] ?? 0) : ($o->{$key} ?? 0);
        return (float) $v;
    }
}
