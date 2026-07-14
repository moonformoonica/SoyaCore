<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuKategoriTest extends TestCase
{
    use RefreshDatabase;

    private function kasir(): User
    {
        return User::factory()->create(['role' => 'kasir']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    public function test_kasir_bisa_melihat_daftar_kategori_dan_menu(): void
    {
        $kategori = Kategori::create(['nama' => 'Susu Kedelai']);
        Menu::create(['kategori_id' => $kategori->id, 'nama' => 'Susu Botol', 'harga' => 10000]);

        Sanctum::actingAs($this->kasir());

        $this->getJson('/api/kategori')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/menu')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_kasir_tidak_boleh_menulis_kategori_atau_menu(): void
    {
        $kategori = Kategori::create(['nama' => 'Snack']);

        Sanctum::actingAs($this->kasir());

        $this->postJson('/api/kategori', ['nama' => 'Baru'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');

        $this->postJson('/api/menu', ['kategori_id' => $kategori->id, 'nama' => 'X', 'harga' => 1000])
            ->assertStatus(403);
    }

    public function test_manager_crud_kategori_penuh(): void
    {
        Sanctum::actingAs($this->manager());

        $id = $this->postJson('/api/kategori', ['nama' => 'Snack'])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/kategori/{$id}")->assertOk()->assertJsonPath('data.nama', 'Snack');

        $this->putJson("/api/kategori/{$id}", ['nama' => 'Camilan'])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Camilan');

        $this->deleteJson("/api/kategori/{$id}")->assertOk();
        $this->assertDatabaseMissing('kategori', ['id' => $id]);
    }

    public function test_hapus_kategori_yang_masih_punya_menu_ditolak_409(): void
    {
        $kategori = Kategori::create(['nama' => 'Susu Kedelai']);
        Menu::create(['kategori_id' => $kategori->id, 'nama' => 'Susu Botol', 'harga' => 10000]);

        Sanctum::actingAs($this->manager());

        $this->deleteJson("/api/kategori/{$kategori->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'kategori_masih_dipakai');

        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);
    }

    public function test_manager_crud_menu_dengan_validasi(): void
    {
        $kategori = Kategori::create(['nama' => 'Susu Kedelai']);

        Sanctum::actingAs($this->manager());

        // harga negatif ditolak
        $this->postJson('/api/menu', [
            'kategori_id' => $kategori->id,
            'nama' => 'Susu Botol',
            'harga' => -1,
        ])->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');

        $id = $this->postJson('/api/menu', [
            'kategori_id' => $kategori->id,
            'nama' => 'Susu Botol',
            'rasa' => 'Original',
            'ukuran' => '250ml',
            'harga' => 10000,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/menu/{$id}", ['harga' => 11000])
            ->assertOk()
            ->assertJsonPath('data.harga', 11000);

        $this->deleteJson("/api/menu/{$id}")->assertOk();
        $this->assertDatabaseMissing('menu', ['id' => $id]);
    }

    public function test_filter_menu_per_kategori_dan_status_aktif(): void
    {
        $susu = Kategori::create(['nama' => 'Susu Kedelai']);
        $snack = Kategori::create(['nama' => 'Snack']);
        Menu::create(['kategori_id' => $susu->id, 'nama' => 'Susu Botol', 'harga' => 10000]);
        Menu::create(['kategori_id' => $snack->id, 'nama' => 'Tahu Bakso', 'harga' => 15000]);
        Menu::create(['kategori_id' => $snack->id, 'nama' => 'Keripik Lama', 'harga' => 5000, 'is_active' => false]);

        Sanctum::actingAs($this->kasir());

        $this->getJson("/api/menu?kategori_id={$snack->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/menu?kategori_id={$snack->id}&is_active=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Tahu Bakso');
    }
}
