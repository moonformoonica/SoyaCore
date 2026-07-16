<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Services\DiskonEngine;
use PHPUnit\Framework\TestCase;

class DiskonEngineTest extends TestCase
{
    private DiskonEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DiskonEngine();
    }

    public function test_preset_20_persen_dihitung_benar(): void
    {
        $hasil = $this->engine->hitung(50000, 'preset', 20);

        $this->assertSame(['diskon_persen' => 20, 'diskon_nilai' => 10000], $hasil);
    }

    public function test_preset_selain_10_20_50_ditolak(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('preset');

        $this->engine->hitung(50000, 'preset', 15);
    }

    public function test_custom_persen_dibulatkan_ke_rupiah_terdekat(): void
    {
        $hasil = $this->engine->hitung(33333, 'custom_persen', 15);

        // 33333 * 15% = 4999.95 -> 5000
        $this->assertSame(['diskon_persen' => 15, 'diskon_nilai' => 5000], $hasil);
    }

    public function test_custom_persen_di_atas_100_ditolak(): void
    {
        $this->expectException(ApiException::class);

        $this->engine->hitung(50000, 'custom_persen', 101);
    }

    public function test_custom_nilai_disimpan_dengan_persen_nol(): void
    {
        $hasil = $this->engine->hitung(50000, 'custom_nilai', 7000);

        $this->assertSame(['diskon_persen' => 0, 'diskon_nilai' => 7000], $hasil);
    }

    public function test_distribusi_nominal_proporsional_dan_jumlahnya_tepat(): void
    {
        // 20000 dari subtotal 25000: item 10000 dapat floor(8000),
        // item terakhir dapat sisa 12000 — jumlah tepat 20000
        $hasil = $this->engine->distribusi([1 => 10000, 2 => 15000], 20000);

        $this->assertSame([1 => 8000, 2 => 12000], $hasil);
        $this->assertSame(20000, array_sum($hasil));
    }

    public function test_distribusi_nominal_penuh_setara_subtotal_tiap_item(): void
    {
        $hasil = $this->engine->distribusi([1 => 10000, 2 => 15000], 25000);

        $this->assertSame([1 => 10000, 2 => 15000], $hasil);
    }

    public function test_distribusi_nol_mengosongkan_semua_item(): void
    {
        $this->assertSame([1 => 0, 2 => 0], $this->engine->distribusi([1 => 10000, 2 => 15000], 0));
    }

    public function test_custom_nilai_melebihi_subtotal_ditolak(): void
    {
        try {
            $this->engine->hitung(50000, 'custom_nilai', 50001);
            $this->fail('ApiException tidak dilempar.');
        } catch (ApiException $e) {
            $this->assertSame('diskon_melebihi_subtotal', $e->errorCode);
            $this->assertSame(422, $e->status);
        }
    }
}
