<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Membatalkan redeem pada pesanan yang BELUM dibayar.
 *
 * Sampai sekarang satu-satunya jalan keluar dari salah pilih reward adalah
 * membatalkan seluruh transaksi lalu menyusunnya lagi dari nol, persis yang
 * disarankan pesan error `transaksi_sudah_redeem`. Kasir harus mengetik ulang
 * seluruh pesanan di depan pelanggan hanya karena salah menekan satu tombol.
 *
 * Yang dijaga di sini: membatalkan redeem mengembalikan keadaan ke SEBELUM
 * redeem, ketiganya sekaligus. Poin kembali tapi hadiahnya masih menempel =
 * toko rugi. Hadiah dicabut tapi poin tidak kembali = pelanggan rugi. Keduanya
 * kegagalan senyap: tidak ada error, angkanya saja yang salah.
 */
class BatalRedeemTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    private Loyalty $loyalty;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Signature']);
        $this->menu = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 17000,
        ]);

        $customer = Customer::create(['nama' => 'Budi', 'no_wa' => '6281234567890']);
        $this->loyalty = Loyalty::create(['customer_id' => $customer->id, 'poin' => 1000]);

        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
    }

    /** @return int Id transaksi berisi `$qty` item, milik Budi. */
    private function pesananBudi(int $qty = 2): int
    {
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '6281234567890'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->menu->id, 'qty' => $qty])
            ->assertOk();

        return $id;
    }

    // =====================================================================
    // Voucher diskon
    // =====================================================================

    public function test_batal_redeem_diskon_mengembalikan_poin_dan_mencabut_potongannya(): void
    {
        $id = $this->pesananBudi(2); // 2 x 17.000 = 34.000

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();

        // Poin sudah terpotong sejak reward dipilih, walau pesanannya pending.
        $this->assertSame(1000 - 100, (int) $this->loyalty->fresh()->poin);
        $this->assertSame(34000 - 3400, (int) Transaksi::find($id)->total);

        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")->assertOk();

        // 1. Poin kembali utuh.
        $this->assertSame(1000, (int) $this->loyalty->fresh()->poin);

        $transaksi = Transaksi::with('detailTransaksi')->find($id);

        // 2. Potongannya dicabut, total kembali ke harga penuh.
        $this->assertSame(34000, (int) $transaksi->total);
        $this->assertSame(0, (int) $transaksi->detailTransaksi->sum('diskon_nilai'));
        $this->assertSame(0, (int) $transaksi->detailTransaksi->max('diskon_persen'));

        // 3. Jejak redeem-nya hilang, jadi transaksinya bebas dipakai lagi.
        $this->assertNull($transaksi->kode_redeem);
        $this->assertSame(0, (int) $transaksi->poin_ditukar);
        $this->assertNull($transaksi->maks_potongan);
    }

    // =====================================================================
    // Gratis menu
    // =====================================================================

    public function test_batal_redeem_gratis_menu_mengembalikan_poin_dan_menghapus_item_hadiah(): void
    {
        $id = $this->pesananBudi(1); // 17.000

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])->assertOk();

        $this->assertSame(1000 - 350, (int) $this->loyalty->fresh()->poin);
        $this->assertCount(2, Transaksi::find($id)->detailTransaksi, 'Item hadiah ikut masuk.');

        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")->assertOk();

        $this->assertSame(1000, (int) $this->loyalty->fresh()->poin);

        $transaksi = Transaksi::with('detailTransaksi')->find($id);

        // Item hadiahnya dicabut, item yang dibeli pelanggan tidak ikut hilang.
        $this->assertCount(1, $transaksi->detailTransaksi);
        $this->assertFalse((bool) $transaksi->detailTransaksi->first()->is_reward);
        $this->assertSame(17000, (int) $transaksi->total);
        $this->assertNull($transaksi->kode_redeem);
    }

    // =====================================================================
    // Sesudah dibatalkan, transaksinya benar-benar bebas
    // =====================================================================

    /**
     * Inti kegunaannya: kasir yang salah pilih reward bisa langsung memilih
     * yang benar, tanpa mengetik ulang seluruh pesanan.
     */
    public function test_setelah_dibatalkan_bisa_redeem_reward_lain(): void
    {
        $id = $this->pesananBudi(2);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();
        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")->assertOk();

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_20'])->assertOk();

        $transaksi = Transaksi::find($id);
        $this->assertSame('diskon_20', $transaksi->kode_redeem);
        $this->assertSame(34000 - 6800, (int) $transaksi->total);

        // Yang terpotong hanya reward yang benar-benar dipakai, bukan keduanya.
        $this->assertSame(1000 - 200, (int) $this->loyalty->fresh()->poin);
    }

    /**
     * Diskon manual dikunci selama transaksi memakai redeem. Setelah redeem-nya
     * dibatalkan, kuncinya harus ikut lepas, kalau tidak transaksinya jadi
     * setengah terkunci tanpa alasan yang bisa dijelaskan ke kasir.
     */
    public function test_setelah_dibatalkan_diskon_manual_tidak_lagi_terkunci(): void
    {
        $id = $this->pesananBudi(2);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();
        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'custom_persen', 'nilai' => 15])
            ->assertStatus(409)
            ->assertJsonPath('error', 'diskon_terkunci_redeem');

        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")->assertOk();

        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'custom_persen', 'nilai' => 15])->assertOk();
        $this->assertSame(34000 - 5100, (int) Transaksi::find($id)->total);
    }

    // =====================================================================
    // Penolakan
    // =====================================================================

    public function test_transaksi_tanpa_redeem_ditolak(): void
    {
        $id = $this->pesananBudi();

        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")
            ->assertStatus(422)
            ->assertJsonPath('error', 'transaksi_tanpa_redeem');
    }

    /**
     * Sesudah dibayar, poin earn sudah diberikan dan laporan sudah
     * diproyeksikan. Membatalkan redeem lewat jalur ini akan mengubah total
     * transaksi yang sudah tercatat tanpa meninggalkan dokumen apa pun, jadi
     * jalurnya harus lewat pembatalan/koreksi pesanan.
     */
    public function test_transaksi_yang_sudah_lunas_ditolak(): void
    {
        $id = $this->pesananBudi(2);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();

        $poinSetelahBayar = (int) $this->loyalty->fresh()->poin;

        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")
            ->assertStatus(409)
            ->assertJsonPath('error', 'transaksi_sudah_lunas');

        // Dan saldonya tidak tersentuh sama sekali oleh percobaan itu.
        $this->assertSame($poinSetelahBayar, (int) $this->loyalty->fresh()->poin);
        $this->assertSame('diskon_10', Transaksi::find($id)->kode_redeem);
    }

    public function test_membatalkan_dua_kali_tidak_menggandakan_poin(): void
    {
        $id = $this->pesananBudi(2);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'diskon_10'])->assertOk();
        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")->assertOk();
        $this->deleteJson("/api/transaksi/{$id}/redeem-poin")->assertStatus(422);

        $this->assertSame(1000, (int) $this->loyalty->fresh()->poin);
    }
}
