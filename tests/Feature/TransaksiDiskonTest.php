<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransaksiDiskonTest extends TestCase
{
    use RefreshDatabase;

    private Menu $susu;

    private Menu $tahu;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Susu Kedelai']);
        $this->susu = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Susu Kedelai Botol',
            'rasa' => 'Original',
            'harga' => 10000,
        ]);
        $this->tahu = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Tahu Bakso',
            'harga' => 15000,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
    }

    private function buatTransaksi(array $payload = []): int
    {
        return $this->postJson('/api/transaksi', $payload)
            ->assertCreated()
            ->json('data.id');
    }

    public function test_alur_penuh_transaksi_diskon_sampai_lunas(): void
    {
        $id = $this->buatTransaksi([
            'customer' => ['nama' => 'Budi', 'no_wa' => '0812 3456 7890'],
            'nomor_meja' => '5',
        ]);

        // Tambah 2 item berbeda: 2x10000 + 1x15000 = 35000
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 2])
            ->assertOk();
        $respon = $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->tahu->id, 'qty' => 1])
            ->assertOk();

        $this->assertSame(35000, $respon->json('data.subtotal'));
        $this->assertSame(35000, $respon->json('data.total'));

        // Diskon preset 20% -> 7000
        $respon = $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 20])
            ->assertOk();
        $this->assertSame(20, $respon->json('data.diskon_persen'));
        $this->assertSame(7000, $respon->json('data.diskon_nilai'));
        $this->assertSame(28000, $respon->json('data.total'));

        // Ganti ke diskon custom nominal 5000
        $respon = $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'custom_nilai', 'nilai' => 5000])
            ->assertOk();
        $this->assertSame(0, $respon->json('data.diskon_persen'));
        $this->assertSame(5000, $respon->json('data.diskon_nilai'));
        $this->assertSame(30000, $respon->json('data.total'));

        // Bayar cash -> lunas
        $respon = $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])
            ->assertOk();
        $this->assertSame('lunas', $respon->json('data.status'));
        $this->assertSame('cash', $respon->json('data.metode_bayar'));
        $this->assertSame(1, $respon->json('data.point_earned'));
        $this->assertNotNull($respon->json('data.waktu_lunas'));
    }

    public function test_kode_pesanan_kasir_berurutan_harian(): void
    {
        $this->buatTransaksi();
        $id = $this->buatTransaksi();

        $this->getJson("/api/transaksi/{$id}")
            ->assertOk()
            ->assertJsonPath('data.kode_pesanan', '#K002');
    }

    public function test_qty_item_bisa_diubah_dan_item_bisa_dihapus(): void
    {
        $id = $this->buatTransaksi();

        $itemId = $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1])
            ->json('data.items.0.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->tahu->id, 'qty' => 1]);

        $respon = $this->patchJson("/api/transaksi/{$id}/items/{$itemId}", ['qty' => 3])->assertOk();
        $this->assertSame(45000, $respon->json('data.subtotal')); // 3x10000 + 15000

        $respon = $this->deleteJson("/api/transaksi/{$id}/items/{$itemId}")->assertOk();
        $this->assertSame(15000, $respon->json('data.subtotal'));
        $this->assertSame(15000, $respon->json('data.total'));
    }

    public function test_menu_sama_digabung_dengan_menambah_qty(): void
    {
        $id = $this->buatTransaksi();

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1]);
        $respon = $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 2]);

        $this->assertCount(1, $respon->json('data.items'));
        $this->assertSame(3, $respon->json('data.items.0.qty'));
        $this->assertSame(30000, $respon->json('data.subtotal'));
    }

    public function test_menu_nonaktif_tidak_bisa_ditambahkan(): void
    {
        $this->susu->update(['is_active' => false]);
        $id = $this->buatTransaksi();

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error', 'menu_tidak_tersedia');
    }

    public function test_transaksi_lunas_menolak_perubahan(): void
    {
        $id = $this->buatTransaksi();
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1]);
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'qris'])->assertOk();

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->tahu->id, 'qty' => 1])
            ->assertStatus(409)
            ->assertJsonPath('error', 'transaksi_sudah_lunas');

        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 10])
            ->assertStatus(409);

        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])
            ->assertStatus(409);
    }

    public function test_diskon_preset_selain_10_20_50_ditolak(): void
    {
        $id = $this->buatTransaksi();
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1]);

        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 15])
            ->assertStatus(422)
            ->assertJsonPath('error', 'diskon_preset_invalid');
    }

    public function test_diskon_nominal_melebihi_subtotal_ditolak(): void
    {
        $id = $this->buatTransaksi();
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1]);

        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'custom_nilai', 'nilai' => 999999])
            ->assertStatus(422)
            ->assertJsonPath('error', 'diskon_melebihi_subtotal');

        // total tidak pernah negatif
        $this->getJson("/api/transaksi/{$id}")
            ->assertJsonPath('data.total', 10000);
    }

    public function test_diskon_nominal_diclamp_saat_item_dihapus(): void
    {
        $id = $this->buatTransaksi();
        $itemSusu = $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->susu->id, 'qty' => 1])
            ->json('data.items.0.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->tahu->id, 'qty' => 1]);

        // subtotal 25000, diskon nominal 20000
        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'custom_nilai', 'nilai' => 20000])->assertOk();

        // hapus item 10000 -> subtotal 15000 < diskon 20000 -> clamp, total 0
        $respon = $this->deleteJson("/api/transaksi/{$id}/items/{$itemSusu}")->assertOk();
        $this->assertSame(15000, $respon->json('data.subtotal'));
        $this->assertSame(15000, $respon->json('data.diskon_nilai'));
        $this->assertSame(0, $respon->json('data.total'));
    }

    public function test_bayar_tanpa_item_ditolak(): void
    {
        $id = $this->buatTransaksi();

        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'items_kosong');
    }

    public function test_batal_hanya_dari_pending(): void
    {
        $id = $this->buatTransaksi();

        $this->postJson("/api/transaksi/{$id}/batal")
            ->assertOk()
            ->assertJsonPath('data.status', 'batal');

        $this->postJson("/api/transaksi/{$id}/batal")
            ->assertStatus(409)
            ->assertJsonPath('error', 'transaksi_sudah_batal');
    }

    public function test_customer_dipakai_ulang_lewat_normalisasi_nomor_wa(): void
    {
        $this->buatTransaksi(['customer' => ['nama' => 'Budi', 'no_wa' => '0812 3456 7890']]);
        $this->buatTransaksi(['customer' => ['nama' => 'Budi', 'no_wa' => '081234567890']]);
        $this->buatTransaksi(['customer' => ['nama' => 'Budi', 'no_wa' => '0812-3456-7890 ']]);

        $this->assertSame(1, Customer::count());
        $this->assertSame('081234567890', Customer::first()->no_wa);
    }

    public function test_filter_list_transaksi_per_status(): void
    {
        $lunas = $this->buatTransaksi();
        $this->postJson("/api/transaksi/{$lunas}/items", ['menu_id' => $this->susu->id, 'qty' => 1]);
        $this->postJson("/api/transaksi/{$lunas}/bayar", ['metode_bayar' => 'cash']);
        $this->buatTransaksi(); // pending

        $this->getJson('/api/transaksi?status=lunas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'lunas');
    }
}
