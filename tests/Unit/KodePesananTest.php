<?php

namespace Tests\Unit;

use App\Support\KodePesanan;
use PHPUnit\Framework\TestCase;

class KodePesananTest extends TestCase
{
    public function test_dua_digit_pertama_memakai_huruf_a(): void
    {
        $this->assertSame('#A00', KodePesanan::dariUrutan(0));
        $this->assertSame('#A01', KodePesanan::dariUrutan(1));
        $this->assertSame('#A99', KodePesanan::dariUrutan(99));
    }

    public function test_huruf_naik_setelah_angka_habis(): void
    {
        $this->assertSame('#B00', KodePesanan::dariUrutan(100));
        $this->assertSame('#B01', KodePesanan::dariUrutan(101));
        $this->assertSame('#C00', KodePesanan::dariUrutan(200));
        $this->assertSame('#Z99', KodePesanan::dariUrutan(2599));
    }

    public function test_berputar_kembali_ke_a_setelah_z99(): void
    {
        // 2.600 kode per minggu, jauh di atas kebutuhan kedai. Kalaupun
        // terlampaui, nomornya berputar, bukan melempar error di tengah
        // pelanggan mengantre.
        $this->assertSame('#A00', KodePesanan::dariUrutan(2600));
        $this->assertSame('#A01', KodePesanan::dariUrutan(2601));
    }

    public function test_seluruh_kode_dalam_satu_minggu_berbeda(): void
    {
        $kode = [];
        for ($i = 0; $i < 2600; $i++) {
            $kode[] = KodePesanan::dariUrutan($i);
        }

        $this->assertCount(2600, array_unique($kode));
    }

    public function test_normalisasi_menerima_tanpa_pagar_dan_huruf_kecil(): void
    {
        foreach (['#A01', 'A01', 'a01', ' a01 '] as $bentuk) {
            $this->assertSame('#A01', KodePesanan::normalisasi($bentuk));
        }
    }
}
