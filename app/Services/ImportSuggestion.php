<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Memberi tahu file APA yang perlu segera diupload + RENTANG TANGGAL mana,
 * berdasarkan pesanan yang datanya belum lengkap (laba belum final / rincian kurang).
 * Cermin Order::incompleteness() + lacksItemDetail(), tapi diagregasi per channel + periode.
 * Semua query ter-scope ke organisasi user (global scope BelongsToOrganization).
 */
class ImportSuggestion
{
    private static function channelLabel(string $mp): string
    {
        return match ($mp) {
            'SHOPEE' => 'Shopee',
            'TIKTOKTOKO' => 'Tokopedia/TikTok',
            'TOKOPEDIA' => 'Tokopedia',
            'TIKTOK' => 'TikTok',
            default => $mp,
        };
    }

    private static function fmt($d): string
    {
        return $d ? Carbon::parse($d)->translatedFormat('d M Y') : '—';
    }

    /** Query dasar: pesanan aktif (batal/retur dikecualikan — terminal, bukan "kurang data"). */
    private static function base()
    {
        return Order::query()->whereNotIn('status', ['CANCELLED', 'RETURNED']);
    }

    /**
     * @return array<int, array{urgency:string,file:string,via:string,dari:string,sampai:string,count:int,note:string}>
     */
    public function compute(): array
    {
        $out = [];

        // 1) LAPORAN PENGHASILAN — biaya admin/komisi masih estimasi (income_verified=false). Paling berdampak.
        foreach (self::base()->where('income_verified', false)
            ->selectRaw('marketplace, COUNT(*) c, MIN(order_date) dari, MAX(order_date) sampai')
            ->groupBy('marketplace')->orderByDesc('c')->get() as $r) {
            $out[] = [
                'urgency' => 'high',
                'file' => 'Laporan Penghasilan ' . self::channelLabel($r->marketplace),
                'via' => 'Laporan Marketplace',
                'dari' => self::fmt($r->dari),
                'sampai' => self::fmt($r->sampai),
                'count' => (int) $r->c,
                'note' => 'biaya admin/komisi masih estimasi — laba belum final',
            ];
        }

        // 2) FILE PESANAN — rincian produk/SKU/qty belum ada (tak punya item sama sekali).
        foreach (self::base()->doesntHave('items')
            ->selectRaw('marketplace, COUNT(*) c, MIN(order_date) dari, MAX(order_date) sampai')
            ->groupBy('marketplace')->orderByDesc('c')->get() as $r) {
            $out[] = [
                'urgency' => 'medium',
                'file' => 'File Pesanan (Order Completed / Pesanan Selesai) ' . self::channelLabel($r->marketplace),
                'via' => 'Laporan Marketplace',
                'dari' => self::fmt($r->dari),
                'sampai' => self::fmt($r->sampai),
                'count' => (int) $r->c,
                'note' => 'rincian produk/SKU/qty belum tercatat',
            ];
        }

        // 3) DAFTAR PRODUK (HPP) — pesanan packing-sendiri tanpa modal.
        $r = self::base()->where('fulfillment', 'SELF')->where('product_revenue', '>', 0)->where('cogs', '<=', 0)
            ->selectRaw('COUNT(*) c, MIN(order_date) dari, MAX(order_date) sampai')->first();
        if ($r && (int) $r->c > 0) {
            $out[] = [
                'urgency' => 'medium',
                'file' => 'Daftar Produk (HPP / harga modal)',
                'via' => 'Daftar Produk',
                'dari' => self::fmt($r->dari),
                'sampai' => self::fmt($r->sampai),
                'count' => (int) $r->c,
                'note' => 'HPP/modal pesanan packing-sendiri belum ada',
            ];
        }

        // 4) DROPSHIP — biaya dropship per pesanan belum terisi (hanya bila org pakai dropship).
        if (Organization::currentUsesDropship()) {
            $r = self::base()->where('fulfillment', 'DROPSHIP')->where('dropship_cost', '<=', 0)
                ->selectRaw('COUNT(*) c, MIN(order_date) dari, MAX(order_date) sampai')->first();
            if ($r && (int) $r->c > 0) {
                $out[] = [
                    'urgency' => 'medium',
                    'file' => 'Biaya Dropship',
                    'via' => 'Dropship',
                    'dari' => self::fmt($r->dari),
                    'sampai' => self::fmt($r->sampai),
                    'count' => (int) $r->c,
                    'note' => 'biaya dropship per pesanan belum terisi',
                ];
            }

            // 5) FILE DROPSHIP KURANG — toko ber-mode "dropship" masih punya pesanan packing-sendiri
            //    yang BELUM ada data dropship-nya → file Dropship periode itu perlu diupload.
            foreach (\App\Models\Store::query()->where('fulfillment_mode', 'dropship')->get() as $store) {
                $r = self::base()->where('store_id', $store->id)->where('fulfillment', 'SELF')
                    ->whereNotExists(function ($q) {
                        $q->selectRaw('1')->from('dropship_costs as dc')
                            ->whereColumn('dc.external_no', 'orders.external_no')
                            ->whereColumn('dc.organization_id', 'orders.organization_id');
                    })
                    ->selectRaw('COUNT(*) c, MIN(order_date) dari, MAX(order_date) sampai')->first();
                if ($r && (int) $r->c > 0) {
                    $out[] = [
                        'urgency' => 'medium',
                        'file' => 'Biaya Dropship — toko ' . $store->name,
                        'via' => 'Dropship',
                        'dari' => self::fmt($r->dari),
                        'sampai' => self::fmt($r->sampai),
                        'count' => (int) $r->c,
                        'note' => 'toko dropship tapi ada pesanan packing-sendiri yang belum ada data dropship — upload file Dropship periode ini',
                    ];
                }
            }
        }

        return $out;
    }
}
