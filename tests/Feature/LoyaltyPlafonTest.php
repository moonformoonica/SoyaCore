<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\User;
use App\Support\LoyaltyRedemptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Konsep redeem poin v2: plafon potongan, bonus pendaftaran, dan kedaluwarsa
 * poin.
 *
 * Yang dijaga di sini bukan "endpointnya jalan", tapi bahwa satu transaksi
 * besar tidak bisa lagi menghapus profit setahun seorang pelanggan, cacat
 * yang membuat perubahan ini dibuat: diskon 50% pada pesanan Rp 475.000
 * dulu memberi potongan Rp 237.500.
 */
class LoyaltyPlafonTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Loyalty $loyalty;

    /** Rp 95.000, dipakai menyusun pesanan besar. */
    private Menu $botol;

    /** Rp 20.000, dipakai menyusun subtotal bulat kecil/menengah. */
    private Menu $gelas;

    protected function setUp(): void
    {
        parent::setUp();

        $signature = Kategori::create(['nama' => 'Soya Signature']);
        $coffee = Kategori::create(['nama' => 'Soya Coffee']);
        $tropical = Kategori::create(['nama' => 'Soya Tropical']);

        // Menu reward, harga di sini yang dipakai menghitung Rp/poin efektif
        Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 17000]);
        Menu::create(['kategori_id' => $coffee->id, 'nama' => 'Coffee Kopi', 'ukuran' => 'Reguler', 'harga' => 21000]);
        Menu::create(['kategori_id' => $tropical->id, 'nama' => 'Honey Lemon', 'ukuran' => 'Reguler', 'harga' => 20000]);
        Menu::create(['kategori_id' => $tropical->id, 'nama' => 'Mango Monggo', 'ukuran' => 'Reguler', 'harga' => 20000]);

        $this->botol = Menu::create([
            'kategori_id' => $signature->id, 'nama' => 'Dark Choco', 'ukuran' => '1000ml', 'harga' => 95000,
        ]);
        $this->gelas = Menu::create([
            'kategori_id' => $signature->id, 'nama' => 'Taro Thanos', 'ukuran' => 'Large', 'harga' => 20000,
        ]);

        $this->customer = Customer::create(['nama' => 'Budi', 'no_wa' => '6281234567890']);
        $this->loyalty = Loyalty::create(['customer_id' => $this->customer->id, 'poin' => 1000]);

        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
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
     * Transaksi pending atas nama Budi dengan satu jenis item.
     */
    private function transaksiPending(Menu $menu, int $qty): int
    {
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $menu->id, 'qty' => $qty])
            ->assertOk();

        return $id;
    }

    private function redeem(int $id, string $kode)
    {
        return $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => $kode]);
    }

    // ---------------------------------------------------------------
    // Plafon potongan
    // ---------------------------------------------------------------

    public function test_plafon_tidak_mengikat_tersimpan_sebagai_persen(): void
    {
        $id = $this->transaksiPending($this->gelas, 2); // subtotal 40.000

        $respon = $this->redeem($id, 'diskon_20')->assertOk();

        // 20% dari 40.000 = 8.000, di bawah plafon 10.000
        $this->assertSame(8000, $respon->json('data.diskon_nilai'));
        $this->assertSame(20, $respon->json('data.diskon_persen'));
        $this->assertSame(32000, $respon->json('data.total'));
    }

    public function test_plafon_mengikat_tersimpan_sebagai_nominal(): void
    {
        $id = $this->transaksiPending($this->gelas, 5); // subtotal 100.000

        $respon = $this->redeem($id, 'diskon_20')->assertOk();

        // 20% dari 100.000 = 20.000, dipotong plafon jadi 10.000. Begitu
        // plafon mengikat, diskonnya bukan persen lagi, persen dinolkan
        // supaya penambahan item berikutnya tidak menghitung ulang 20%.
        $this->assertSame(10000, $respon->json('data.diskon_nilai'));
        $this->assertSame(0, $respon->json('data.diskon_persen'));
        $this->assertSame(90000, $respon->json('data.total'));
    }

    /**
     * Regression test cacat utama: 5 botol 1000ml Dark Choco = Rp 475.000,
     * yang dulu memberi potongan Rp 237.500 (67,9% dari omzet yang dipakai
     * mengumpulkan poinnya).
     */
    public function test_pesanan_besar_berhenti_di_plafon_bukan_setengah_tagihan(): void
    {
        $id = $this->transaksiPending($this->botol, 5); // subtotal 475.000

        $respon = $this->redeem($id, 'diskon_50')->assertOk();

        $this->assertSame(25000, $respon->json('data.diskon_nilai'));
        $this->assertSame(450000, $respon->json('data.total'));
    }

    /**
     * Celah paling halus: diskon disimpan sebagai persen karena saat redeem
     * masih di bawah plafon, lalu kasir menambah item. recalculateTotals()
     * menurunkan ulang persennya, tanpa plafon yang ikut tersimpan di
     * transaksi, potongannya ikut membengkak.
     */
    public function test_plafon_tetap_berlaku_saat_item_ditambah_setelah_redeem(): void
    {
        $id = $this->transaksiPending($this->gelas, 2); // subtotal 40.000

        // 50% dari 40.000 = 20.000, masih di bawah plafon -> tersimpan persen
        $respon = $this->redeem($id, 'diskon_50')->assertOk();
        $this->assertSame(20000, $respon->json('data.diskon_nilai'));
        $this->assertSame(50, $respon->json('data.diskon_persen'));

        // kasir menambah 6 botol 1000ml -> subtotal 610.000
        $respon = $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->botol->id, 'qty' => 6])
            ->assertOk();

        $this->assertSame(610000, $respon->json('data.subtotal'));
        $this->assertSame(25000, $respon->json('data.diskon_nilai')); // bukan 305.000
        $this->assertSame(585000, $respon->json('data.total'));
    }

    public function test_diskon_manual_kasir_tetap_tanpa_plafon(): void
    {
        $id = $this->transaksiPending($this->botol, 5); // subtotal 475.000

        // Plafon hanya milik diskon hasil redeem poin. Diskon manual tetap
        // wewenang penuh kasir/manager.
        $respon = $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 50])
            ->assertOk();

        $this->assertSame(237500, $respon->json('data.diskon_nilai'));
    }

    public function test_min_subtotal_menolak_redeem_di_pesanan_kecil(): void
    {
        $id = $this->transaksiPending($this->gelas, 1); // subtotal 20.000

        $this->redeem($id, 'diskon_20')
            ->assertStatus(422)
            ->assertJsonPath('error', 'minimal_pembelian_kurang');

        $this->assertSame(1000, $this->loyalty->fresh()->poin); // tidak terpotong
    }

    public function test_diskon_30_tersedia_dan_bisa_diredeem(): void
    {
        $this->assertContains('diskon_30', LoyaltyRedemptionCatalog::kodeTersedia());

        $id = $this->transaksiPending($this->gelas, 5); // subtotal 100.000

        $respon = $this->redeem($id, 'diskon_30')->assertOk();

        // 30% dari 100.000 = 30.000, dipotong plafon jadi 15.000
        $this->assertSame(15000, $respon->json('data.diskon_nilai'));
        $this->assertSame(300, $respon->json('data.poin_ditukar'));
        $this->assertSame(700, $this->loyalty->fresh()->poin);
    }

    public function test_poin_baru_gratis_minuman_dipakai(): void
    {
        $this->loyalty->update(['poin' => 349]);
        $id = $this->transaksiPending($this->gelas, 2);

        $respon = $this->redeem($id, 'gratis_original')
            ->assertStatus(422)
            ->assertJsonPath('error', 'poin_kurang');
        $this->assertStringContainsString('butuh 350', $respon->json('message'));

        $this->loyalty->refresh()->update(['poin' => 350]);
        $id = $this->transaksiPending($this->gelas, 2);

        $this->redeem($id, 'gratis_original')
            ->assertOk()
            ->assertJsonPath('data.poin_ditukar', 350);

        $this->assertSame(0, $this->loyalty->fresh()->poin);
    }

    // ---------------------------------------------------------------
    // Diskon manual terkunci setelah redeem
    // ---------------------------------------------------------------

    public function test_diskon_manual_ditolak_setelah_redeem(): void
    {
        $id = $this->transaksiPending($this->gelas, 5); // subtotal 100.000
        $this->redeem($id, 'diskon_20')->assertOk();

        $respon = $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 50])
            ->assertStatus(409)
            ->assertJsonPath('error', 'diskon_terkunci_redeem');

        $this->assertStringContainsString('diskon_20', $respon->json('message'));

        // diskon reward tidak tertimpa
        $this->assertSame(10000, $this->getJson("/api/transaksi/{$id}")->json('data.diskon_nilai'));
    }

    // ---------------------------------------------------------------
    // Bonus pendaftaran
    // ---------------------------------------------------------------

    public function test_customer_baru_dapat_bonus_pendaftaran_sekali_saja(): void
    {
        $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Siti', 'no_wa' => '0857-1111-2222'],
        ])->assertCreated();

        $siti = Customer::where('no_wa', '6285711112222')->firstOrFail();
        $this->assertSame(Loyalty::POIN_BONUS_DAFTAR, $siti->loyalty->poin);

        // kunjungan berikutnya: customer sudah ada -> tidak ada bonus kedua
        $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Siti', 'no_wa' => '0857-1111-2222'],
        ])->assertCreated();

        $this->assertSame(Loyalty::POIN_BONUS_DAFTAR, $siti->loyalty()->first()->poin);
        $this->assertSame(1, Customer::where('no_wa', '6285711112222')->count());
    }

    public function test_bonus_tidak_menyentuh_saldo_customer_lama(): void
    {
        // Budi sudah ada sejak setUp dengan 1.000 poin
        $this->transaksiPending($this->gelas, 1);

        $this->assertSame(1000, $this->loyalty->fresh()->poin);
    }

    // ---------------------------------------------------------------
    // Kedaluwarsa poin
    // ---------------------------------------------------------------

    public function test_poin_hangus_setelah_lewat_masa_berlaku(): void
    {
        $this->loyalty->update(['poin_kedaluwarsa_pada' => now()->subDay()]);

        $this->getJson('/api/loyalty/6281234567890')
            ->assertOk()
            ->assertJsonPath('poin', 0)
            ->assertJsonPath('poin_kedaluwarsa_pada', null);

        // benar-benar dinolkan di database, bukan cuma disembunyikan
        $this->assertSame(0, $this->loyalty->fresh()->poin);

        // dan redeem ikut ditolak karena saldonya memang sudah tidak ada
        $id = $this->transaksiPending($this->gelas, 5);
        $this->redeem($id, 'diskon_20')
            ->assertStatus(422)
            ->assertJsonPath('error', 'poin_kurang');
    }

    public function test_poin_belum_hangus_sebelum_jatuh_tempo(): void
    {
        $besok = now()->addDay();
        $this->loyalty->update(['poin_kedaluwarsa_pada' => $besok]);

        $this->getJson('/api/loyalty/6281234567890')
            ->assertOk()
            ->assertJsonPath('poin', 1000)
            ->assertJsonPath('poin_kedaluwarsa_pada', $besok->toIso8601String());
    }

    public function test_transaksi_baru_memperpanjang_masa_berlaku(): void
    {
        $this->loyalty->update(['poin_kedaluwarsa_pada' => now()->addDay()]);

        $id = $this->transaksiPending($this->gelas, 1); // total 20.000 -> 20 poin
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();

        $segar = $this->loyalty->fresh();

        $this->assertSame(1020, $segar->poin);
        $this->assertSame(
            now()->addMonths(Loyalty::BULAN_KEDALUWARSA)->toDateString(),
            $segar->poin_kedaluwarsa_pada->toDateString(),
        );
    }

    public function test_poin_yang_sudah_hangus_tidak_hidup_lagi_saat_transaksi_baru(): void
    {
        $this->loyalty->update(['poin_kedaluwarsa_pada' => now()->subDay()]);

        $id = $this->transaksiPending($this->gelas, 1); // total 20.000 -> 20 poin
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();

        // saldo lama hangus dulu, baru poin transaksi ini ditambahkan
        $this->assertSame(20, $this->loyalty->fresh()->poin);
    }

    public function test_baris_lama_tanpa_tanggal_kedaluwarsa_tidak_ikut_hangus(): void
    {
        // baris seperti ini yang ada di database sebelum fitur kedaluwarsa
        $this->assertNull($this->loyalty->poin_kedaluwarsa_pada);

        $this->getJson('/api/loyalty/6281234567890')
            ->assertOk()
            ->assertJsonPath('poin', 1000)
            ->assertJsonPath('poin_kedaluwarsa_pada', null);

        $this->assertSame(1000, $this->loyalty->fresh()->poin);
    }

    // ---------------------------------------------------------------
    // Pengaturan plafon & minimal belanja
    // ---------------------------------------------------------------

    public function test_manager_ubah_plafon_dan_langsung_dipakai_redeem(): void
    {
        $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_20', ['maks_potongan' => 3000])
            ->assertOk()
            ->assertJsonPath('data.maks_potongan', 3000)
            ->assertJsonPath('data.poin', 200) // poin tidak ikut ter-reset
            // Rp 3.000 / 200 poin = 15, jauh di bawah Rp 50, reward jadi pelit
            ->assertJsonPath('data.rupiah_per_poin_efektif', 15);

        $this->actingAsKasir();
        $id = $this->transaksiPending($this->gelas, 5); // subtotal 100.000

        $this->redeem($id, 'diskon_20')
            ->assertOk()
            ->assertJsonPath('data.diskon_nilai', 3000);
    }

    public function test_manager_ubah_minimal_belanja_dan_langsung_dipakai_redeem(): void
    {
        $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_20', ['min_subtotal' => 90000])
            ->assertOk()
            ->assertJsonPath('data.min_subtotal', 90000);

        $this->actingAsKasir();

        // 40.000 lolos aturan bawaan (25.000) tapi tidak lolos aturan manager
        $id = $this->transaksiPending($this->gelas, 2);
        $this->redeem($id, 'diskon_20')
            ->assertStatus(422)
            ->assertJsonPath('error', 'minimal_pembelian_kurang');

        $id = $this->transaksiPending($this->gelas, 5); // 100.000
        $this->redeem($id, 'diskon_20')->assertOk();
    }

    public function test_plafon_ditolak_pada_reward_gratis_menu(): void
    {
        $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/gratis_original', ['maks_potongan' => 5000])
            ->assertStatus(422)
            ->assertJsonPath('error', 'maks_potongan_tidak_berlaku');

        $this->assertDatabaseCount('katalog_redeem', 0);
    }

    public function test_body_hanya_berisi_plafon_atau_minimal_belanja_diterima(): void
    {
        $this->actingAsManager();

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['maks_potongan' => 6000])
            ->assertOk();
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['min_subtotal' => 30000])
            ->assertOk()
            ->assertJsonPath('data.maks_potongan', 6000); // override sebelumnya bertahan

        // body kosong tetap ditolak
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');

        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['min_subtotal' => -1])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    /**
     * Cek silang seluruh katalog. Rp/poin di luar rentang ini berarti ada
     * reward yang mendominasi (kemurahan) atau mati (kemahalan), dua-duanya
     * bikin katalog jadi pajangan.
     */
    public function test_seluruh_katalog_berada_di_rentang_rupiah_per_poin_yang_sehat(): void
    {
        $data = $this->getJson('/api/pengaturan/loyalty/katalog')->assertOk()->json('data');

        $this->assertCount(8, $data);

        foreach ($data as $item) {
            $efektif = $item['rupiah_per_poin_efektif'];

            $this->assertNotNull($efektif, "{$item['kode']} tidak punya nilai reward");
            $this->assertGreaterThanOrEqual(46.7, $efektif, "{$item['kode']} terlalu mahal");
            $this->assertLessThanOrEqual(50.0, $efektif, "{$item['kode']} terlalu murah");
        }
    }
}
