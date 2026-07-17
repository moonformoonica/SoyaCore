<?php

namespace Tests\Unit;

use App\Support\NomorWa;
use PHPUnit\Framework\TestCase;

class NomorWaTest extends TestCase
{
    public function test_semua_variasi_penulisan_menghasilkan_nomor_yang_sama(): void
    {
        $variasi = [
            '0812-3456-7890',
            '+62 812 3456 7890',
            '812345 67890',
            ' 0812 3456 7890 ',
            '62812-3456-7890',
            '(0812) 3456 7890',
        ];

        foreach ($variasi as $input) {
            $this->assertSame('6281234567890', NomorWa::normalisasi($input), "Input gagal: {$input}");
        }
    }

    public function test_input_tanpa_digit_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', NomorWa::normalisasi('abc-def'));
        $this->assertSame('', NomorWa::normalisasi('   '));
    }

    public function test_kode_negara_lain_dibiarkan_apa_adanya(): void
    {
        $this->assertSame('6512345678', NomorWa::normalisasi('+65 1234 5678'));
    }
}
