<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\LaporanTransaksi;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Blok D — pembatalan / koreksi pesanan yang salah.
 *
 * BUKAN pengembalian uang: tidak ada kas keluar, tidak ada metode pengembalian
 * dana. Nilainya dicatat karena omzet dashboard dan laporan kasir harus ikut
 * terkoreksi — penjualan yang dibatalkan tidak boleh tetap terhitung.
 */
class PembatalanTest extends TestCase
{
    use RefreshDatabase;

    private Menu $reguler;

    private Menu $large;

    private User $kasir1;

    private User $kasir2;

    /** @var array{nama: string, no_wa: string} */
    private array $customer = ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'];

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Signature']);
        $this->reguler = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 20000,
        ]);
        $this->large = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Taro Thanos', 'ukuran' => 'Large', 'harga' => 30000,
        ]);

        $this->kasir1 = User::factory()->create(['role' => 'kasir', 'nama' => 'Kasir Satu']);
        $this->kasir2 = User::factory()->create(['role' => 'kasir', 'nama' => 'Kasir Dua']);

        Sanctum::actingAs($this->kasir1);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    /**
     * @param  array<int, array{0: Menu, 1: int}>  $items
     */
    private function pesanan(array $items, bool $denganCustomer = false): int
    {
        $id = $this->postJson('/api/transaksi', $denganCustomer ? ['customer' => $this->customer] : [])
            ->assertCreated()->json('data.id');

        foreach ($items as [$menu, $qty]) {
            $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $menu->id, 'qty' => $qty])->assertOk();
        }

        return $id;
    }

    private function lunasi(int $id): void
    {
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();
    }

    private function itemId(int $transaksiId, Menu $menu): int
    {
        return Transaksi::find($transaksiId)->detailTransaksi()->where('menu_id', $menu->id)->value('id');
    }

    private function saldoPoin(): int
    {
        return (int) Loyalty::first()->poin;
    }

    public function test_pembatalan_penuh_transaksi_lunas_menurunkan_omzet_dashboard(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]); // 40.000
        $this->lunasi($id);

        $this->assertSame(40000, (int) LaporanTransaksi::sum('total'));

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Pelanggan salah pesan'])
            ->assertCreated()
            ->assertJsonPath('status_transaksi', 'batal')
            ->assertJsonPath('data.nilai_dibatalkan', 40000)
            ->assertJsonPath('data.alasan', 'Pelanggan salah pesan');

        $this->assertSame('batal', Transaksi::find($id)->status);

        // Omzet dashboard benar-benar turun, bukan cuma berhenti bertambah.
        $this->assertSame(0, (int) LaporanTransaksi::sum('total'));
        $this->assertSame(0, LaporanTransaksi::count());

        // Transaksi aslinya tidak dihapus dan isinya tidak diubah.
        $this->assertSame(40000, (int) Transaksi::find($id)->total);
        $this->assertSame(1, Transaksi::find($id)->detailTransaksi()->count());
    }

    public function test_pembatalan_sebagian_menghasilkan_status_dan_nilai_proporsional(): void
    {
        $id = $this->pesanan([[$this->reguler, 3], [$this->large, 1]]); // 60.000 + 30.000
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Salah ukuran satu gelas',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->reguler), 'qty' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('status_transaksi', 'batal_sebagian')
            ->assertJsonPath('data.nilai_dibatalkan', 20000)
            ->assertJsonPath('data.items.0.qty', 1);

        // Omzet turun sebesar 1 gelas saja: 90.000 - 20.000.
        $this->assertSame(70000, (int) LaporanTransaksi::sum('total'));

        // Qty terjual ikut turun, tapi barisnya tetap ada.
        $this->assertSame(2, (int) LaporanTransaksi::where('ukuran', 'Reguler')->value('qty'));
        $this->assertSame(2, LaporanTransaksi::count());
    }

    public function test_pembatalan_sebagian_yang_menghabiskan_semua_item_berakhir_batal(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]);
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Semua salah',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->reguler), 'qty' => 2]],
        ])
            ->assertCreated()
            ->assertJsonPath('status_transaksi', 'batal');

        $this->assertSame(0, LaporanTransaksi::count());
    }

    public function test_qty_melebihi_asli_ditolak_termasuk_akumulasi(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]);
        $this->lunasi($id);
        $item = $this->itemId($id, $this->reguler);

        // Sekali kirim melebihi qty asli.
        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Coba melebihi',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 3]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'qty_pembatalan_melebihi');

        // Sah: 1 dari 2.
        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Satu gelas salah',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 1]],
        ])->assertCreated();

        // AKUMULASI: 1 lagi masih boleh, tapi 2 sudah melebihi sisanya.
        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Coba melebihi lagi',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 2]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'qty_pembatalan_melebihi');

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Sisanya juga salah',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 1]],
        ])->assertCreated();

        $this->assertSame(40000, (int) Pembatalan::sum('nilai_dibatalkan'));
    }

    public function test_item_berdiskon_menghasilkan_nilai_setelah_diskon(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]); // 40.000
        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 20])->assertOk();
        $this->lunasi($id);

        // Total setelah diskon 20% = 32.000.
        $this->assertSame(32000, (int) Transaksi::find($id)->total);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Satu gelas salah',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->reguler), 'qty' => 1]],
        ])
            ->assertCreated()
            // (40.000 - 8.000) x 1/2 = 16.000, BUKAN harga mentah 20.000.
            ->assertJsonPath('data.nilai_dibatalkan', 16000);

        // Omzet tidak pernah jadi minus.
        $this->assertSame(16000, (int) LaporanTransaksi::sum('total'));
    }

    public function test_pembatalan_penuh_menghabiskan_nilai_persis_tanpa_residu_pembulatan(): void
    {
        // 3 item dengan diskon 10% → nilai bersih 54.000 yang tidak habis
        // dibagi 3 secara bulat.
        $id = $this->pesanan([[$this->reguler, 3]]);
        $this->postJson("/api/transaksi/{$id}/diskon", ['tipe' => 'preset', 'nilai' => 10])->assertOk();
        $this->lunasi($id);

        $item = $this->itemId($id, $this->reguler);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Satu salah',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 1]],
        ])->assertCreated();

        // Sisa dua qty dibatalkan penuh: totalnya harus pas 54.000, tidak
        // menyisakan omzet beberapa rupiah yang tidak bisa dihilangkan.
        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Sisanya juga salah'])
            ->assertCreated();

        $this->assertSame(54000, (int) Pembatalan::sum('nilai_dibatalkan'));
        $this->assertSame(0, (int) LaporanTransaksi::sum('total'));
    }

    public function test_poin_earn_ditarik_proporsional(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]], true); // 40.000 → 40 poin
        $this->lunasi($id);

        $this->assertSame(Loyalty::POIN_BONUS_DAFTAR + 40, $this->saldoPoin());

        // Batalkan separuh → tarik separuh poinnya.
        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Satu gelas salah',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->reguler), 'qty' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.poin_ditarik', 20)
            ->assertJsonPath('saldo_poin_pelanggan', Loyalty::POIN_BONUS_DAFTAR + 20);

        $this->assertSame(Loyalty::POIN_BONUS_DAFTAR + 20, $this->saldoPoin());
    }

    public function test_poin_earn_tidak_pernah_negatif(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]], true); // 40 poin
        $this->lunasi($id);

        // Pelanggan sudah membelanjakan poinnya sampai hampir habis.
        Loyalty::query()->update(['poin' => 5]);

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Salah semua'])
            ->assertCreated()
            // Yang dicatat adalah poin yang BENAR-BENAR bisa ditarik.
            ->assertJsonPath('data.poin_ditarik', 5)
            ->assertJsonPath('saldo_poin_pelanggan', 0);

        $this->assertSame(0, $this->saldoPoin());
    }

    /**
     * REGRESSION D1 — sisi poin earn.
     *
     * Transaksi pending belum pernah memberi poin earn (`loyalty_applied_at`
     * masih null), jadi tidak ada yang boleh ditarik.
     */
    public function test_pembatalan_transaksi_pending_tidak_menarik_poin_earn(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]], true);

        $this->assertNull(Transaksi::find($id)->loyalty_applied_at);
        $saldoAwal = $this->saldoPoin();

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Pelanggan batal'])
            ->assertCreated()
            ->assertJsonPath('data.poin_ditarik', 0)
            ->assertJsonPath('status_transaksi', 'batal');

        $this->assertSame($saldoAwal, $this->saldoPoin());
    }

    /**
     * REGRESSION D1 — bug yang diperbaiki.
     *
     * `redeemPoin()` sudah memotong saldo saat redeem walau transaksinya masih
     * pending, sementara `batal()` lama cuma mengubah status. Akibatnya
     * pelanggan kehilangan poinnya DAN tidak mendapat minumannya.
     */
    public function test_pembatalan_pending_ber_redeem_mengembalikan_poin_utuh(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]], true);

        Loyalty::query()->update(['poin' => 500, 'poin_kedaluwarsa_pada' => null]);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])->assertOk();

        // Saldo sudah terpotong 350 walau masih pending.
        $this->assertSame(150, $this->saldoPoin());

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Pelanggan batal'])
            ->assertCreated()
            ->assertJsonPath('data.poin_dikembalikan', 350)
            ->assertJsonPath('data.poin_ditarik', 0)
            ->assertJsonPath('saldo_poin_pelanggan', 500);

        $this->assertSame(500, $this->saldoPoin());

        // Jejak redemption ikut dikosongkan supaya diskon/reward yang sudah
        // digugurkan tidak tetap menempel.
        $transaksi = Transaksi::find($id);
        $this->assertNull($transaksi->kode_redeem);
        $this->assertSame(0, (int) $transaksi->poin_ditukar);
        $this->assertNull($transaksi->maks_potongan);
    }

    public function test_pembatalan_penuh_transaksi_lunas_ber_redeem_mengembalikan_poin_utuh(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]], true);

        Loyalty::query()->update(['poin' => 500, 'poin_kedaluwarsa_pada' => null]);
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])->assertOk();
        $this->lunasi($id);

        // 150 sisa redeem + 20 poin earn dari belanja 20.000.
        $this->assertSame(170, $this->saldoPoin());

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Salah semua'])
            ->assertCreated()
            ->assertJsonPath('data.poin_dikembalikan', 350)
            ->assertJsonPath('data.poin_ditarik', 20);

        // 170 - 20 (earn ditarik) + 350 (redeem kembali) = 500.
        $this->assertSame(500, $this->saldoPoin());
    }

    public function test_pembatalan_sebagian_tanpa_item_reward_tidak_mengembalikan_poin_redeem(): void
    {
        $id = $this->pesanan([[$this->reguler, 1], [$this->large, 2]], true);

        Loyalty::query()->update(['poin' => 500, 'poin_kedaluwarsa_pada' => null]);
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])->assertOk();
        $this->lunasi($id);

        $saldoSebelum = $this->saldoPoin();

        // Yang dibatalkan item berbayar, bukan item rewardnya.
        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Satu Taro salah',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->large), 'qty' => 1]],
        ])
            ->assertCreated()
            // Rewardnya memang tetap diterima pelanggan.
            ->assertJsonPath('data.poin_dikembalikan', 0);

        $transaksi = Transaksi::find($id);
        $this->assertSame('gratis_original', $transaksi->kode_redeem);
        $this->assertSame(350, (int) $transaksi->poin_ditukar);

        // Saldo hanya berubah karena poin earn yang ditarik, bukan redeem.
        $this->assertLessThan($saldoSebelum, $this->saldoPoin());
        $this->assertSame($saldoSebelum - $transaksi->pembatalan()->sum('poin_ditarik'), $this->saldoPoin());
    }

    public function test_pembatalan_sebagian_yang_menyertakan_item_reward_mengembalikan_poin(): void
    {
        $id = $this->pesanan([[$this->reguler, 1], [$this->large, 1]], true);

        Loyalty::query()->update(['poin' => 500, 'poin_kedaluwarsa_pada' => null]);
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])->assertOk();
        $this->lunasi($id);

        $reward = Transaksi::find($id)->detailTransaksi()->where('is_reward', true)->first();
        $this->assertNotNull($reward);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Reward salah menu',
            'items' => [['detail_transaksi_id' => $reward->id, 'qty' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.poin_dikembalikan', 350)
            ->assertJsonPath('data.items.0.is_reward', true);

        $this->assertNull(Transaksi::find($id)->kode_redeem);
    }

    public function test_alasan_kosong_ditolak(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]]);
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => '  '])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');

        $this->assertSame(0, Pembatalan::count());
    }

    public function test_transaksi_yang_sudah_batal_menolak_pembatalan_kedua_dengan_409(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]]);
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Salah pesan'])->assertCreated();

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Salah lagi'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'transaksi_sudah_batal');
    }

    public function test_pembatalan_sebagian_transaksi_pending_diarahkan_ke_ubah_item(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Kurangi satu',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->reguler), 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'pembatalan_sebagian_butuh_lunas');
    }

    public function test_item_bukan_milik_transaksi_ditolak(): void
    {
        $lain = $this->pesanan([[$this->large, 1]]);
        $this->lunasi($lain);

        $id = $this->pesanan([[$this->reguler, 1]]);
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Coba item transaksi lain',
            'items' => [['detail_transaksi_id' => $this->itemId($lain, $this->large), 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'item_bukan_milik_transaksi');
    }

    public function test_endpoint_lama_batal_tetap_jalan_dan_ikut_mengembalikan_poin_redeem(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]], true);

        Loyalty::query()->update(['poin' => 500, 'poin_kedaluwarsa_pada' => null]);
        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])->assertOk();
        $this->assertSame(150, $this->saldoPoin());

        $this->postJson("/api/transaksi/{$id}/batal")
            ->assertOk()
            ->assertJsonPath('data.status', 'batal');

        // Inilah yang dulu hilang: poin redeem kembali walau lewat alias lama.
        $this->assertSame(500, $this->saldoPoin());

        // Dan pembatalannya tercatat sebagai dokumen, bukan cuma ubah status.
        $pembatalan = Pembatalan::first();
        $this->assertNotNull($pembatalan);
        $this->assertSame('Dibatalkan lewat endpoint lama', $pembatalan->alasan);
        $this->assertSame($this->kasir1->id, $pembatalan->user_id);
    }

    public function test_riwayat_pembatalan_per_transaksi(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]);
        $this->lunasi($id);
        $item = $this->itemId($id, $this->reguler);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Gelas pertama salah',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 1]],
        ])->assertCreated();

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Gelas kedua juga',
            'items' => [['detail_transaksi_id' => $item, 'qty' => 1]],
        ])->assertCreated();

        $this->getJson("/api/transaksi/{$id}/pembatalan")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.alasan', 'Gelas kedua juga')
            ->assertJsonPath('data.0.diproses_oleh.nama', 'Kasir Satu');
    }

    public function test_daftar_pembatalan_manager_saja_dengan_filter_akun(): void
    {
        $satu = $this->pesanan([[$this->reguler, 1]]);
        $this->lunasi($satu);
        $this->postJson("/api/transaksi/{$satu}/pembatalan", ['alasan' => 'Salah A'])->assertCreated();

        Sanctum::actingAs($this->kasir2);
        $dua = $this->pesanan([[$this->large, 1]]);
        $this->lunasi($dua);
        $this->postJson("/api/transaksi/{$dua}/pembatalan", ['alasan' => 'Salah B'])->assertCreated();

        // Kasir tidak boleh melihat rekap pembatalan seluruh toko.
        $this->getJson('/api/pembatalan')->assertStatus(403);

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/pembatalan')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.jumlah_pembatalan', 2)
            ->assertJsonPath('meta.nilai_dibatalkan', 50000);

        $this->getJson('/api/pembatalan?user_id='.$this->kasir2->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alasan', 'Salah B')
            ->assertJsonPath('meta.nilai_dibatalkan', 30000);
    }

    /**
     * Pembatalan tercatat atas akun yang MEMPROSESnya, bukan atas pembuat
     * penjualan aslinya.
     */
    public function test_pembatalan_kasir2_atas_penjualan_kasir1_tercatat_atas_kasir2(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]]); // dibuat & dilunasi Kasir 1
        $this->lunasi($id);

        Sanctum::actingAs($this->kasir2);
        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Kesalahan shift sebelumnya'])
            ->assertCreated()
            ->assertJsonPath('data.diproses_oleh.nama', 'Kasir Dua');

        $this->assertSame($this->kasir2->id, Pembatalan::first()->user_id);

        Sanctum::actingAs($this->manager());
        $respon = $this->getJson('/api/laporan/kasir')->assertOk();
        $data = collect($respon->json('data'))->keyBy('nama');

        // Akuntabilitas pembatalan menempel di Kasir Dua…
        $this->assertSame(1, $data['Kasir Dua']['jumlah_pembatalan']);
        $this->assertSame(20000, $data['Kasir Dua']['nilai_dibatalkan']);
        $this->assertSame(0, $data['Kasir Dua']['jumlah_transaksi']);

        // …sementara penjualan Kasir Satu sudah gugur seluruhnya, jadi ia tidak
        // punya baris penjualan sama sekali. Omzetnya tidak "dipindahkan" ke
        // Kasir Dua: kalau pengurangnya ikut mengejar akun pemroses, `meta`
        // tidak lagi bisa direkonsiliasi dengan dashboard.
        $this->assertArrayNotHasKey('Kasir Satu', $data->all());
        $this->assertSame(0, $respon->json('meta.total_omzet'));
        $this->assertSame(0, (int) LaporanTransaksi::sum('total'));
    }

    public function test_pembatalan_menurunkan_total_omzet_akun_yang_memprosesnya(): void
    {
        // Kasus normal: kasir yang sama menjual dan membatalkan.
        $dibatalkan = $this->pesanan([[$this->reguler, 1]]); // 20.000
        $this->lunasi($dibatalkan);

        $tetap = $this->pesanan([[$this->large, 1]]); // 30.000
        $this->lunasi($tetap);

        $this->postJson("/api/transaksi/{$dibatalkan}/pembatalan", ['alasan' => 'Salah pesan'])->assertCreated();

        Sanctum::actingAs($this->manager());
        $baris = $this->getJson('/api/laporan/kasir')->assertOk()->json('data.0');

        $this->assertSame('Kasir Satu', $baris['nama']);
        $this->assertSame(30000, $baris['total_omzet']);
        $this->assertSame(1, $baris['jumlah_pembatalan']);
        $this->assertSame(20000, $baris['nilai_dibatalkan']);
    }

    public function test_walk_in_tanpa_customer_tidak_punya_saldo_poin(): void
    {
        $id = $this->pesanan([[$this->reguler, 1]]);
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", ['alasan' => 'Salah pesan'])
            ->assertCreated()
            ->assertJsonPath('saldo_poin_pelanggan', null)
            ->assertJsonPath('data.poin_ditarik', 0)
            ->assertJsonPath('data.poin_dikembalikan', 0);
    }

    public function test_status_batal_sebagian_bisa_difilter_di_daftar_transaksi(): void
    {
        $id = $this->pesanan([[$this->reguler, 2]]);
        $this->lunasi($id);

        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Satu salah',
            'items' => [['detail_transaksi_id' => $this->itemId($id, $this->reguler), 'qty' => 1]],
        ])->assertCreated();

        $this->getJson('/api/transaksi?status=batal_sebagian')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }
}
