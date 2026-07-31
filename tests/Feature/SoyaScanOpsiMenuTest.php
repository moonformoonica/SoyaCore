<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Menu;
use App\Models\PengaturanToko;
use App\Models\Transaksi;
use App\Models\User;
use App\Support\GolonganUkuran;
use App\Support\QrMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Blok E & F — opsi sugar/ice SoyaScan, penghapusan nomor meja, QRIS,
 * QR menu, dan golongan ukuran cup vs botol.
 */
class SoyaScanOpsiMenuTest extends TestCase
{
    use RefreshDatabase;

    private Menu $hot;

    private Menu $reguler;

    private Menu $large;

    private Menu $botol500;

    private Menu $dessert;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $signature = Kategori::create(['nama' => 'Soya Signature']);
        $this->hot = Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Hot', 'harga' => 17000]);
        $this->reguler = Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 17000]);
        $this->large = Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => 'Large', 'harga' => 21000]);
        $this->botol500 = Menu::create(['kategori_id' => $signature->id, 'nama' => 'Original', 'ukuran' => '500ml', 'harga' => 39000]);

        $manis = Kategori::create(['nama' => 'Dessert & Cookies']);
        $this->dessert = Menu::create(['kategori_id' => $manis->id, 'nama' => 'Soy Milk Pudding', 'ukuran' => '', 'harga' => 15000]);

        $this->kasir = User::factory()->create(['role' => 'kasir', 'nama' => 'Adrian']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payloadOrder(array $override = []): array
    {
        return array_merge([
            'nama' => 'Budi',
            'nomor_wa' => '0812-3456-7890',
            'items' => [['menu_id' => $this->reguler->id, 'qty' => 1]],
        ], $override);
    }

    // ------------------------------------------------------- E2: nomor meja

    public function test_order_tanpa_nomor_meja_diterima_201(): void
    {
        $this->postJson('/api/order', $this->payloadOrder())
            ->assertCreated()
            ->assertJsonMissingPath('nomor_meja');
    }

    public function test_order_yang_masih_mengirim_nomor_meja_tetap_diterima(): void
    {
        // Klien lama tidak rusak di tengah revisi: nilainya diabaikan, bukan
        // ditolak.
        $this->postJson('/api/order', $this->payloadOrder(['nomor_meja' => '12']))
            ->assertCreated()
            ->assertJsonMissingPath('nomor_meja');
    }

    /**
     * SoyaScan sengaja TIDAK punya fitur catatan/notes per item — keputusan
     * pemilik produk. Pelanggan hanya memilih sugar & ice; permintaan bebas
     * disampaikan ke kasir di konter.
     *
     * `detail_transaksi.catatan` tetap ada karena KASIR memakainya, jadi
     * kolomnya gampang ikut terisi kalau suatu saat ada yang menambahkan
     * `catatan` ke payload order. Test ini yang menahannya: kalau nanti gagal,
     * berarti notes masuk lewat SoyaScan tanpa keputusan baru.
     */
    public function test_soyascan_tidak_punya_fitur_catatan(): void
    {
        $this->postJson('/api/order', $this->payloadOrder([
            'items' => [[
                'menu_id' => $this->reguler->id,
                'qty' => 1,
                'catatan' => 'tolong dibungkus rapat',
            ]],
        ]))
            ->assertCreated()
            // Tidak ditolak — klien yang mengirimnya tidak rusak — tapi juga
            // tidak dijanjikan di response.
            ->assertJsonMissingPath('items.0.catatan');

        // Yang penting: nilainya tidak tersimpan.
        $this->assertNull(Transaksi::first()->detailTransaksi()->first()->catatan);
    }

    // ------------------------------------------------------- E1: sugar & ice

    public function test_level_sugar_dan_ice_di_luar_daftar_ditolak(): void
    {
        $this->postJson('/api/order', $this->payloadOrder([
            'items' => [['menu_id' => $this->reguler->id, 'qty' => 1, 'level_sugar' => 'setengah']],
        ]))->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');

        $this->postJson('/api/order', $this->payloadOrder([
            'items' => [['menu_id' => $this->reguler->id, 'qty' => 1, 'level_ice' => 'dikit']],
        ]))->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_menu_hot_menerima_sugar_tapi_menolak_ice(): void
    {
        $this->postJson('/api/order', $this->payloadOrder([
            'items' => [['menu_id' => $this->hot->id, 'qty' => 1, 'level_sugar' => 'less']],
        ]))->assertCreated()->assertJsonPath('items.0.level_sugar_label', 'Less Sugar');

        // Es tidak relevan di minuman panas — dan itu harus terlihat, bukan
        // diabaikan diam-diam.
        $this->postJson('/api/order', $this->payloadOrder([
            'items' => [['menu_id' => $this->hot->id, 'qty' => 1, 'level_ice' => 'less']],
        ]))->assertStatus(422)->assertJsonPath('error', 'opsi_tidak_tersedia');
    }

    public function test_kemasan_botol_menolak_sugar_dan_ice(): void
    {
        foreach (['level_sugar' => 'less', 'level_ice' => 'no'] as $field => $nilai) {
            $this->postJson('/api/order', $this->payloadOrder([
                'items' => [['menu_id' => $this->botol500->id, 'qty' => 1, $field => $nilai]],
            ]))
                ->assertStatus(422)
                ->assertJsonPath('error', 'opsi_tidak_tersedia');
        }
    }

    public function test_reguler_dan_large_menerima_keduanya(): void
    {
        foreach ([$this->reguler, $this->large] as $menu) {
            $this->postJson('/api/order', $this->payloadOrder([
                'items' => [['menu_id' => $menu->id, 'qty' => 1, 'level_sugar' => 'no', 'level_ice' => 'extra']],
            ]))
                ->assertCreated()
                ->assertJsonPath('items.0.level_sugar', 'no')
                ->assertJsonPath('items.0.level_sugar_label', 'No Sugar')
                ->assertJsonPath('items.0.level_ice', 'extra')
                ->assertJsonPath('items.0.level_ice_label', 'Extra Ice');
        }

        $detail = Transaksi::first()->detailTransaksi()->first();
        $this->assertSame('no', $detail->level_sugar);
        $this->assertSame('extra', $detail->level_ice);
    }

    public function test_dessert_tidak_bisa_pilih_sugar_maupun_ice(): void
    {
        $menu = collect($this->getJson('/api/menu')->assertOk()->json('kategori'))
            ->firstWhere('nama', 'Dessert & Cookies')['menu'][0];

        $this->assertFalse($menu['bisa_pilih_sugar']);
        $this->assertFalse($menu['bisa_pilih_ice']);
        $this->assertSame(GolonganUkuran::LAINNYA, $menu['golongan_ukuran']);

        $this->postJson('/api/order', $this->payloadOrder([
            'items' => [['menu_id' => $this->dessert->id, 'qty' => 1, 'level_sugar' => 'less']],
        ]))->assertStatus(422)->assertJsonPath('error', 'opsi_tidak_tersedia');
    }

    public function test_meta_opsi_sugar_dan_ice_muncul_di_katalog_menu(): void
    {
        $respon = $this->getJson('/api/menu')->assertOk();

        // Frontend merender dari sini, tidak menyalin daftarnya sendiri.
        $respon->assertJsonCount(4, 'meta.opsi_sugar')
            ->assertJsonPath('meta.opsi_sugar.0.kode', 'normal')
            ->assertJsonPath('meta.opsi_sugar.1.label', 'Less Sugar')
            ->assertJsonCount(4, 'meta.opsi_ice')
            ->assertJsonPath('meta.opsi_ice.1.label', 'Less Ice')
            ->assertJsonPath('meta.golongan_ukuran', ['cup', 'botol', 'lainnya']);
    }

    public function test_flag_bisa_pilih_per_ukuran_di_katalog(): void
    {
        $menu = collect($this->getJson('/api/menu')->assertOk()->json('kategori'))
            ->firstWhere('nama', 'Soya Signature')['menu'];

        $perUkuran = collect($menu)->keyBy('ukuran');

        $this->assertTrue($perUkuran['Hot']['bisa_pilih_sugar']);
        $this->assertFalse($perUkuran['Hot']['bisa_pilih_ice']);

        $this->assertTrue($perUkuran['Reguler']['bisa_pilih_sugar']);
        $this->assertTrue($perUkuran['Reguler']['bisa_pilih_ice']);

        $this->assertFalse($perUkuran['500ml']['bisa_pilih_sugar']);
        $this->assertFalse($perUkuran['500ml']['bisa_pilih_ice']);
    }

    public function test_kasir_bisa_mencatat_sugar_ice_dan_ikut_tampil_di_detail_transaksi(): void
    {
        Sanctum::actingAs($this->kasir);

        $id = $this->postJson('/api/transaksi', [])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", [
            'menu_id' => $this->reguler->id, 'qty' => 1, 'level_sugar' => 'less', 'level_ice' => 'no',
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.level_sugar', 'less')
            ->assertJsonPath('data.items.0.level_sugar_label', 'Less Sugar')
            ->assertJsonPath('data.items.0.level_ice_label', 'No Ice');

        // Aturan ketersediaan berlaku sama untuk kasir.
        $this->postJson("/api/transaksi/{$id}/items", [
            'menu_id' => $this->botol500->id, 'qty' => 1, 'level_sugar' => 'less',
        ])->assertStatus(422)->assertJsonPath('error', 'opsi_tidak_tersedia');
    }

    public function test_item_dengan_opsi_berbeda_tidak_digabung_jadi_satu_baris(): void
    {
        Sanctum::actingAs($this->kasir);

        $id = $this->postJson('/api/transaksi', [])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", [
            'menu_id' => $this->reguler->id, 'qty' => 1, 'level_sugar' => 'less',
        ])->assertOk();

        $respon = $this->postJson("/api/transaksi/{$id}/items", [
            'menu_id' => $this->reguler->id, 'qty' => 1, 'level_sugar' => 'normal',
        ])->assertOk();

        // Dua instruksi berbeda buat barista harus tetap dua baris.
        $this->assertCount(2, $respon->json('data.items'));
    }

    // ------------------------------------------------------------ E3: QRIS

    public function test_upload_qris_manager_boleh_kasir_ditolak(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->kasir);
        $this->postJson('/api/pengaturan/toko/qris', ['qris' => UploadedFile::fake()->image('qris.png')])
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');

        Sanctum::actingAs($this->manager());
        $respon = $this->postJson('/api/pengaturan/toko/qris', [
            'qris' => UploadedFile::fake()->image('qris.png', 400, 400),
        ])->assertOk();

        $this->assertNotNull($respon->json('data.qris_url'));

        $path = PengaturanToko::current()->qris_gambar;
        Storage::disk('public')->assertExists($path);

        // Ikut terbaca di endpoint baca pengaturan toko.
        $this->getJson('/api/pengaturan/toko')
            ->assertOk()
            ->assertJsonPath('data.qris_url', $respon->json('data.qris_url'));
    }

    public function test_upload_qris_berkas_non_gambar_ditolak(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/pengaturan/toko/qris', [
            'qris' => UploadedFile::fake()->create('qris.pdf', 100, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_ganti_qris_menghapus_berkas_lama(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/pengaturan/toko/qris', ['qris' => UploadedFile::fake()->image('lama.png')])->assertOk();
        $lama = PengaturanToko::current()->qris_gambar;

        $this->postJson('/api/pengaturan/toko/qris', ['qris' => UploadedFile::fake()->image('baru.png')])->assertOk();
        $baru = PengaturanToko::current()->qris_gambar;

        $this->assertNotSame($lama, $baru);
        // Kalau tidak dihapus, storage menumpuk berkas yang tidak pernah dipakai.
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
    }

    public function test_hapus_qris(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/pengaturan/toko/qris', ['qris' => UploadedFile::fake()->image('qris.png')])->assertOk();
        $path = PengaturanToko::current()->qris_gambar;

        $this->deleteJson('/api/pengaturan/toko/qris')
            ->assertOk()
            ->assertJsonPath('data.qris_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(PengaturanToko::current()->qris_gambar);
    }

    public function test_qris_url_hanya_muncul_di_response_order_saat_metode_qris(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->manager());
        $this->postJson('/api/pengaturan/toko/qris', ['qris' => UploadedFile::fake()->image('qris.png')])->assertOk();

        // Tanpa auth (SoyaScan publik).
        app('auth')->forgetGuards();

        $this->postJson('/api/order', $this->payloadOrder(['metode_bayar' => 'qris']))
            ->assertCreated()
            ->assertJsonPath('qris_url', PengaturanToko::current()->qrisUrl());

        $this->postJson('/api/order', $this->payloadOrder(['metode_bayar' => 'cash']))
            ->assertCreated()
            ->assertJsonMissingPath('qris_url');

        $this->postJson('/api/order', $this->payloadOrder())
            ->assertCreated()
            ->assertJsonMissingPath('qris_url');
    }

    public function test_qris_url_null_kalau_belum_diunggah(): void
    {
        $this->postJson('/api/order', $this->payloadOrder(['metode_bayar' => 'qris']))
            ->assertCreated()
            ->assertJsonPath('qris_url', null);
    }

    // --------------------------------------------------------- E4: QR menu

    public function test_qr_menu_mengembalikan_svg_secara_default(): void
    {
        Sanctum::actingAs($this->manager());

        $respon = $this->get('/api/pengaturan/toko/qr-menu')->assertOk();

        $this->assertSame('image/svg+xml', $respon->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $respon->getContent());
    }

    public function test_qr_menu_format_png(): void
    {
        Sanctum::actingAs($this->manager());

        $respon = $this->get('/api/pengaturan/toko/qr-menu?format=png&ukuran=256')->assertOk();

        $this->assertSame('image/png', $respon->headers->get('Content-Type'));
        // Signature PNG, bukan JSON base64: manager bisa langsung menyimpannya.
        $this->assertStringStartsWith("\x89PNG", $respon->getContent());
    }

    public function test_qr_menu_ditolak_untuk_kasir(): void
    {
        Sanctum::actingAs($this->kasir);

        $this->getJson('/api/pengaturan/toko/qr-menu')
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');
    }

    public function test_qr_menu_validasi_format_dan_ukuran(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/pengaturan/toko/qr-menu?format=gif')->assertStatus(422);
        $this->getJson('/api/pengaturan/toko/qr-menu?ukuran='.(QrMenu::UKURAN_MAKS + 1))->assertStatus(422);
        $this->getJson('/api/pengaturan/toko/qr-menu?ukuran=10')->assertStatus(422);
    }

    public function test_qr_menu_memakai_url_dari_config_bukan_hardcode(): void
    {
        config(['soyascan.url' => 'https://soyascan.gressoy.test/scan/']);

        // Trailing slash dinormalkan supaya QR yang dicetak tidak menunjuk URL
        // dengan garis miring ganda.
        $this->assertSame('https://soyascan.gressoy.test/scan', QrMenu::url());
    }

    // ------------------------------------------------ F: golongan & urutan

    public function test_golongan_ukuran_benar_untuk_cup_botol_dan_dessert(): void
    {
        Sanctum::actingAs($this->manager());

        $menu = collect($this->getJson('/api/menu-internal')->assertOk()->json('data'))->keyBy('ukuran');

        $this->assertSame(GolonganUkuran::CUP, $menu['Hot']['golongan_ukuran']);
        $this->assertSame(GolonganUkuran::CUP, $menu['Reguler']['golongan_ukuran']);
        $this->assertSame(GolonganUkuran::CUP, $menu['Large']['golongan_ukuran']);
        $this->assertSame(GolonganUkuran::BOTOL, $menu['500ml']['golongan_ukuran']);
        $this->assertSame(GolonganUkuran::LAINNYA, $menu['']['golongan_ukuran']);
    }

    public function test_filter_golongan_di_menu_internal(): void
    {
        Sanctum::actingAs($this->manager());

        $cup = array_column($this->getJson('/api/menu-internal?golongan=cup')->assertOk()->json('data'), 'ukuran');
        $this->assertSame(['Hot', 'Reguler', 'Large'], $cup);

        $botol = array_column($this->getJson('/api/menu-internal?golongan=botol')->assertOk()->json('data'), 'ukuran');
        $this->assertSame(['500ml'], $botol);

        $lainnya = array_column($this->getJson('/api/menu-internal?golongan=lainnya')->assertOk()->json('data'), 'ukuran');
        $this->assertSame([''], $lainnya);
    }

    public function test_urutan_ukuran_mengikuti_urutan_eksplisit_bukan_alfabetis(): void
    {
        Menu::create(['kategori_id' => $this->reguler->kategori_id, 'nama' => 'Original', 'ukuran' => '250ml', 'harga' => 22000]);
        Menu::create(['kategori_id' => $this->reguler->kategori_id, 'nama' => 'Original', 'ukuran' => '1000ml', 'harga' => 74000]);

        // Alfabetis akan menghasilkan 1000ml, 250ml, 500ml, Hot, Large, Reguler.
        $harapan = ['Hot', 'Reguler', 'Large', '250ml', '500ml', '1000ml'];

        $katalog = collect($this->getJson('/api/menu')->assertOk()->json('kategori'))
            ->firstWhere('nama', 'Soya Signature')['menu'];
        $this->assertSame($harapan, array_column($katalog, 'ukuran'));

        Sanctum::actingAs($this->manager());
        $internal = $this->getJson('/api/menu-internal?golongan=cup')->assertOk()->json('data');
        $this->assertSame(['Hot', 'Reguler', 'Large'], array_column($internal, 'ukuran'));
    }
}
