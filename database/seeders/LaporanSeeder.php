<?php

namespace Database\Seeders;

use App\Services\LaporanImporter;
use Illuminate\Database\Seeder;

/**
 * Seeder layer reporting — TERPISAH dari seeder POS live (DatabaseSeeder).
 * Logika impornya ada di LaporanImporter supaya dipakai bersama dengan
 * perintah `php artisan laporan:import`.
 *
 * Jalankan: php artisan db:seed --class=LaporanSeeder
 * (untuk update data rutin, pakai `php artisan laporan:import`)
 */
class LaporanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((new LaporanImporter)->import() as $tabel => $jumlah) {
            $this->command?->info("{$tabel}: {$jumlah} baris");
        }
    }
}
