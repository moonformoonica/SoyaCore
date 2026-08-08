<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\KatalogRedeem;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Models\User;
use App\Support\LoyaltyRedemptionCatalog;
use App\Support\WaktuToko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menambah JENIS reward baru dari halaman Loyalty.
 *
 * Sebelum ini delapan jenis di LoyaltyRedemptionCatalog::defaults() adalah
 * semuanya yang pernah bisa ada: manager cuma boleh mengubah poin dan mematikan
 * reward, dan satu reward promo baru berarti menunggu deploy.
 *
 * Yang dijaga di sini bukan cuma "endpoint-nya jalan", tapi bahwa reward buatan
 * manager BENAR-BENAR bisa ditukarkan pelanggan. Reward yang tersimpan rapi di
 * katalog tapi gagal saat ditukarkan adalah kegagalan yang muncul di depan
 * kasir, bukan di layar manager.
 */
class LoyaltyRewardKustomTest extends TestCase
{
    use RefreshDatabase;

    private Menu $tropical;

    private Customer $customer;

    private Loyalty $loyalty;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Tropical']);
        $this->tropical = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Berry Blast', 'ukuran' => 'Reguler', 'harga' => 22000,
        ]);

        $this->customer = Customer::create(['nama' => 'Budi', 'no_wa' => '6281234567890']);
        $this->loyalty = Loyalty::create(['customer_id' => $this->customer->id, 'poin' => 5000]);
    }

    private function manager(): User
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        return $manager;
    }

    private function kasir(): User
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        Sanctum::actingAs($kasir);

        return $kasir;
    }

    /**
     * Reward gratis menu yang menunjuk satu-satunya menu di setUp, jadi
     * redeem-nya benar-benar bisa jalan. Reward bawaan seperti `gratis_original`
     * tidak dipakai di sini: menunya tidak ada di skenario ini, dan redeem-nya
     * akan gagal karena datanya, bukan karena hal yang sedang diuji.
     *
     * Mengembalikan kodenya supaya pemanggil tidak perlu tahu cara kode itu
     * dibentuk dari label.
     */
    private function rewardSiapPakai(): string
    {
        $pemanggilSemula = auth()->user();

        $this->manager();
        $kode = $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Berry Blast',
            'tipe' => 'gratis_menu',
            'poin' => 440,
            'menu_id' => $this->tropical->id,
        ])->assertCreated()->json('data.kode');

        if ($pemanggilSemula !== null) {
            Sanctum::actingAs($pemanggilSemula);
        }

        return $kode;
    }

    // =====================================================================
    // Membuat reward
    // =====================================================================

    public function test_manager_bisa_menambah_voucher_diskon_baru(): void
    {
        $this->manager();

        $respon = $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Diskon 15% Ramadan',
            'tipe' => 'diskon',
            'poin' => 150,
            'persen' => 15,
            'maks_potongan' => 7500,
            'min_subtotal' => 30000,
        ])->assertCreated();

        // Kode diturunkan dari labelnya, bukan diketik manager: kode ikut
        // tersimpan di `transaksi.kode_redeem` dan terbaca di riwayat.
        $respon->assertJsonPath('data.kode', 'diskon_15_ramadan')
            ->assertJsonPath('data.label', 'Diskon 15% Ramadan')
            ->assertJsonPath('data.tipe', 'diskon')
            ->assertJsonPath('data.bawaan', false)
            ->assertJsonPath('data.poin', 150)
            ->assertJsonPath('data.maks_potongan', 7500);

        // Dan langsung muncul di katalog yang dibaca halaman Loyalty.
        $kode = array_column($this->getJson('/api/pengaturan/loyalty/katalog')->json('data'), 'kode');
        $this->assertContains('diskon_15_ramadan', $kode);
    }

    public function test_manager_bisa_menambah_reward_gratis_menu_baru(): void
    {
        $this->manager();

        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Berry Blast',
            'tipe' => 'gratis_menu',
            'poin' => 440,
            'menu_id' => $this->tropical->id,
        ])->assertCreated()
            ->assertJsonPath('data.kode', 'gratis_berry_blast')
            ->assertJsonPath('data.menu_gratis', 'Berry Blast');

        // Nama menu dan kategorinya disalin dari baris menu, itu yang dipakai
        // LoyaltyService mencari hadiahnya saat redeem.
        $item = LoyaltyRedemptionCatalog::find('gratis_berry_blast');
        $this->assertSame('Berry Blast', $item['menu']);
        $this->assertSame('Soya Tropical', $item['kategori']);

        // "Reguler" dan "Regular" dianggap ejaan yang sama, mengikuti katalog
        // bawaan; menu yang mengeja versi satunya tetap ketemu.
        $this->assertSame(['Reguler', 'Regular'], $item['ukuran']);
    }

    /**
     * Inti fiturnya: reward buatan manager harus benar-benar bisa ditukarkan,
     * bukan sekadar tersimpan.
     */
    public function test_reward_kustom_bisa_ditukarkan_pelanggan(): void
    {
        $this->manager();
        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Berry Blast',
            'tipe' => 'gratis_menu',
            'poin' => 440,
            'menu_id' => $this->tropical->id,
        ])->assertCreated();

        $kasir = $this->kasir();

        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_berry_blast'])
            ->assertOk();

        $transaksi = Transaksi::with('detailTransaksi')->find($id);

        $this->assertSame('gratis_berry_blast', $transaksi->kode_redeem);
        $this->assertSame(440, $transaksi->poin_ditukar);

        // Item hadiahnya menunjuk menu yang benar dan berharga nol.
        $reward = $transaksi->detailTransaksi->firstWhere('is_reward', true);
        $this->assertNotNull($reward, 'Item reward harus ikut masuk ke transaksi.');
        $this->assertSame($this->tropical->id, $reward->menu_id);

        $this->assertSame(5000 - 440, (int) $this->loyalty->fresh()->poin);
        $this->assertSame($kasir->id, $transaksi->user_id);
    }

    public function test_voucher_diskon_kustom_memakai_persen_dan_plafonnya_sendiri(): void
    {
        $this->manager();
        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Diskon 15% Ramadan',
            'tipe' => 'diskon',
            'poin' => 150,
            'persen' => 15,
            'maks_potongan' => 7500,
            'min_subtotal' => 0,
        ])->assertCreated();

        $this->kasir();

        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');

        // 4 × Rp 22.000 = Rp 88.000. 15% = Rp 13.200, tapi plafonnya Rp 7.500.
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->tropical->id, 'qty' => 4])
            ->assertOk();
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_15_ramadan'])
            ->assertOk();

        $transaksi = Transaksi::find($id);
        $this->assertSame(88000 - 7500, (int) $transaksi->total);
    }

    // =====================================================================
    // Validasi
    // =====================================================================

    /**
     * Error validasi di API ini dibungkus `error` + `details`, bukan `errors`
     * bawaan Laravel (lihat bootstrap/app.php), jadi kuncinya dicek di `details`.
     */
    public function test_voucher_diskon_wajib_punya_persen_dan_plafon(): void
    {
        $this->manager();

        $respon = $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Diskon Tanpa Pagar', 'tipe' => 'diskon', 'poin' => 100,
        ])->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');

        // Diskon persen tanpa plafon berarti satu pesanan besar bisa memotong
        // berapa pun, dan itu risiko yang tidak pernah dipilih siapa-siapa
        // secara sadar.
        $this->assertArrayHasKey('persen', $respon->json('details'));
        $this->assertArrayHasKey('maks_potongan', $respon->json('details'));
    }

    public function test_gratis_menu_wajib_menunjuk_menu_yang_ada(): void
    {
        $this->manager();

        $tanpaMenu = $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Entah Apa', 'tipe' => 'gratis_menu', 'poin' => 400,
        ])->assertStatus(422);
        $this->assertArrayHasKey('menu_id', $tanpaMenu->json('details'));

        $menuNgawur = $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Entah Apa', 'tipe' => 'gratis_menu', 'poin' => 400, 'menu_id' => 99999,
        ])->assertStatus(422);
        $this->assertArrayHasKey('menu_id', $menuNgawur->json('details'));
    }

    /**
     * Menu NONAKTIF tidak boleh jadi hadiah. `LoyaltyService::cariMenuGratis()`
     * mensyaratkan `is_active = true`, jadi reward yang menunjuk menu nonaktif
     * tersimpan rapi di katalog lalu gagal justru saat pelanggan menukarkannya
     * di depan kasir.
     */
    public function test_menu_nonaktif_ditolak_jadi_hadiah(): void
    {
        $this->tropical->update(['is_active' => false]);
        $this->manager();

        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Menu Mati',
            'tipe' => 'gratis_menu',
            'poin' => 400,
            'menu_id' => $this->tropical->id,
        ])->assertStatus(422)->assertJsonPath('error', 'menu_nonaktif');

        $this->assertNull(LoyaltyRedemptionCatalog::find('gratis_menu_mati'));
    }

    /**
     * Bentuk response `/api/menu-internal` yang dibaca dropdown menu hadiah di
     * halaman Loyalty. `kategori` berupa STRING nama kategori, bukan objek;
     * salah baca inilah yang sempat membuat dropdown-nya kosong padahal ada
     * puluhan menu.
     */
    public function test_menu_internal_mengirim_kategori_sebagai_string(): void
    {
        $this->manager();

        $item = $this->getJson('/api/menu-internal')->assertOk()->json('data.0');

        $this->assertIsString($item['kategori']);
        $this->assertSame('Soya Tropical', $item['kategori']);
        $this->assertArrayHasKey('is_active', $item);
    }

    public function test_label_sama_menghasilkan_kode_yang_tidak_bentrok(): void
    {
        $this->manager();

        $body = ['label' => 'Promo Kilat', 'tipe' => 'diskon', 'poin' => 100, 'persen' => 10, 'maks_potongan' => 5000];

        $this->postJson('/api/pengaturan/loyalty/katalog', $body)
            ->assertCreated()->assertJsonPath('data.kode', 'promo_kilat');
        $this->postJson('/api/pengaturan/loyalty/katalog', $body)
            ->assertCreated()->assertJsonPath('data.kode', 'promo_kilat_2');
    }

    // =====================================================================
    // Kartu "Reward ditukar bulan ini"
    // =====================================================================

    /**
     * Angka ini dulu disaring di browser dari
     * `GET /api/transaksi?status=lunas&per_page=200`. Tiga hal lolos dari cara
     * itu, dan ketiganya membuat penukaran hilang dari hitungan tanpa gejala
     * apa pun: `status=lunas` membuang `batal_sebagian`, `per_page` memagari
     * 200 baris di daftar yang bercampur ratusan baris impor CSV, dan batas
     * bulannya memakai jam browser alih-alih WIB.
     */
    public function test_reward_ditukar_dihitung_backend_dan_ikut_penukaran_baru(): void
    {
        $this->manager();
        $awal = WaktuToko::sekarang()->startOfMonth()->toDateString();
        $akhir = WaktuToko::sekarang()->endOfMonth()->toDateString();
        $url = "/api/dashboard/loyalty?start={$awal}&end={$akhir}";

        $this->getJson($url)->assertOk()->assertJsonPath('data.reward_ditukar', 0);

        $this->kasir();
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => $this->rewardSiapPakai()])
            ->assertOk();

        $this->manager();
        $this->getJson($url)->assertOk()->assertJsonPath('data.reward_ditukar', 1);
    }

    public function test_reward_ditukar_mengabaikan_transaksi_yang_dibatalkan_penuh(): void
    {
        $this->kasir();
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => $this->rewardSiapPakai()])->assertOk();

        $awal = WaktuToko::sekarang()->startOfMonth()->toDateString();
        $akhir = WaktuToko::sekarang()->endOfMonth()->toDateString();
        $url = "/api/dashboard/loyalty?start={$awal}&end={$akhir}";

        $this->manager();
        $this->getJson($url)->assertJsonPath('data.reward_ditukar', 1);

        // Dibatalkan penuh: poin redeem-nya kembali utuh ke pelanggan, jadi
        // penukarannya tidak pernah jadi dan tidak boleh ikut terhitung.
        Transaksi::find($id)->update(['status' => 'batal']);
        $this->getJson($url)->assertJsonPath('data.reward_ditukar', 0);

        // Batal SEBAGIAN beda: poinnya sudah benar-benar terpotong.
        Transaksi::find($id)->update(['status' => 'batal_sebagian']);
        $this->getJson($url)->assertJsonPath('data.reward_ditukar', 1);
    }

    public function test_reward_ditukar_tidak_bocor_ke_bulan_lain(): void
    {
        $this->kasir();
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => $this->rewardSiapPakai()])->assertOk();

        $this->manager();

        $bulanLalu = WaktuToko::sekarang()->subMonthNoOverflow();
        $this->getJson(
            '/api/dashboard/loyalty?start='.$bulanLalu->copy()->startOfMonth()->toDateString()
                .'&end='.$bulanLalu->copy()->endOfMonth()->toDateString()
        )->assertOk()->assertJsonPath('data.reward_ditukar', 0);
    }

    public function test_kasir_tidak_boleh_menambah_atau_menghapus_reward(): void
    {
        $this->kasir();

        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Diskon Gelap', 'tipe' => 'diskon', 'poin' => 1, 'persen' => 100, 'maks_potongan' => 999999,
        ])->assertStatus(403);

        $this->deleteJson('/api/pengaturan/loyalty/katalog/diskon_10')->assertStatus(403);
    }

    // =====================================================================
    // Menghapus
    // =====================================================================

    public function test_reward_kustom_yang_belum_pernah_dipakai_bisa_dihapus(): void
    {
        $this->manager();
        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Promo Kilat', 'tipe' => 'diskon', 'poin' => 100, 'persen' => 10, 'maks_potongan' => 5000,
        ])->assertCreated();

        $this->deleteJson('/api/pengaturan/loyalty/katalog/promo_kilat')->assertOk();

        $this->assertNull(LoyaltyRedemptionCatalog::find('promo_kilat'));
        $this->assertDatabaseMissing('katalog_redeem', ['kode' => 'promo_kilat']);
    }

    /**
     * `transaksi.kode_redeem` menyimpan kodenya. Menghapus definisinya membuat
     * riwayat penukaran lama kehilangan artinya tanpa error apa pun, jadi
     * ditolak dan diarahkan ke nonaktifkan, aturan yang sama dengan akun kasir
     * yang sudah punya transaksi.
     */
    public function test_reward_yang_pernah_ditukarkan_ditolak_dan_diarahkan_ke_nonaktifkan(): void
    {
        $this->manager();
        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Gratis Berry Blast', 'tipe' => 'gratis_menu', 'poin' => 440, 'menu_id' => $this->tropical->id,
        ])->assertCreated();

        $this->kasir();
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_berry_blast'])->assertOk();

        $this->manager();
        $respon = $this->deleteJson('/api/pengaturan/loyalty/katalog/gratis_berry_blast')
            ->assertStatus(422)
            ->assertJsonPath('error', 'reward_sudah_dipakai');

        $this->assertStringContainsString('Nonaktifkan', $respon->json('message'));
        $this->assertNotNull(LoyaltyRedemptionCatalog::find('gratis_berry_blast'));
    }

    /**
     * Reward bawaan tidak bisa dihapus: logika redeem-nya ada di PHP dan tidak
     * ikut hilang bersama barisnya, jadi "menghapus" cuma akan memunculkannya
     * lagi dengan setelan bawaan pada request berikutnya.
     */
    public function test_reward_bawaan_tidak_bisa_dihapus_hanya_dinonaktifkan(): void
    {
        $this->manager();

        $this->deleteJson('/api/pengaturan/loyalty/katalog/diskon_10')
            ->assertStatus(422)
            ->assertJsonPath('error', 'reward_bawaan');

        $this->assertNotNull(LoyaltyRedemptionCatalog::find('diskon_10'));

        // Yang dimaksud manager hampir selalu ini, dan jalurnya tetap terbuka.
        $this->patchJson('/api/pengaturan/loyalty/katalog/diskon_10', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    // =====================================================================
    // Hidup berdampingan dengan reward bawaan
    // =====================================================================

    public function test_reward_kustom_tidak_mengganggu_katalog_bawaan(): void
    {
        $this->manager();
        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Promo Kilat', 'tipe' => 'diskon', 'poin' => 100, 'persen' => 10, 'maks_potongan' => 5000,
        ])->assertCreated();

        $data = collect($this->getJson('/api/pengaturan/loyalty/katalog')->json('data'))->keyBy('kode');

        // Delapan bawaan + satu kustom, dan yang bawaan tetap ditandai bawaan.
        $this->assertCount(9, $data);
        $this->assertTrue($data['diskon_10']['bawaan']);
        $this->assertFalse($data['promo_kilat']['bawaan']);
        $this->assertSame(100, $data['diskon_10']['poin'], 'Poin reward bawaan tidak boleh ikut berubah.');
    }

    public function test_poin_reward_kustom_tetap_bisa_diubah_lewat_patch(): void
    {
        $this->manager();
        $this->postJson('/api/pengaturan/loyalty/katalog', [
            'label' => 'Promo Kilat', 'tipe' => 'diskon', 'poin' => 100, 'persen' => 10, 'maks_potongan' => 5000,
        ])->assertCreated();

        $this->patchJson('/api/pengaturan/loyalty/katalog/promo_kilat', ['poin' => 250, 'maks_potongan' => 9000])
            ->assertOk()
            ->assertJsonPath('data.poin', 250)
            ->assertJsonPath('data.maks_potongan', 9000);

        // Satu baris saja, PATCH tidak boleh membuat baris override kedua untuk
        // kode yang barisnya memang sudah ada.
        $this->assertSame(1, KatalogRedeem::query()->where('kode', 'promo_kilat')->count());
    }
}
