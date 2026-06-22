<?php

namespace Tests\Unit;

use App\Services\ProfitService;
use PHPUnit\Framework\TestCase;

/**
 * GOLDEN TEST v2: ProfitService harus mereproduksi angka laba v1 (yang sudah diaudit)
 * PERSIS untuk tiap skenario. Skenario = salinan dari v1 (tests/Fixtures/golden_scenarios.json).
 * Jika ada yang meleset walau 1 rupiah -> bug porting, perbaiki sebelum rilis.
 */
class ProfitServiceGoldenTest extends TestCase
{
    public function test_golden_profit_dan_net_cocok_dengan_v1(): void
    {
        $svc = new ProfitService();
        $data = json_decode(file_get_contents(__DIR__ . '/../Fixtures/golden_scenarios.json'), true);
        $this->assertNotEmpty($data['scenarios'] ?? null, 'scenarios.json kosong');

        foreach ($data['scenarios'] as $s) {
            $this->assertEqualsWithDelta(
                (float) $s['expected_profit'],
                $svc->profit($s['input']),
                0.01,
                "Laba meleset utk skenario: {$s['id']}"
            );

            if (array_key_exists('expected_net', $s)) {
                $this->assertEqualsWithDelta(
                    (float) $s['expected_net'],
                    $svc->net($s['input']),
                    0.01,
                    "Net meleset utk skenario: {$s['id']}"
                );
            }
        }
    }
}
