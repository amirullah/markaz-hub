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
 * PENGECUALIAN PENDING (settlement belum cair): bila NET (uang bersih marketplace) <= 0, HPP &
 * biaya dropship BELUM direalisasi → profit = net. Jadi 0 saat belum ada omzet (dropship
 * "Dibayar") ATAU saat pesanan "Selesai" tapi dananya belum cair (settlement 0 di laporan).
 * HPP/dropship baru dihitung saat dana cair (net > 0). Retur (net<0, cogs=0) tetap rugi nyata;
 * pesanan batal sudah dinolkan saat impor.
 *
 * Menerima array kolom finansial ATAU model Order (apa pun yang ArrayAccess-able).
 */
class ProfitService
{
    /** Ekspresi SQL net (settlement/uang bersih marketplace sebelum modal); 0 bila belum ada omzet. */
    public const SQL_NET = '(CASE WHEN (product_revenue + other_income) > 0 THEN (product_revenue + other_income - (admin_fee + shipping_cost_seller + voucher_seller_borne + other_cost)) ELSE 0 END)';

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
        $net = self::SQL_NET; // settlement (ter-gate: 0 bila belum ada omzet)
        // Settlement belum cair (net<=0) → laba = net (HPP/dropship belum direalisasi);
        // selain itu net − HPP − biaya dropship (mengikuti toggle Dropship).
        return '(CASE WHEN ' . $net . ' <= 0 THEN ' . $net . ' ELSE (' . $net . ' - cogs - ' . self::dropshipExpr() . ') END)';
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
        // Laba = settlement (net) − HPP − biaya dropship. Bila settlement BELUM cair (net <= 0),
        // HPP/dropship belum terealisasi → laba = net (PENDING; 0 bila belum ada omzet, atau rugi
        // nyata pada retur yg cogs=0). HPP/dropship baru dihitung saat dana cair (net > 0).
        $net = $this->net($o);
        if ($net <= 0) {
            return $net;
        }

        return round($net - $this->f($o, 'cogs') - $this->effectiveDropship($o), 2);
    }

    /** Uang bersih marketplace (laba sebelum modal). Harus == settlement utk pesanan verified. */
    public function net(array|object $o): float
    {
        if ($this->revenue($o) <= 0) {
            return 0.0;
        }

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
