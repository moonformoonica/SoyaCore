<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\PengaturanLoyalty;
use App\Models\User;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pengaturan Loyalty (Settings manager): rate poin + biaya poin katalog.
 *
 * Fokus test ini bukan cuma "endpoint-nya jalan", tapi bahwa setting yang
 * disimpan BENAR-BENAR nyampe ke perhitungan poin, persis kekhawatiran yang
 * bikin fitur ini dibuat (UI nampilin 10.000 tapi kode masih bagi 1.000).
 */
class LoyaltyPengaturanTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Loyalty $loyalty;

    private Menu $menuBiasa;

    protected function setUp(): void
    {
        parent::setUp();

        $signature = Kategori::create(['nama' => 'Soya Signature']);
        Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 17000]);

        $this->menuBiasa = Menu::create([
            'kategori_id' => $signature->id, 'nama' => 'Taro Thanos', 'ukuran' => 'Large', 'harga' => 25000,
        ]);

        $this->customer = Customer::create(['nama' => 'Budi', 'no_wa' => '6281234567890']);
        $this->loyalty = Loyalty::create(['customer_id' => $this->customer->id, 'poin' => 0]);
    }

    private function actingAsManager(): User
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        return $manager;
    }

    private function actingAsKasir(): User
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        Sanctum::actingAs($kasir);

        return $kasir;
    }

    /**
     * Transaksi pending ber-customer, 2x Taro Thanos = subtotal Rp 50.000.
     */
    private function transaksiPending(int $qty = 2): int
    {
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->menuBiasa->id, 'qty' => $qty])
            ->assertOk();

        return $id;
    }

    // ---------------------------------------------------------------
    // Rate poin
    // ---------------------------------------------------------------

    public function test_default_rate_1000_saat_belum_pernah_disetel(): void
    {
        $this->actingAsKasir();

        $this->getJson('/api/pengaturan/loyalty')
            ->assertOk()
            ->assertJsonPath('data.rupiah_per_poin', 1000)
            ->assertJsonPath('data.rupiah_per_poin_default', 1000)
            ->assertJsonPath('data.contoh.belanja', 50000)
            ->assertJsonPath('data.contoh.poin_didapat', 50)
            ->assertJsonPath('data.diperbarui_pada', null)
            ->assertJsonPath('data.diperbarui_oleh', null);

        // tabel pengaturan memang masih kosong, default datang dari kode
        $this->assertDatabaseCount('pengaturan_loyalty', 0);
    }

    public function test_manager_ubah_rate_dan_contoh_ikut_berubah(): void
    {
        $manager = $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 10000])
            ->assertOk()
            ->assertJsonPath('data.rupiah_per_poin', 10000)
            ->assertJsonPath('data.contoh.poin_didapat', 5) // 50.000 / 10.000
            ->assertJsonPath('data.diperbarui_oleh', $manager->nama);

        $this->assertSame(10000, PengaturanLoyalty::rupiahPerPoin());
    }

    public function test_hanya_satu_baris_pengaturan_walau_diubah_berkali_kali(): void
    {
        $this->actingAsManager();

        foreach ([5000, 10000, 2500] as $rate) {
            $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => $rate])->assertOk();
        }

        $this->assertDatabaseCount('pengaturan_loyalty', 1);
        $this->assertSame(2500, PengaturanLoyalty::rupiahPerPoin());
    }

    /**
     * Inti dari fitur ini: rate yang disimpan harus dipakai earning.
     * Persis skenario verifikasi yang diminta, set 10.000, bayar 50.000,
     * poin naik 5 (bukan 50).
     */
    public function test_rate_baru_dipakai_saat_tandai_lunas(): void
    {
        $this->actingAsManager();
        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 10000])->assertOk();

        $this->actingAsKasir();
        $id = $this->transaksiPending(2); // total Rp 50.000

        $this->postJson("/api/transaksi/{$id}/tandai-lunas", ['metode_bayar' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.total', 50000)
            ->assertJsonPath('data.point_earned', 5)
            ->assertJsonPath('data.rupiah_per_poin', 10000);

        $this->assertSame(5, $this->loyalty->fresh()->poin);
    }

    public function test_perubahan_rate_tidak_retroaktif(): void
    {
        // transaksi pertama di rate default 1.000 -> 50 poin
        $this->actingAsKasir();
        $id = $this->transaksiPending(2);
        $this->postJson("/api/transaksi/{$id}/tandai-lunas", ['metode_bayar' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.point_earned', 50);

        // manager menaikkan rate
        $this->actingAsManager();
        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 10000])->assertOk();

        // saldo lama tetap, dan snapshot rate transaksi lama tidak ikut berubah
        $this->assertSame(50, $this->loyalty->fresh()->poin);
        $this->getJson("/api/transaksi/{$id}")
            ->assertOk()
            ->assertJsonPath('data.point_earned', 50)
            ->assertJsonPath('data.rupiah_per_poin', 1000);

        // transaksi berikutnya baru pakai rate baru
        $this->actingAsKasir();
        $id2 = $this->transaksiPending(2);
        $this->postJson("/api/transaksi/{$id2}/tandai-lunas", ['metode_bayar' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.point_earned', 5);

        $this->assertSame(55, $this->loyalty->fresh()->poin);
    }

    public function test_rate_ditolak_kalau_di_luar_batas_atau_bukan_angka(): void
    {
        $this->actingAsManager();

        foreach ([0, 99, 1_000_001, -500] as $tidakValid) {
            $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => $tidakValid])
                ->assertStatus(422)
                ->assertJsonPath('error', 'validasi_gagal');
        }

        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 'sepuluh ribu'])
            ->assertStatus(422);

        $this->patchJson('/api/pengaturan/loyalty', [])->assertStatus(422);

        // tidak ada satu pun yang tersimpan
        $this->assertDatabaseCount('pengaturan_loyalty', 0);
        $this->assertSame(1000, PengaturanLoyalty::rupiahPerPoin());
    }

    public function test_kasir_boleh_baca_tapi_tidak_boleh_ubah(): void
    {
        $this->actingAsKasir();

        $this->getJson('/api/pengaturan/loyalty')->assertOk();
        $this->getJson('/api/pengaturan/loyalty/katalog')->assertOk();

        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 10000])
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');

        $this->patchJson('/api/pengaturan/loyalty/katalog/gratis_original', ['poin' => 200])
            ->assertStatus(403);

        $this->assertSame(1000, PengaturanLoyalty::rupiahPerPoin());
    }

    public function test_pengaturan_butuh_login(): void
    {
        $this->getJson('/api/pengaturan/loyalty')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');

        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 10000])
            ->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Katalog redeem
    // ---------------------------------------------------------------

    public function test_katalog_menampilkan_nilai_bawaan_dan_setara_belanja(): void
    {
        $this->actingAsKasir();

        $respon = $this->getJson('/api/pengaturan/loyalty/katalog')->assertOk();

        $this->assertCount(8, $respon->json('data'));
        $this->assertSame(1000, $respon->json('meta.rupiah_per_poin'));

        $gratisOriginal = collect($respon->json('data'))->firstWhere('kode', 'gratis_original');

        $this->assertSame('Gratis Original', $gratisOriginal['label']);
        $this->assertSame('gratis_menu', $gratisOriginal['tipe']);
        $this->assertSame(350, $gratisOriginal['poin']);
        $this->assertSame(350, $gratisOriginal['poin_default']);
        $this->assertTrue($gratisOriginal['is_active']);
        $this->assertSame('Original', $gratisOriginal['menu_gratis']);
        $this->assertSame(350 * 1000, $gratisOriginal['setara_belanja']);
        $this->assertNull($gratisOriginal['diubah_pada']);
        // reward gratis tidak mengenal plafon potongan
        $this->assertNull($gratisOriginal['maks_potongan']);
        // Rp 17.000 / 350 poin = 48,6, di bawah acuan Rp 50/poin
        $this->assertSame(48.6, $gratisOriginal['rupiah_per_poin_efektif']);

        // belum ada yang diedit -> tabel override masih kosong
        $this->assertDatabaseCount('katalog_redeem', 0);
    }

    public function test_manager_ubah_poin_reward_dan_langsung_dipakai_redeem(): void
    {
        $manager = $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/gratis_original', ['poin' => 200])
            ->assertOk()
            ->assertJsonPath('data.kode', 'gratis_original')
            ->assertJsonPath('data.poin', 200)
            ->assertJsonPath('data.poin_default', 350) // bawaan tetap terlihat sebagai pembanding
            ->assertJsonPath('data.setara_belanja', 200000)
            // Rp 17.000 / 200 poin = 85, jauh di atas Rp 50, tanda reward
            // kemurahan yang memang harus terlihat manager
            ->assertJsonPath('data.rupiah_per_poin_efektif', 85);

        $this->assertDatabaseHas('katalog_redeem', [
            'kode' => 'gratis_original',
            'poin' => 200,
            'updated_by' => $manager->id,
        ]);

        // 175 poin: kurang untuk harga baru (200)
        $this->loyalty->update(['poin' => 175]);

        $this->actingAsKasir();
        $id = $this->transaksiPending(2);

        $respon = $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'poin_kurang');

        // pesan error menyebut harga BARU, bukan yang di kode
        $this->assertStringContainsString('kurang 25', $respon->json('message'));
        $this->assertStringContainsString('butuh 200', $respon->json('message'));

        $this->assertSame(175, $this->loyalty->fresh()->poin);
    }

    public function test_reward_dinonaktifkan_ditolak_dengan_kode_error_sendiri(): void
    {
        $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.poin', 100); // poin tidak ikut berubah

        $this->loyalty->update(['poin' => 400]);

        $this->actingAsKasir();
        $id = $this->transaksiPending(2);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'kode_redeem_nonaktif');

        $this->assertSame(400, $this->loyalty->fresh()->poin); // tidak terpotong
        $this->assertSame(50000, $this->getJson("/api/transaksi/{$id}")->json('data.total'));
    }

    public function test_aktifkan_kembali_reward_yang_dinonaktifkan(): void
    {
        $this->actingAsManager();
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['is_active' => false])->assertOk();
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->loyalty->update(['poin' => 400]);

        $this->actingAsKasir();
        $id = $this->transaksiPending(2); // subtotal 50.000

        // 10% dari 50.000 = 5.000, tepat di plafon (belum mengikat)
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])
            ->assertOk()
            ->assertJsonPath('data.diskon_persen', 10)
            ->assertJsonPath('data.diskon_nilai', 5000)
            ->assertJsonPath('data.total', 45000);

        $this->assertSame(300, $this->loyalty->fresh()->poin);
    }

    public function test_patch_sebagian_tidak_menghapus_field_lain(): void
    {
        $this->actingAsManager();

        // set dua-duanya, lalu kirim satu per satu
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_20', ['poin' => 300, 'is_active' => false])
            ->assertOk();

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_20', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.poin', 300)      // poin override lama dipertahankan
            ->assertJsonPath('data.is_active', true);

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_20', ['poin' => 275])
            ->assertOk()
            ->assertJsonPath('data.poin', 275)
            ->assertJsonPath('data.is_active', true); // status tidak ikut ter-reset

        $this->assertDatabaseCount('katalog_redeem', 1);
    }

    public function test_katalog_ikut_rate_saat_menghitung_setara_belanja(): void
    {
        $this->actingAsManager();
        $this->patchJson('/api/pengaturan/loyalty', ['rupiah_per_poin' => 10000])->assertOk();

        $respon = $this->getJson('/api/pengaturan/loyalty/katalog')->assertOk();

        $this->assertSame(10000, $respon->json('meta.rupiah_per_poin'));

        // 350 poin di rate 10.000 = belanja Rp 3,5 juta, angka inilah yang
        // memberi tahu manager bahwa poin katalog perlu ikut diturunkan
        $gratisOriginal = collect($respon->json('data'))->firstWhere('kode', 'gratis_original');
        $this->assertSame(3_500_000, $gratisOriginal['setara_belanja']);
    }

    public function test_kode_katalog_tidak_dikenal_404(): void
    {
        $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_99', ['poin' => 100])
            ->assertStatus(404)
            ->assertJsonPath('error', 'kode_redeem_invalid');

        $this->assertDatabaseCount('katalog_redeem', 0);
    }

    public function test_poin_katalog_ditolak_kalau_di_luar_batas_atau_body_kosong(): void
    {
        $this->actingAsManager();

        foreach ([0, -10, 100_001] as $tidakValid) {
            $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['poin' => $tidakValid])
                ->assertStatus(422)
                ->assertJsonPath('error', 'validasi_gagal');
        }

        // body kosong bukan no-op diam-diam, ditolak eksplisit
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');

        // tidak ada override yang tersimpan -> katalog masih di nilai bawaan
        $this->assertDatabaseCount('katalog_redeem', 0);
        $this->assertSame(100, LoyaltyRedemptionCatalog::find('diskon_10')['poin']);
    }
}
