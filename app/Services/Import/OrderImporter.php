<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi import — PORT FAITHFUL dari v1 (inc/actions.php), diadaptasi ke
 * multi-tenant: SEMUA query diberi organization_id eksplisit. Parsing memakai
 * fungsi v1 yang sudah teruji (app/Legacy: mp_read_file, mp_merge_orders,
 * mp_parse_date, mp_map_status). Logika laba TIDAK diubah (golden test menjaga).
 */
class OrderImporter
{
    private bool $usesJakmall;

    public function __construct(private int $orgId)
    {
        // Org yang tidak memakai Jakmall: file master/laporan Jakmall dilewati saat import.
        $this->usesJakmall = (bool) (DB::table('organizations')->where('id', $orgId)->value('uses_jakmall') ?? true);
    }

    private function group(string $marketplace): string
    {
        return $marketplace === 'SHOPEE' ? 'SHOPEE' : 'TIKTOKTOKO';
    }

    /** Proses banyak file untuk satu toko. Mengembalikan laporan per-file + ringkasan. */
    public function importFiles(array $files, int $storeId, string $storeMarketplace, bool $updateOldHpp = false, ?string $hppSince = null): array
    {
        $srcLabel = [
            'shopee_income' => 'Laporan Penghasilan Shopee', 'shopee_order' => 'Order Completed Shopee',
            'tiktok_income' => 'Laporan Penghasilan Tokopedia/TikTok', 'csv' => 'Pesanan Selesai Tokopedia/TikTok',
            'jakmall' => 'Master Produk Jakmall', 'jakmall_orders' => 'Laporan Pesanan Jakmall', 'generic_xlsx' => 'File pesanan',
        ];
        $report = []; $orderSources = []; $orderFiles = []; $jakmall = []; $dropshipMap = []; $hasJakmallReport = false;

        foreach ($files as $f) {
            $name = $f['name']; $res = mp_read_file($f['path'], $name);
            $label = $srcLabel[$res['source'] ?? ''] ?? 'File';
            // Org non-Jakmall: lewati hanya LAPORAN PESANAN (dropship). Master produk
            // tetap diproses karena = katalog harga/HPP + riwayat harga (relevan walau dropship off).
            if (! $this->usesJakmall && ($res['type'] ?? '') === 'jakmall_orders') {
                $report[] = ['name' => $name, 'ok' => false, 'reason' => 'Laporan Pesanan Jakmall (dropship) dilewati — organisasi tidak memakai dropship Jakmall. (Master produk tetap diproses.)'];
                continue;
            }
            if ($res['type'] === 'jakmall') {
                foreach ($res['products'] as $p) $jakmall[$p['sku']] = $p;
                $report[] = ['name' => $name, 'ok' => true, 'type' => $label, 'detail' => count($res['products']) . ' produk'];
            } elseif ($res['type'] === 'jakmall_orders') {
                $hasJakmallReport = true;
                foreach ($res['dropship'] as $no => $info) $dropshipMap[$no] = $info;
                $report[] = ['name' => $name, 'ok' => true, 'type' => $label, 'detail' => count($res['dropship']) . ' pesanan dropship'];
            } elseif ($res['type'] === 'orders' && !empty($res['orders'])) {
                $orderSources[] = $res['orders'];
                $orderFiles[] = ['name' => $name, 'mk' => $res['marketplace'] ?? null, 'ridx' => count($report)];
                $report[] = ['name' => $name, 'ok' => true, 'type' => $label, 'detail' => count($res['orders']) . ' pesanan terbaca'];
            } else {
                $report[] = ['name' => $name, 'ok' => false, 'reason' => 'Format tidak dikenali / kosong.'];
            }
        }

        // Saring per channel toko (file beda channel dilewati, bukan blokir semua).
        $grp = $this->group($storeMarketplace); $matched = [];
        foreach ($orderFiles as $i => $of) {
            if ($of['mk'] !== null && $of['mk'] !== $grp) {
                $lbl = $of['mk'] === 'SHOPEE' ? 'Shopee' : 'Tokopedia/TikTok';
                $report[$of['ridx']]['ok'] = false;
                $report[$of['ridx']]['reason'] = "Beda channel: file $lbl, toko " . $storeMarketplace . '.';
            } else {
                $matched[] = $orderSources[$i];
            }
        }
        $orderSources = $matched;

        $summary = [];
        if ($jakmall) {
            [$ins, $upd, $changes, $skippedOld] = $this->importJakmallProducts(array_values($jakmall));
            $bf = $this->backfillHpp();
            if ($updateOldHpp) $bf += $this->recomputeHpp($hppSince);
            $summary['jakmall'] = "Master: $ins baru, $upd diperbarui" . ($changes ? ', ' . count($changes) . ' harga berubah' : '')
                . ($skippedOld ? ", $skippedOld dilewati (data lebih lama)" : '') . ($bf ? ", $bf pesanan ber-HPP" : '') . '.';
            $summary['hpp_changes'] = $changes;
        }
        if ($orderSources) {
            $orders = mp_merge_orders($orderSources);
            $summary['orders'] = $this->importOrders($orders, $storeId, $storeMarketplace, $dropshipMap, $hasJakmallReport, 'SELF');
        }
        if ($hasJakmallReport) {
            $bf = $this->backfillDropship($dropshipMap);
            $summary['dropship'] = "Laporan Jakmall: " . count($dropshipMap) . " pesanan dropship; $bf pesanan lama diperbarui.";
        }

        return ['report' => $report, 'summary' => $summary];
    }

    /** Upsert katalog Jakmall (produk + ID marketplace) + deteksi perubahan harga. */
    public function importJakmallProducts(array $products): array
    {
        $supId = DB::table('suppliers')->where('organization_id', $this->orgId)->where('type', 'JAKMALL')->value('id');
        if (! $supId) {
            $supId = DB::table('suppliers')->insertGetId([
                'organization_id' => $this->orgId, 'name' => 'Jakmall', 'type' => 'JAKMALL',
                'note' => 'Dropship', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $oldCost = DB::table('products')->where('organization_id', $this->orgId)->pluck('cost_price', 'sku')->all();
        $oldDate = DB::table('products')->where('organization_id', $this->orgId)->pluck('cost_changed_at', 'sku')->all();

        $ins = 0; $upd = 0; $skippedOld = 0; $changes = []; $now = now();
        $prodRows = []; $pmiRows = []; $priceRows = [];
        foreach ($products as $p) {
            $sku = $p['sku'] ?? '';
            if ($sku === '') continue;
            $new = (float) $p['cost'];
            $changedAt = mp_jakmall_date($p['changedAt'] ?? null); // tgl "Perubahan Terakhir" dari master
            $exists = array_key_exists($sku, $oldCost);
            $curDate = $oldDate[$sku] ?? null;

            // Data master LEBIH LAMA dari yang tersimpan → JANGAN ubah harga (cegah harga balik ke lama).
            if ($exists && $curDate !== null && $changedAt !== null && $changedAt < (string) $curDate) {
                $skippedOld++;
                continue;
            }

            if ($exists) {
                if (abs((float) $oldCost[$sku] - $new) > 1) {
                    $changes[] = ['sku' => $sku, 'name' => (string) $p['name'], 'old' => (float) $oldCost[$sku], 'new' => $new];
                    $priceRows[] = ['organization_id' => $this->orgId, 'sku' => $sku, 'old_price' => (float) $oldCost[$sku],
                        'new_price' => $new, 'changed_at' => $changedAt ?? $now, 'created_at' => $now, 'updated_at' => $now];
                }
                $upd++;
            } else {
                $ins++;
                // Harga awal produk baru = titik riwayat pertama.
                $priceRows[] = ['organization_id' => $this->orgId, 'sku' => $sku, 'old_price' => null,
                    'new_price' => $new, 'changed_at' => $changedAt ?? $now, 'created_at' => $now, 'updated_at' => $now];
            }
            $prodRows[] = ['organization_id' => $this->orgId, 'sku' => $sku, 'name' => mb_substr((string) $p['name'], 0, 255),
                'cost_price' => $new, 'dropship_cost' => $new, 'cost_changed_at' => $changedAt, 'supplier_id' => $supId, 'active' => 1, 'created_at' => $now, 'updated_at' => $now];
            foreach ($p['mpIds'] ?? [] as $mpId) {
                $pmiRows[] = ['organization_id' => $this->orgId, 'marketplace_product_id' => mb_substr((string) $mpId, 0, 64), 'sku' => $sku, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($prodRows, 500) as $c) {
            DB::table('products')->upsert($c, ['organization_id', 'sku'], ['name', 'cost_price', 'dropship_cost', 'cost_changed_at', 'supplier_id', 'active', 'updated_at']);
        }
        foreach (array_chunk($pmiRows, 500) as $c) {
            DB::table('product_marketplace_ids')->upsert($c, ['organization_id', 'marketplace_product_id'], ['sku', 'updated_at']);
        }
        foreach (array_chunk($priceRows, 500) as $c) {
            DB::table('product_price_changes')->insert($c);
        }
        return [$ins, $upd, $changes, $skippedOld];
    }

    /**
     * Seed riwayat harga dari membandingkan 2 snapshot master (lama vs baru).
     * Mencatat satu perubahan per SKU yang harganya beda, dgn tanggal "Perubahan
     * Terakhir" master baru. Idempotent (lewati yg sudah tercatat sku+new_price).
     */
    public function seedPriceHistory(array $oldProducts, array $newProducts): int
    {
        $oldMap = [];
        foreach ($oldProducts as $p) {
            if (! empty($p['sku'])) $oldMap[$p['sku']] = (float) $p['cost'];
        }
        $existing = DB::table('product_price_changes')->where('organization_id', $this->orgId)
            ->get(['sku', 'new_price'])->map(fn ($r) => $r->sku . '|' . (float) $r->new_price)->flip();

        $now = now(); $rows = [];
        foreach ($newProducts as $p) {
            $sku = $p['sku'] ?? ''; if ($sku === '') continue;
            $new = (float) $p['cost'];
            $old = $oldMap[$sku] ?? null;
            if ($old === null || abs($old - $new) <= 1) continue;
            if (isset($existing[$sku . '|' . $new])) continue; // sudah tercatat
            $rows[] = ['organization_id' => $this->orgId, 'sku' => $sku, 'old_price' => $old,
                'new_price' => $new, 'changed_at' => mp_jakmall_date($p['changedAt'] ?? null) ?? $now,
                'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $c) {
            DB::table('product_price_changes')->insert($c);
        }
        // Set cost_changed_at = changed_at terbaru per SKU dalam SATU query (hemat di MySQL remote).
        DB::statement('UPDATE products p JOIN (SELECT sku, MAX(changed_at) mx FROM product_price_changes WHERE organization_id = ? GROUP BY sku) x ON x.sku = p.sku SET p.cost_changed_at = x.mx WHERE p.organization_id = ?', [$this->orgId, $this->orgId]);
        return count($rows);
    }

    /** Isi HPP yang masih kosong (jangan timpa HPP lama — histori harga). */
    public function backfillHpp(): int
    {
        $cond = "o.organization_id = ? AND o.status NOT IN ('CANCELLED','RETURNED') AND o.fulfillment='SELF' AND o.deleted_at IS NULL";
        $before = (int) DB::selectOne("SELECT COUNT(*) c FROM orders o WHERE $cond AND o.cogs=0", [$this->orgId])->c;
        DB::statement("UPDATE order_items i JOIN orders o ON o.id=i.order_id JOIN products p ON p.sku=i.sku AND p.organization_id=o.organization_id
            SET i.unit_cost=p.cost_price WHERE $cond AND (i.unit_cost=0 OR i.unit_cost IS NULL)", [$this->orgId]);
        DB::statement("UPDATE orders o SET o.cogs=COALESCE((SELECT SUM(i.unit_cost*i.qty) FROM order_items i WHERE i.order_id=o.id),0)
            WHERE $cond AND o.cogs=0", [$this->orgId]);
        $after = (int) DB::selectOne("SELECT COUNT(*) c FROM orders o WHERE $cond AND o.cogs=0", [$this->orgId])->c;
        return max(0, $before - $after);
    }

    /** Hitung ULANG HPP (menimpa) — hanya bila user memilih, boleh dibatasi tanggal. */
    public function recomputeHpp(?string $since = null): int
    {
        $cond = "o.organization_id = ? AND o.status NOT IN ('CANCELLED','RETURNED') AND o.fulfillment='SELF' AND o.deleted_at IS NULL"
            . ($since ? " AND o.order_date >= " . DB::getPdo()->quote($since) : '');
        DB::statement("UPDATE order_items i JOIN orders o ON o.id=i.order_id JOIN products p ON p.sku=i.sku AND p.organization_id=o.organization_id
            SET i.unit_cost=p.cost_price WHERE $cond", [$this->orgId]);
        return DB::update("UPDATE orders o SET o.cogs=COALESCE((SELECT SUM(i.unit_cost*i.qty) FROM order_items i WHERE i.order_id=o.id),0) WHERE $cond", [$this->orgId]);
    }

    /** Backfill DROPSHIP + modal Jakmall pada pesanan lama yang cocok laporan. */
    public function backfillDropship(array $dropshipMap): int
    {
        $n = 0;
        foreach ($dropshipMap as $no => $jak) {
            $modal = (float) ($jak['productCost'] ?? 0); // Total Harga Produk = modal historis (biaya bila packing sendiri)
            $note = mb_substr('Dropship Jakmall' . (!empty($jak['jakmallCode']) ? ' #' . $jak['jakmallCode'] : '') .
                ': total Rp' . number_format($jak['total'], 0, ',', '.') . ', modal Rp' . number_format($modal, 0, ',', '.'), 0, 500);
            $n += DB::update("UPDATE orders SET fulfillment='DROPSHIP', dropship_cost=?, dropship_modal=?, cogs=0, note=?
                WHERE organization_id=? AND external_no=? AND status NOT IN ('CANCELLED','RETURNED') AND deleted_at IS NULL
                  AND (fulfillment<>'DROPSHIP' OR ABS(dropship_cost - ?) > 1 OR ABS(dropship_modal - ?) > 1)",
                [$jak['total'], $modal, $note, $this->orgId, (string) $no, $jak['total'], $modal]);
        }
        return $n;
    }

    /** PORT import_shopee_orders: merge + resolusi SKU + hitung + tulis (org-scoped). */
    public function importOrders(array $orders, int $storeId, string $storeMarketplace, array $dropshipMap, bool $hasJakmallReport, string $defaultFulfillment): string
    {
        if (! $orders) return 'Tidak ada pesanan.';
        $org = $this->orgId;

        // Pass 1: merge dgn pesanan lama (per org, lintas toko) + kumpulkan ID Produk.
        $prepared = []; $mpIds = [];
        foreach ($orders as $o) {
            $o['externalNo'] = (string) $o['externalNo'];
            $ex = DB::table('orders')->where('organization_id', $org)->where('external_no', $o['externalNo'])->first();
            $exItems = $ex ? DB::table('order_items')->where('order_id', $ex->id)->get()->map(fn ($r) => (array) $r)->all() : [];
            if ($ex) $o = mp_merge_orders([[$this->orderRowToNorm((array) $ex, $exItems)], [$o]])[0];
            foreach ($o['items'] as $it) {
                if (empty($it['sku']) && !empty($it['shopeeId'])) $mpIds[$it['shopeeId']] = true;
            }
            $prepared[] = ['ex' => $ex ? (array) $ex : null, 'exItems' => $exItems, 'o' => $o];
        }

        // ID Produk -> SKU (dari Master Jakmall, org-scoped).
        $skuByMpId = [];
        if ($mpIds) {
            foreach (array_chunk(array_keys($mpIds), 500) as $chunk) {
                foreach (DB::table('product_marketplace_ids')->where('organization_id', $org)->whereIn('marketplace_product_id', $chunk)->get() as $row) {
                    $skuByMpId[$row->marketplace_product_id] = $row->sku;
                }
            }
        }
        $skus = [];
        foreach ($prepared as &$pp) {
            foreach ($pp['o']['items'] as &$it) {
                if (empty($it['sku']) && !empty($it['shopeeId']) && isset($skuByMpId[$it['shopeeId']])) $it['sku'] = $skuByMpId[$it['shopeeId']];
                if (!empty($it['sku'])) $skus[$it['sku']] = true;
            }
            unset($it);
        }
        unset($pp);

        // SKU -> produk (katalog org).
        $productBySku = [];
        if ($skus) {
            foreach (array_chunk(array_keys($skus), 500) as $chunk) {
                foreach (DB::table('products')->where('organization_id', $org)->whereIn('sku', $chunk)->get() as $p) {
                    $productBySku[$p->sku] = (array) $p;
                }
            }
        }

        $created = 0; $updated = 0; $unchanged = 0; $r = fn ($v) => (int) round((float) $v);

        foreach ($prepared as $pp) {
            $ex = $pp['ex']; $exItems = $pp['exItems']; $o = $pp['o']; $no = $o['externalNo'];

            $jak = $dropshipMap[$no] ?? null;
            if ($jak) $ful = 'DROPSHIP';
            elseif ($ex && $ex['fulfillment'] === 'DROPSHIP') $ful = 'DROPSHIP';
            elseif ($hasJakmallReport) $ful = 'SELF';
            elseif ($ex) $ful = $ex['fulfillment'];
            else $ful = $defaultFulfillment;

            $cogs = 0; $items = [];
            foreach ($o['items'] as $it) {
                $product = (!empty($it['sku']) && isset($productBySku[$it['sku']])) ? $productBySku[$it['sku']] : null;
                $unitCost = $product ? (float) $product['cost_price'] : 0;
                if ($ful === 'SELF') $cogs += $unitCost * $it['qty'];
                $items[] = ['product_id' => $product['id'] ?? null, 'sku' => $it['sku'] ?: null, 'name' => $it['name'],
                    'qty' => $it['qty'], 'qty_assumed' => !empty($it['qtyAssumed']) ? 1 : 0, 'unit_price' => $it['unitPrice'], 'unit_cost' => $unitCost];
            }

            $dropship = 0; $dropshipModal = 0; $note = $o['note'] ?? null;
            if ($ful === 'DROPSHIP') {
                $cogs = 0;
                if ($jak) {
                    $dropship = (float) $jak['total'];
                    $dropshipModal = (float) ($jak['productCost'] ?? 0); // modal historis = biaya bila packing sendiri
                    $note = mb_substr('Dropship Jakmall' . (!empty($jak['jakmallCode']) ? ' #' . $jak['jakmallCode'] : '') . ': total Rp' . number_format($jak['total'], 0, ',', '.') . ', modal Rp' . number_format($dropshipModal, 0, ',', '.'), 0, 500);
                } elseif ($ex && $ex['fulfillment'] === 'DROPSHIP') {
                    $dropship = (float) $ex['dropship_cost']; $dropshipModal = (float) ($ex['dropship_modal'] ?? 0); $note = $ex['note'];
                } else {
                    foreach ($o['items'] as $it) {
                        $p = (!empty($it['sku']) && isset($productBySku[$it['sku']])) ? $productBySku[$it['sku']] : null;
                        if ($p) { $dropship += (float) $p['dropship_cost'] * $it['qty']; $dropshipModal += (float) ($p['cost_price'] ?? $p['dropship_cost']) * $it['qty']; }
                    }
                }
            }

            $revenue = (float) $o['productRevenue'];
            $verified = !empty($o['_hasIncome']) ? 1 : 0;
            $adminFee = (float) ($o['adminFee'] ?? 0);
            $status = mp_map_status($o['status'] ?? '');
            $voucher = $o['voucherSellerBorne'] ?? 0; $shipSeller = $o['shippingCostSeller'] ?? 0;
            $otherCost = $o['otherCost'] ?? 0; $otherIncome = $o['otherIncome'] ?? 0;
            if ($status === 'CANCELLED') { $revenue = 0; $adminFee = 0; $cogs = 0; $dropship = 0; $dropshipModal = 0; $voucher = 0; $shipSeller = 0; $otherCost = 0; $otherIncome = 0; }
            if ($status === 'RETURNED') { $cogs = 0; $dropship = 0; $dropshipModal = 0; }

            // Deteksi perubahan: re-import tanpa data baru = tidak diutak-atik.
            if ($ex) {
                $exQty = 0; $exSku = 0; foreach ($exItems as $x) { $exQty += (int) $x['qty']; if (!empty($x['sku'])) $exSku++; }
                $newQty = 0; $newSku = 0; foreach ($items as $x) { $newQty += (int) $x['qty']; if (!empty($x['sku'])) $newSku++; }
                $same = $ex['fulfillment'] === $ful && (int) $ex['income_verified'] === $verified && $ex['status'] === $status
                    && $r($ex['product_revenue']) === $r($revenue) && $r($ex['admin_fee']) === $r($adminFee)
                    && $r($ex['cogs']) === $r($cogs) && $r($ex['dropship_cost']) === $r($dropship)
                    && $r($ex['dropship_modal'] ?? 0) === $r($dropshipModal)
                    && $r($ex['other_cost']) === $r($otherCost) && $r($ex['voucher_seller_borne']) === $r($voucher)
                    && count($exItems) === count($items) && $exSku === $newSku && $exQty === $newQty;
                if ($same) { $unchanged++; continue; }
            }

            DB::transaction(function () use ($ex, $org, $no, $storeId, $storeMarketplace, $status, $ful, $o, $revenue, $adminFee, $cogs, $dropship, $dropshipModal, $voucher, $shipSeller, $otherCost, $otherIncome, $verified, $note, $items) {
                $data = [
                    'status' => $status, 'fulfillment' => $ful, 'order_date' => mp_parse_date($o['orderDate'] ?? null),
                    'buyer_name' => ($o['buyerName'] ?? '') ?: null, 'product_revenue' => $revenue,
                    'shipping_charged_to_buyer' => $o['shippingChargedToBuyer'] ?? 0, 'other_income' => $otherIncome,
                    'cogs' => $cogs, 'admin_fee' => $adminFee, 'shipping_cost_seller' => $shipSeller,
                    'voucher_seller_borne' => $voucher, 'dropship_cost' => $dropship, 'dropship_modal' => $dropshipModal, 'other_cost' => $otherCost,
                    'income_verified' => $verified, 'note' => $note, 'updated_at' => now(),
                ];
                if ($ex) {
                    DB::table('orders')->where('id', $ex['id'])->update($data);
                    DB::table('order_items')->where('order_id', $ex['id'])->delete();
                    $orderId = $ex['id'];
                } else {
                    $orderId = DB::table('orders')->insertGetId($data + [
                        'organization_id' => $org, 'store_id' => $storeId, 'external_no' => $no,
                        'marketplace' => $storeMarketplace, 'created_at' => now(),
                    ]);
                }
                $rows = [];
                foreach ($items as $it) {
                    $rows[] = ['organization_id' => $org, 'order_id' => $orderId, 'product_id' => $it['product_id'],
                        'sku' => $it['sku'], 'name' => $it['name'], 'qty' => $it['qty'], 'qty_assumed' => $it['qty_assumed'],
                        'unit_price' => $it['unit_price'], 'unit_cost' => $it['unit_cost']];
                }
                if ($rows) DB::table('order_items')->insert($rows);
            });
            $ex ? $updated++ : $created++;
        }

        return "Pesanan: $created baru, $updated diperbarui, $unchanged tetap.";
    }

    /** PORT order_row_to_norm: baris DB -> bentuk ternormalisasi utk mp_merge_orders. */
    private function orderRowToNorm(array $ex, array $exItems): array
    {
        $items = [];
        foreach ($exItems as $it) {
            $items[] = ['sku' => $it['sku'] ?: null, 'name' => $it['name'], 'qty' => (int) $it['qty'],
                'qtyAssumed' => !empty($it['qty_assumed']), 'unitPrice' => (float) $it['unit_price']];
        }
        return [
            'externalNo' => $ex['external_no'], 'orderDate' => $ex['order_date'], 'status' => $ex['status'],
            'buyerName' => $ex['buyer_name'], 'adminFee' => (float) $ex['admin_fee'],
            'shippingCostSeller' => (float) $ex['shipping_cost_seller'], 'voucherSellerBorne' => (float) $ex['voucher_seller_borne'],
            'shippingChargedToBuyer' => (float) $ex['shipping_charged_to_buyer'], 'otherIncome' => (float) $ex['other_income'],
            'otherCost' => (float) $ex['other_cost'], 'productRevenue' => (float) $ex['product_revenue'],
            'items' => $items, 'note' => $ex['note'], '_hasIncome' => ((int) $ex['income_verified']) === 1,
        ];
    }
}
