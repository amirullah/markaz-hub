<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pengunci perilaku mp_merge_status() & integrasi status di mp_merge_orders():
 * status MAJU/TERMINAL diterapkan, MUNDUR diabaikan, terminal lama tak ditimpa,
 * dan finansial income tidak rusak saat status berubah. (Fungsi murni — tanpa DB.)
 */
class MergeStatusTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../app/Legacy/marketplace.php';
    }

    /** @return array<string, array{0: ?string, 1: ?string, 2: string}> */
    public static function transisiProvider(): array
    {
        return [
            'maju paid->completed' => ['PAID', 'COMPLETED', 'COMPLETED'],
            'mundur completed->paid dipertahankan' => ['COMPLETED', 'PAID', 'COMPLETED'],
            'terminal retur menimpa selesai' => ['COMPLETED', 'RETURNED', 'RETURNED'],
            'terminal batal menimpa selesai' => ['COMPLETED', 'CANCELLED', 'CANCELLED'],
            'maju pending->shipped' => ['PENDING', 'SHIPPED', 'SHIPPED'],
            'mundur shipped->pending dipertahankan' => ['SHIPPED', 'PENDING', 'SHIPPED'],
            'terminal lama tak di-un-cancel' => ['CANCELLED', 'COMPLETED', 'CANCELLED'],
            'terminal lama retur tetap' => ['RETURNED', 'COMPLETED', 'RETURNED'],
            'baru kosong -> lama dipertahankan' => ['COMPLETED', '', 'COMPLETED'],
            'baru null -> lama dipertahankan' => ['COMPLETED', null, 'COMPLETED'],
            'raw indo: selesai->dikembalikan' => ['Selesai', 'Dikembalikan', 'RETURNED'],
            'raw indo maju: perlu dikirim->selesai' => ['Perlu dikirim', 'Selesai', 'COMPLETED'],
            'lama null -> pakai baru' => [null, 'Selesai', 'COMPLETED'],
            'sama tetap' => ['PAID', 'PAID', 'PAID'],
            'dua terminal -> lama menang' => ['CANCELLED', 'RETURNED', 'CANCELLED'],
        ];
    }

    #[DataProvider('transisiProvider')]
    public function test_transisi_status(?string $lama, ?string $baru, string $harap): void
    {
        $this->assertSame($harap, mp_merge_status($lama, $baru));
    }

    private function item(string $sku): array
    {
        return ['sku' => $sku, 'name' => 'p', 'qty' => 1, 'qtyAssumed' => false, 'unitPrice' => 100000, 'shopeeId' => null];
    }

    public function test_merge_retur_menimpa_completed_tanpa_merusak_finansial_income(): void
    {
        $lama = ['externalNo' => 'X1', 'status' => 'COMPLETED', '_hasIncome' => true, 'productRevenue' => 100000.0,
            'adminFee' => 20000.0, 'orderDate' => '2025-05-01', 'buyerName' => 'A', 'items' => [$this->item('SKU-A')]];
        $baru = ['externalNo' => 'X1', 'status' => 'Dikembalikan', '_hasIncome' => false, 'productRevenue' => 0.0,
            'adminFee' => 0.0, 'orderDate' => '2025-05-01', 'buyerName' => '', 'items' => [$this->item('SKU-A')]];

        $m = mp_merge_orders([[$lama], [$baru]])[0];

        $this->assertSame('RETURNED', $m['status']);
        $this->assertSame(100000, (int) $m['productRevenue'], 'finansial income tidak boleh ditimpa file pesanan');
        $this->assertSame(20000, (int) $m['adminFee']);
    }

    public function test_merge_estimasi_jadi_final_saat_income_masuk(): void
    {
        $lama = ['externalNo' => 'X2', 'status' => 'PAID', '_hasIncome' => false, 'productRevenue' => 50000.0,
            'adminFee' => 0.0, 'orderDate' => '2025-05-02', 'buyerName' => 'B', 'items' => [$this->item('SKU-B')]];
        $baru = ['externalNo' => 'X2', 'status' => 'Selesai', '_hasIncome' => true, 'productRevenue' => 50000.0,
            'adminFee' => 8000.0, 'shippingCostSeller' => 0, 'voucherSellerBorne' => 0, 'otherIncome' => 0,
            'otherCost' => 0, 'shippingChargedToBuyer' => 0, 'orderDate' => '2025-05-02', 'buyerName' => 'B', 'items' => [$this->item('SKU-B')]];

        $m = mp_merge_orders([[$lama], [$baru]])[0];

        $this->assertSame('COMPLETED', $m['status']);
        $this->assertSame(8000, (int) $m['adminFee']);
        $this->assertTrue((bool) $m['_hasIncome']);
    }

    public function test_merge_file_lama_status_mundur_diabaikan(): void
    {
        $lama = ['externalNo' => 'X3', 'status' => 'COMPLETED', '_hasIncome' => true, 'productRevenue' => 70000.0,
            'adminFee' => 10000.0, 'orderDate' => '2025-05-03', 'buyerName' => 'C', 'items' => [$this->item('SKU-C')]];
        $baru = ['externalNo' => 'X3', 'status' => 'Perlu dikirim', '_hasIncome' => false, 'productRevenue' => 70000.0,
            'adminFee' => 0.0, 'orderDate' => '2025-05-03', 'buyerName' => 'C', 'items' => [$this->item('SKU-C')]];

        $m = mp_merge_orders([[$lama], [$baru]])[0];

        $this->assertSame('COMPLETED', $m['status']);
    }
}
