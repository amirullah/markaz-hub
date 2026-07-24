<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDummyData extends Command
{
    protected $signature = 'dummy:seed {email=markazvirtual@gmail.com}';
    protected $description = 'Isi data dummy untuk akun tertentu (toko, produk, pesanan, stok)';

    private const PROCESSING = ['PENDING', 'PROCESSING', 'PACKED', 'SHIPPED', 'FAILED'];
    private const MARKETPLACE_STATUS = ['SHOPEE' => ['PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED'], 'TIKTOKTOKO' => ['PAID', 'SHIPPED', 'COMPLETED', 'RETURNED']];
    private const NAMES = ['Ahmad Fauzi', 'Siti Rahmawati', 'Budi Santoso', 'Dewi Lestari', 'Rudi Hermawan', 'Ani Nurhayati', 'Doni Prasetyo', 'Rina Marlina', 'Agus Wijaya', 'Mega Sari', 'Fitri Handayani', 'Hendra Gunawan', 'Lina Susanti', 'Rizky Pratama', 'Wulan Sari'];

    private int $orgId;

    public function handle(): int
    {
        $user = User::with('organization')->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error("User dengan email {$this->argument('email')} tidak ditemukan.");
            return 1;
        }
        $this->orgId = (int) $user->organization_id;
        $this->info("Mengisi data untuk {$user->name} (org #{$this->orgId})...");

        $this->seedCategories();
        $this->seedSuppliers();
        $stores = $this->seedStores();
        $products = $this->seedProducts();
        $this->seedOrders($stores, $products);
        $this->seedStockMovements($products);

        $this->newLine();
        $this->info("Selesai! Data dummy untuk {$user->name}.");
        return 0;
    }

    private function seedCategories(): void
    {
        $now = now();
        $cats = [
            ['name' => 'Elektronik', 'fee_shopee' => 4.0, 'fee_tokotiktok' => 3.5],
            ['name' => 'Fashion Pria', 'fee_shopee' => 6.5, 'fee_tokotiktok' => 5.5],
            ['name' => 'Fashion Wanita', 'fee_shopee' => 6.5, 'fee_tokotiktok' => 5.5],
            ['name' => 'Fashion Muslim', 'fee_shopee' => 5.0, 'fee_tokotiktok' => 4.5],
            ['name' => 'Makanan & Minuman', 'fee_shopee' => 3.0, 'fee_tokotiktok' => 2.5],
            ['name' => 'Kesehatan', 'fee_shopee' => 4.5, 'fee_tokotiktok' => 3.5],
            ['name' => 'Kecantikan', 'fee_shopee' => 5.5, 'fee_tokotiktok' => 4.5],
            ['name' => 'Perawatan Tubuh', 'fee_shopee' => 5.0, 'fee_tokotiktok' => 4.0],
            ['name' => 'Bayi & Anak', 'fee_shopee' => 4.5, 'fee_tokotiktok' => 3.5],
            ['name' => 'Mainan & Hobi', 'fee_shopee' => 5.0, 'fee_tokotiktok' => 4.0],
            ['name' => 'Olahraga', 'fee_shopee' => 4.5, 'fee_tokotiktok' => 3.5],
            ['name' => 'Otomotif', 'fee_shopee' => 4.0, 'fee_tokotiktok' => 3.0],
            ['name' => 'Perlengkapan Rumah', 'fee_shopee' => 5.0, 'fee_tokotiktok' => 4.0],
            ['name' => 'Dapur', 'fee_shopee' => 5.0, 'fee_tokotiktok' => 4.0],
            ['name' => 'Furniture', 'fee_shopee' => 4.5, 'fee_tokotiktok' => 3.5],
            ['name' => 'Buku & Alat Tulis', 'fee_shopee' => 5.5, 'fee_tokotiktok' => 4.5],
            ['name' => 'Aksesoris HP', 'fee_shopee' => 4.0, 'fee_tokotiktok' => 3.0],
            ['name' => 'Komputer & Laptop', 'fee_shopee' => 3.5, 'fee_tokotiktok' => 2.5],
            ['name' => 'Kamera', 'fee_shopee' => 3.5, 'fee_tokotiktok' => 2.5],
            ['name' => 'Perhiasan & Logam Mulia', 'fee_shopee' => 3.0, 'fee_tokotiktok' => 2.0],
            ['name' => 'Tas & Dompet', 'fee_shopee' => 6.0, 'fee_tokotiktok' => 5.0],
            ['name' => 'Sepatu', 'fee_shopee' => 6.0, 'fee_tokotiktok' => 5.0],
            ['name' => 'Jam Tangan', 'fee_shopee' => 5.5, 'fee_tokotiktok' => 4.5],
            ['name' => 'Voucher & Tiket', 'fee_shopee' => 2.0, 'fee_tokotiktok' => 1.5],
            ['name' => 'Pets & Hewan', 'fee_shopee' => 4.5, 'fee_tokotiktok' => 3.5],
            ['name' => 'Produk Lainnya', 'fee_shopee' => 5.0, 'fee_tokotiktok' => 4.0],
        ];
        $inserted = 0;
        foreach ($cats as $c) {
            if (! DB::table('categories')->where('organization_id', $this->orgId)->where('name', $c['name'])->exists()) {
                DB::table('categories')->insert(array_merge($c, ['organization_id' => $this->orgId, 'created_at' => $now, 'updated_at' => $now]));
                $inserted++;
            }
        }
        $this->info("Kategori: {$inserted} baru.");
    }

    private function seedSuppliers(): void
    {
        $now = now();
        $names = ['PT Sukses Abadi', 'CV Berkah Jaya', 'UD Niaga Baru', 'Distributor Inti', 'Supplier Global'];
        $inserted = 0;
        foreach ($names as $n) {
            if (! DB::table('suppliers')->where('organization_id', $this->orgId)->where('name', $n)->exists()) {
                DB::table('suppliers')->insert([
                    'organization_id' => $this->orgId, 'name' => $n, 'type' => 'OTHER',
                    'note' => 'Supplier ' . $n, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $inserted++;
            }
        }
        $this->info("Supplier: {$inserted} baru.");
    }

    private function seedStores(): array
    {
        $now = now();
        $stores = [];
        $data = [
            ['name' => 'Markaz Store', 'marketplace' => 'SHOPEE', 'fulfillment_mode' => 'self'],
            ['name' => 'Markaz Fashion', 'marketplace' => 'SHOPEE', 'fulfillment_mode' => 'dropship'],
            ['name' => 'Markaz Official', 'marketplace' => 'TIKTOKTOKO', 'fulfillment_mode' => 'both'],
        ];
        foreach ($data as $d) {
            $id = DB::table('stores')->where('organization_id', $this->orgId)->where('name', $d['name'])->value('id');
            if (! $id) {
                $id = DB::table('stores')->insertGetId(array_merge($d, ['organization_id' => $this->orgId, 'active' => true, 'created_at' => $now, 'updated_at' => $now]));
            }
            $stores[] = (object) ['id' => $id, 'marketplace' => $d['marketplace'], 'fulfillment_mode' => $d['fulfillment_mode']];
        }
        $this->info('Toko: ' . count($stores) . ' siap.');
        return $stores;
    }

    private function seedProducts(): array
    {
        $catIds = DB::table('categories')->where('organization_id', $this->orgId)->pluck('id', 'name');
        $supIds = DB::table('suppliers')->where('organization_id', $this->orgId)->pluck('id')->toArray();
        $now = now();
        $list = [
            ['sku' => 'KMR-001', 'name' => 'Kemeja Putih Polos Pria', 'cat' => 'Fashion Pria', 'hpp' => 45000, 'drop' => 52000, 'stk' => 50, 'min' => 10],
            ['sku' => 'KMR-002', 'name' => 'Kemeja Batik Lengan Panjang', 'cat' => 'Fashion Pria', 'hpp' => 65000, 'drop' => 72000, 'stk' => 30, 'min' => 5],
            ['sku' => 'DRS-001', 'name' => 'Dress Wanita A-Line', 'cat' => 'Fashion Wanita', 'hpp' => 85000, 'drop' => 95000, 'stk' => 25, 'min' => 5],
            ['sku' => 'BLU-001', 'name' => 'Blouse Wanita Katun', 'cat' => 'Fashion Wanita', 'hpp' => 55000, 'drop' => 62000, 'stk' => 40, 'min' => 8],
            ['sku' => 'JIL-001', 'name' => 'Hijab Segi Empat Ceruti', 'cat' => 'Fashion Muslim', 'hpp' => 15000, 'drop' => 19000, 'stk' => 200, 'min' => 50],
            ['sku' => 'KOP-001', 'name' => 'Kopi Arabica Gayo 200gr', 'cat' => 'Makanan & Minuman', 'hpp' => 35000, 'drop' => 40000, 'stk' => 100, 'min' => 20],
            ['sku' => 'SNK-001', 'name' => 'Keripik Singkong Pedas', 'cat' => 'Makanan & Minuman', 'hpp' => 8000, 'drop' => 10000, 'stk' => 0, 'min' => 30],
            ['sku' => 'MIN-001', 'name' => 'Madu Murni 250ml', 'cat' => 'Kesehatan', 'hpp' => 45000, 'drop' => 50000, 'stk' => 60, 'min' => 10],
            ['sku' => 'SKN-001', 'name' => 'Serum Vitamin C Wajah', 'cat' => 'Kecantikan', 'hpp' => 35000, 'drop' => 42000, 'stk' => 45, 'min' => 10],
            ['sku' => 'SMP-001', 'name' => 'Shampo Organik 100ml', 'cat' => 'Perawatan Tubuh', 'hpp' => 22000, 'drop' => 27000, 'stk' => 80, 'min' => 15],
            ['sku' => 'MBN-001', 'name' => 'Baby Diaper Size M (30pcs)', 'cat' => 'Bayi & Anak', 'hpp' => 42000, 'drop' => 48000, 'stk' => 35, 'min' => 10],
            ['sku' => 'MLK-001', 'name' => 'Mainan Puzzle Kayu 24pc', 'cat' => 'Mainan & Hobi', 'hpp' => 28000, 'drop' => 33000, 'stk' => 20, 'min' => 5],
            ['sku' => 'YGA-001', 'name' => 'Yoga Mat Premium 6mm', 'cat' => 'Olahraga', 'hpp' => 95000, 'drop' => 110000, 'stk' => 15, 'min' => 3],
            ['sku' => 'KCL-001', 'name' => 'Kunci L Set 12 pcs', 'cat' => 'Otomotif', 'hpp' => 25000, 'drop' => 30000, 'stk' => 40, 'min' => 8],
            ['sku' => 'LPN-001', 'name' => 'Lampu LED Panel 12W', 'cat' => 'Perlengkapan Rumah', 'hpp' => 18000, 'drop' => 22000, 'stk' => 55, 'min' => 10],
            ['sku' => 'WKN-001', 'name' => 'Wajan Anti Lengket 28cm', 'cat' => 'Dapur', 'hpp' => 55000, 'drop' => 62000, 'stk' => 25, 'min' => 5],
            ['sku' => 'BKU-001', 'name' => 'Buku Notes Hardcover A5', 'cat' => 'Buku & Alat Tulis', 'hpp' => 12000, 'drop' => 15000, 'stk' => 3, 'min' => 10],
            ['sku' => 'CVR-001', 'name' => 'Softcase HP Anti Shock', 'cat' => 'Aksesoris HP', 'hpp' => 8000, 'drop' => 11000, 'stk' => 100, 'min' => 20],
            ['sku' => 'TAS-001', 'name' => 'Tas Selempang Pria Kanvas', 'cat' => 'Tas & Dompet', 'hpp' => 55000, 'drop' => 65000, 'stk' => 18, 'min' => 5],
            ['sku' => 'SPT-001', 'name' => 'Sepatu Sneakers Pria', 'cat' => 'Sepatu', 'hpp' => 120000, 'drop' => 140000, 'stk' => 12, 'min' => 3],
            ['sku' => 'JAM-001', 'name' => 'Jam Tangan Pria Digital', 'cat' => 'Jam Tangan', 'hpp' => 45000, 'drop' => 52000, 'stk' => 8, 'min' => 5],
            ['sku' => 'SAR-001', 'name' => 'Sarung Bantal Sofa Set', 'cat' => 'Furniture', 'hpp' => 35000, 'drop' => 42000, 'stk' => 10, 'min' => 3],
            ['sku' => 'ORG-001', 'name' => 'Organizer Desktop 3 Laci', 'cat' => 'Perlengkapan Rumah', 'hpp' => 25000, 'drop' => 30000, 'stk' => 30, 'min' => 5],
            ['sku' => 'TUM-001', 'name' => 'Tumbler Stainless 500ml', 'cat' => 'Olahraga', 'hpp' => 35000, 'drop' => 40000, 'stk' => 22, 'min' => 5],
        ];
        $products = [];
        foreach ($list as $d) {
            $id = DB::table('products')->where('organization_id', $this->orgId)->where('sku', $d['sku'])->value('id');
            if (! $id) {
                $id = DB::table('products')->insertGetId([
                    'organization_id' => $this->orgId, 'sku' => $d['sku'], 'name' => $d['name'],
                    'cost_price' => $d['hpp'], 'dropship_cost' => $d['drop'],
                    'stock' => $d['stk'], 'min_stock' => $d['min'],
                    'category_id' => $catIds[$d['cat']] ?? null,
                    'supplier_id' => $supIds ? $supIds[array_rand($supIds)] : null,
                    'active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $p = DB::table('products')->where('id', $id)->first(['id', 'sku', 'name', 'cost_price', 'dropship_cost', 'stock', 'category_id']);
            $products[] = $p;
        }
        $this->info('Produk: ' . count($products) . ' siap.');
        return $products;
    }

    private function seedOrders(array $stores, array $products): void
    {
        $catFees = DB::table('categories')->where('organization_id', $this->orgId)->pluck('fee_shopee', 'id');
        $now = now();
        $orderRows = [];
        $itemRows = [];

        for ($i = 0; $i < 80; $i++) {
            $store = $stores[array_rand($stores)];
            $mp = $store->marketplace;
            $status = self::MARKETPLACE_STATUS[$mp][array_rand(self::MARKETPLACE_STATUS[$mp])];
            $fulfillment = $store->fulfillment_mode === 'dropship' ? 'DROPSHIP' : 'SELF';
            $daysAgo = rand(0, 60);
            $date = $now->copy()->subDays($daysAgo);
            $extNo = substr($mp, 0, 3) . '-' . $date->format('ymd') . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -5);

            $numItems = rand(1, 3);
            $keys = array_rand($products, min($numItems, count($products)));
            $keys = is_array($keys) ? $keys : [$keys];
            $totalRev = 0;
            $totalCogs = 0;
            $items = [];
            foreach ($keys as $k) {
                $prod = $products[$k];
                $qty = rand(1, 3);
                $price = round((int) $prod->cost_price * (1 + rand(20, 100) / 100) / 500) * 500;
                $totalRev += $price * $qty;
                $totalCogs += (int) $prod->cost_price * $qty;
                $items[] = ['sku' => $prod->sku, 'name' => $prod->name, 'qty' => $qty, 'unit_price' => $price, 'unit_cost' => (int) $prod->cost_price, 'product_id' => $prod->id];
            }
            $feePct = ($catFees[(int) ($products[$keys[0]]->category_id ?? 0)] ?? 5) / 100;
            $adminFee = rand(0, 1) ? (int) ($totalRev * $feePct) : 0;
            $voucher = rand(0, 1) ? rand(2000, 10000) : 0;
            $shippingCost = rand(0, 1) ? rand(3000, 10000) : 0;
            $dropCost = $fulfillment === 'DROPSHIP' ? (int) $products[$keys[0]]->dropship_cost * $items[0]['qty'] : 0;
            $procStatus = self::PROCESSING[array_rand(self::PROCESSING)];
            $incomeVerified = in_array($status, ['COMPLETED', 'CANCELLED', 'RETURNED']) && rand(0, 2) > 0;

            $orderRows[] = [
                'organization_id' => $this->orgId, 'store_id' => $store->id, 'external_no' => $extNo,
                'marketplace' => $mp, 'status' => $status, 'processing_status' => $procStatus,
                'fulfillment' => $fulfillment, 'order_date' => $date,
                'buyer_name' => self::NAMES[array_rand(self::NAMES)],
                'product_revenue' => $totalRev, 'shipping_charged_to_buyer' => rand(5000, 20000),
                'other_income' => 0, 'cogs' => $totalCogs, 'admin_fee' => $adminFee,
                'shipping_cost_seller' => $shippingCost, 'voucher_seller_borne' => $voucher,
                'dropship_cost' => $dropCost, 'dropship_modal' => 0, 'other_cost' => 0,
                'income_verified' => $incomeVerified,
                'tracking_number' => $procStatus === 'SHIPPED' ? 'RESI' . rand(100000, 999999) : null,
                'courier' => $procStatus === 'SHIPPED' ? ['JNE', 'J&T', 'SiCepat'][array_rand([0 => 'JNE', 1 => 'J&T', 2 => 'SiCepat'])] : null,
                'shipped_at' => $procStatus === 'SHIPPED' ? $date->copy()->addDays(rand(1, 3)) : null,
                'failed_reason' => $procStatus === 'FAILED' ? 'Stok tidak mencukupi (data dummy)' : null,
                'note' => rand(0, 3) > 2 ? 'Barang pecah belah' : null,
                'created_at' => $date, 'updated_at' => $now,
            ];

            $itemRows[] = ['items' => $items, 'extNo' => $extNo];
        }

        foreach (array_chunk($orderRows, 25) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $orders = DB::table('orders')->where('organization_id', $this->orgId)
            ->whereIn('external_no', array_column($itemRows, 'extNo'))
            ->pluck('id', 'external_no');

        $flatItems = [];
        foreach ($itemRows as $ir) {
            $oid = $orders[$ir['extNo']] ?? null;
            if (! $oid) continue;
            foreach ($ir['items'] as $it) {
                $flatItems[] = array_merge($it, ['order_id' => $oid, 'organization_id' => $this->orgId]);
            }
        }
        foreach (array_chunk($flatItems, 50) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }

        $this->info('Pesanan: ' . count($orderRows) . ' + item.');
    }

    private function seedStockMovements(array $products): void
    {
        $now = now();
        $rows = [];
        foreach ($products as $p) {
            if ((int) $p->stock > 0) {
                $rows[] = [
                    'organization_id' => $this->orgId, 'product_id' => $p->id,
                    'type' => 'IN', 'qty' => (int) $p->stock,
                    'reference' => 'Stok awal', 'note' => 'Data dummy',
                    'created_at' => $now->copy()->subDays(rand(1, 30)), 'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('stock_movements')->insert($chunk);
        }
        $this->info('Mutasi stok: ' . count($rows) . ' baris.');
    }
}
