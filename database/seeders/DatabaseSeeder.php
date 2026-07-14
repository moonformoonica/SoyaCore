<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Fix bug M1: key sebelumnya 'name' (bawaan skeleton) padahal kolom
        // di tabel users adalah 'nama', sehingga override diam-diam diabaikan.
        User::factory()->create([
            'nama' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'manager',
        ]);

        // Password default factory: 'password'
        User::factory()->create([
            'nama' => 'Kasir Gressoy',
            'email' => 'kasir@gressoy.test',
            'role' => 'kasir',
        ]);

        $susuKedelai = Kategori::create(['nama' => 'Susu Kedelai']);
        $snack = Kategori::create(['nama' => 'Snack']);

        Menu::create([
            'kategori_id' => $susuKedelai->id,
            'nama' => 'Susu Kedelai Botol',
            'rasa' => 'Original',
            'ukuran' => '250ml',
            'harga' => 10000,
        ]);

        Menu::create([
            'kategori_id' => $susuKedelai->id,
            'nama' => 'Susu Kedelai Botol',
            'rasa' => 'Cokelat',
            'ukuran' => '250ml',
            'harga' => 12000,
        ]);

        Menu::create([
            'kategori_id' => $susuKedelai->id,
            'nama' => 'Susu Kedelai Botol',
            'rasa' => 'Stroberi',
            'ukuran' => '250ml',
            'harga' => 12000,
        ]);

        Menu::create([
            'kategori_id' => $snack->id,
            'nama' => 'Tahu Bakso',
            'harga' => 15000,
        ]);
    }
}
