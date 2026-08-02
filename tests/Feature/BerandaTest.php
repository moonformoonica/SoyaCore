<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menggantikan `ExampleTest` bawaan Laravel, yang menuntut `GET /`
 * mengembalikan 200 padahal route-nya memang tidak pernah ada di aplikasi ini.
 * Test itu gagal sejak awal dan lama-lama jadi kebisingan yang membuat orang
 * terbiasa mengabaikan hasil suite.
 */
class BerandaTest extends TestCase
{
    public function test_domain_telanjang_mengarahkan_ke_login(): void
    {
        // Ini yang dibuka orang saat dikirimi link ngrok atau alamat
        // production: tanpa route ini mereka mendarat di 404 dan mengira
        // aplikasinya rusak.
        $this->get('/')->assertRedirect('/login');
    }

    public function test_halaman_login_bisa_dibuka(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_halaman_scan_publik_bisa_dibuka_tanpa_login(): void
    {
        $this->get('/scan')->assertOk();
    }
}
