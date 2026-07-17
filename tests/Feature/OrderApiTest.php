<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Menu $susu;

    private Menu $tahu;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Signature']);
        $this->susu = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Original',
            'ukuran' => 'Reguler',
            'harga' => 17000,
        ]);
        $this->tahu = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Original',
            'ukuran' => 'Large',
            'harga' => 21000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'nama' => 'Budi',
            'nomor_wa' => '0812-3456-7890',
            'nomor_meja' => '12',
            'items' => [
                ['menu_id' => $this->susu->id, 'qty' => 2],
                ['menu_id' => $this->tahu->id, 'qty' => 1],
            ],
        ], $override);
    }

    public function test_order_publik_tanpa_auth_membuat_transaksi_pending(): void
    {
        $respon = $this->postJson('/api/order', $this->payload())->assertCreated();

        // 2x17000 + 1x21000 = 55000 — dihitung server
        $respon->assertJsonPath('status', 'pending')
            ->assertJsonPath('nomor_meja', '12')
            ->assertJsonPath('total', 55000)
            ->assertJsonPath('kode_pesanan', '#A01')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.nama_menu', 'Original');

        $this->assertStringContainsString('#A01', $respon->json('pesan'));

        $transaksi = Transaksi::first();
        $this->assertNull($transaksi->user_id); // belum ada kasir
        $this->assertSame('self_order', $transaksi->detailTransaksi()->first()->sumber);
        $this->assertSame('12', $transaksi->detailTransaksi()->first()->nomor_meja);

        // loyalty dibuat dengan poin 0 — poin TIDAK bertambah saat pending
        $this->assertSame(0, Loyalty::first()->poin);
    }

    public function test_harga_kiriman_client_diabaikan_total_tetap_dari_server(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['harga'] = 1; // percobaan manipulasi harga
        $payload['total'] = 5;

        $this->postJson('/api/order', $payload)
            ->assertCreated()
            ->assertJsonPath('total', 55000)
            ->assertJsonPath('items.0.harga_satuan', 17000);
    }

    public function test_kode_pesanan_berurutan_dan_reset_di_hari_berbeda(): void
    {
        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A01');
        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A02');

        $this->travel(1)->days();

        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A01');

        $this->travelBack();
    }

    public function test_variasi_format_nomor_wa_memakai_customer_yang_sama(): void
    {
        $this->postJson('/api/order', $this->payload(['nomor_wa' => '0812-3456-7890']));
        $this->postJson('/api/order', $this->payload(['nomor_wa' => '+62 812 3456 7890']));
        $this->postJson('/api/order', $this->payload(['nomor_wa' => '812345 67890']));

        $this->assertSame(1, Customer::count());
        $this->assertSame('6281234567890', Customer::first()->no_wa);
    }

    public function test_validasi_kontrak_v1(): void
    {
        // items kosong
        $this->postJson('/api/order', $this->payload(['items' => []]))
            ->assertStatus(422)->assertJsonPath('error', 'items_kosong');

        // qty tidak valid
        $this->postJson('/api/order', $this->payload(['items' => [['menu_id' => $this->susu->id, 'qty' => 0]]]))
            ->assertStatus(422)->assertJsonPath('error', 'qty_invalid');

        // menu tidak ada / nonaktif
        $this->postJson('/api/order', $this->payload(['items' => [['menu_id' => 9999, 'qty' => 1]]]))
            ->assertStatus(422)->assertJsonPath('error', 'menu_tidak_tersedia');

        $this->susu->update(['is_active' => false]);
        $this->postJson('/api/order', $this->payload(['items' => [['menu_id' => $this->susu->id, 'qty' => 1]]]))
            ->assertStatus(422)->assertJsonPath('error', 'menu_tidak_tersedia');

        // nomor WA invalid
        $this->postJson('/api/order', $this->payload(['nomor_wa' => 'abc']))
            ->assertStatus(422)->assertJsonPath('error', 'nomor_wa_invalid');

        // field wajib hilang
        $this->postJson('/api/order', ['nama' => 'Budi'])
            ->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_menu_publik_terkelompok_per_kategori_hanya_yang_aktif(): void
    {
        $this->tahu->update(['is_active' => false]);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(1, 'kategori')
            ->assertJsonPath('kategori.0.nama', 'Soya Signature')
            ->assertJsonCount(1, 'kategori.0.menu')
            ->assertJsonPath('kategori.0.menu.0.harga', 17000);
    }
}
