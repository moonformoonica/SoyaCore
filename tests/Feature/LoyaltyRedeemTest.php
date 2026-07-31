<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyRedeemTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Loyalty $loyalty;

    private Menu $menuBiasa;

    private Menu $menuMurah;

    protected function setUp(): void
    {
        parent::setUp();

        // Menu reward sesuai katalog (ejaan sama dengan DB asli Gressoy)
        $signature = Kategori::create(['nama' => 'Soya Signature']);
        $coffee = Kategori::create(['nama' => 'Soya Coffee']);
        $tropical = Kategori::create(['nama' => 'Soya Tropical']);

        // dipakai dua peran: menu reward gratis_original, dan (sebagai
        // $menuMurah) item biasa yang subtotalnya di bawah min_subtotal
        $this->menuMurah = Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 17000]);
        Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Hot', 'harga' => 17000]);
        Menu::create(['kategori_id' => $coffee->id, 'nama' => 'Coffee Kopi', 'ukuran' => 'Reguler', 'harga' => 21000]);
        Menu::create(['kategori_id' => $tropical->id, 'nama' => 'Honey Lemon', 'ukuran' => 'Reguler', 'harga' => 20000]);
        Menu::create(['kategori_id' => $tropical->id, 'nama' => 'Mango Monggo', 'ukuran' => 'Reguler', 'harga' => 20000]);

        $this->menuBiasa = Menu::create([
            'kategori_id' => $signature->id, 'nama' => 'Taro Thanos', 'ukuran' => 'Large', 'harga' => 30000,
        ]);

        $this->customer = Customer::create(['nama' => 'Budi', 'no_wa' => '6281234567890']);
        $this->loyalty = Loyalty::create(['customer_id' => $this->customer->id, 'poin' => 400]);

        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
    }

    /**
     * Transaksi kasir pending ber-customer dengan 1 item Taro Thanos x qty
     * (atau menu lain kalau diberikan).
     */
    private function transaksiPending(int $qty = 1, ?Menu $menu = null): int
    {
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", [
            'menu_id' => ($menu ?? $this->menuBiasa)->id,
            'qty' => $qty,
        ])->assertOk();

        return $id;
    }

    public function test_redeem_diskon_10_masih_di_bawah_plafon(): void
    {
        $id = $this->transaksiPending(1); // subtotal 30000

        $respon = $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])
            ->assertOk();

        // 10% dari 30.000 = 3.000, masih di bawah plafon 5.000
        $this->assertSame(3000, $respon->json('data.diskon_nilai'));
        $this->assertSame(27000, $respon->json('data.total'));
        $this->assertSame('diskon_10', $respon->json('data.kode_redeem'));
        $this->assertSame(100, $respon->json('data.poin_ditukar'));
        $this->assertSame(300, $this->loyalty->fresh()->poin); // 400 - 100
    }

    public function test_redeem_diskon_20(): void
    {
        $id = $this->transaksiPending(1);

        $respon = $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_20'])
            ->assertOk();

        $this->assertSame(6000, $respon->json('data.diskon_nilai'));
        $this->assertSame(24000, $respon->json('data.total'));
        $this->assertSame(200, $this->loyalty->fresh()->poin); // 400 - 200
    }

    public function test_redeem_diskon_50_butuh_minimal_belanja_25000(): void
    {
        $this->loyalty->update(['poin' => 500]);

        // subtotal 17.000 -> di bawah min_subtotal, ditolak
        $id = $this->transaksiPending(1, $this->menuMurah);
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_50'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'minimal_pembelian_kurang');
        $this->assertSame(500, $this->loyalty->fresh()->poin); // tidak terpotong

        // subtotal 30.000 -> lolos; 50% = 15.000, masih di bawah plafon 25.000
        $id = $this->transaksiPending(1);
        $respon = $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_50'])
            ->assertOk();
        $this->assertSame(15000, $respon->json('data.diskon_nilai'));
        $this->assertSame(15000, $respon->json('data.total'));
        $this->assertSame(0, $this->loyalty->fresh()->poin); // 500 - 500
    }

    public function test_redeem_keempat_menu_gratis(): void
    {
        $skenario = [
            ['gratis_original', 'Original', 350],
            ['gratis_coffee_kopi', 'Coffee Kopi', 450],
            ['gratis_honey_lemon', 'Honey Lemon', 400],
            ['gratis_mango_monggo', 'Mango Monggo', 400],
        ];

        foreach ($skenario as [$kode, $namaMenu, $poin]) {
            // refresh dulu: instance test masih pegang nilai lama sehingga
            // update(500) bisa dianggap not-dirty dan di-skip Eloquent
            $this->loyalty->refresh()->update(['poin' => 500]);
            $id = $this->transaksiPending(1); // subtotal 30000

            $respon = $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => $kode])
                ->assertOk();

            $items = collect($respon->json('data.items'));
            $reward = $items->firstWhere('is_reward', true);

            $this->assertNotNull($reward, "Item reward tidak ada untuk {$kode}");
            $this->assertSame($namaMenu, $reward['nama']);
            $this->assertSame(0, $reward['subtotal']); // gratis: tidak menambah tagihan
            $this->assertGreaterThan(0, $reward['harga_satuan']); // snapshot harga asli untuk laporan
            $this->assertSame(30000, $respon->json('data.total')); // total tidak berubah
            $this->assertSame(500 - $poin, $this->loyalty->fresh()->poin);
        }
    }

    public function test_redeem_ditolak_kalau_poin_kurang(): void
    {
        $this->loyalty->update(['poin' => 50]); // diskon_10 butuh 100
        $id = $this->transaksiPending(1);

        $respon = $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])
            ->assertStatus(422);

        $respon->assertJsonPath('error', 'poin_kurang');
        $this->assertStringContainsString('kurang 50', $respon->json('message'));
        $this->assertSame(50, $this->loyalty->fresh()->poin);
    }

    public function test_satu_transaksi_hanya_satu_redemption(): void
    {
        $id = $this->transaksiPending(1);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_20'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'transaksi_sudah_redeem');
    }

    public function test_redeem_ditolak_setelah_lunas_dan_kode_tidak_dikenal_422(): void
    {
        $id = $this->transaksiPending(1);
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])
            ->assertStatus(409);

        $id2 = $this->transaksiPending(1);
        $this->postJson("/api/transaksi/{$id2}/redeem-poin", ['kode_redeem' => 'diskon_99'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'kode_redeem_invalid');
    }

    public function test_tandai_lunas_menambah_poin_dan_idempotent(): void
    {
        $this->loyalty->update(['poin' => 0]);
        $id = $this->transaksiPending(2); // total 60000 -> 60 poin

        $respon = $this->postJson("/api/transaksi/{$id}/tandai-lunas", ['metode_bayar' => 'qris'])
            ->assertOk();

        $this->assertSame('lunas', $respon->json('data.status'));
        $this->assertSame(60, $respon->json('data.point_earned'));
        $this->assertSame(60, $this->loyalty->fresh()->poin);

        // panggilan kedua ditolak 409 dan poin TIDAK bertambah lagi
        $this->postJson("/api/transaksi/{$id}/tandai-lunas", ['metode_bayar' => 'qris'])
            ->assertStatus(409);
        $this->assertSame(60, $this->loyalty->fresh()->poin);
    }

    public function test_alur_lengkap_redeem_lalu_lunas_poin_dihitung_dari_total_akhir(): void
    {
        $this->loyalty->update(['poin' => 200]);
        $id = $this->transaksiPending(2); // subtotal 60000

        // redeem diskon 10% -> 6.000 kena plafon 5.000, total 55.000,
        // poin 200-100 = 100
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();

        // lunas -> earning dari total AKHIR: intdiv(55000,1000) = 55
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.point_earned', 55);

        $this->assertSame(100 + 55, $this->loyalty->fresh()->poin);
    }

    public function test_loyalty_publik_bentuk_baru_dan_toleran_format_nomor(): void
    {
        $this->loyalty->update(['poin' => 123]);

        // tanpa auth, dengan format nomor berbeda-beda
        foreach (['6281234567890', '081234567890', '0812-3456-7890'] as $format) {
            $this->getJson('/api/loyalty/'.urlencode($format))
                ->assertOk()
                ->assertExactJson([
                    'nomor_wa' => '6281234567890',
                    'nama' => 'Budi',
                    'poin' => 123,
                    // baris lama tanpa kedaluwarsa -> null, poin tidak hangus
                    'poin_kedaluwarsa_pada' => null,
                ]);
        }

        $this->getJson('/api/loyalty/089999999999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'pelanggan_tidak_ditemukan');
    }
}
