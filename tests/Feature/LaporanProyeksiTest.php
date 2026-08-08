<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\LaporanTransaksi;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\PengaturanLoyalty;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\LaporanProjector;
use App\Services\LaporanQuery;
use App\Support\WaktuToko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Blok B, pesanan baru masuk ke dashboard lewat proyeksi ke
 * `laporan_transaksi`, bucket "other" yang berlabel, dan label hari Indonesia
 * di tren penjualan.
 *
 * Sebelum ini dashboard hanya membaca `laporan_transaksi` yang diisi CSV
 * historis, sementara tabel POS live tidak pernah dibaca sama sekali, jadi
 * pesanan baru memang tidak akan pernah muncul.
 */
class LaporanProyeksiTest extends TestCase
{
    use RefreshDatabase;

    private Menu $reguler;

    private Menu $dessert;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $signature = Kategori::create(['nama' => 'Soya Signature']);
        $this->reguler = Menu::create([
            'kategori_id' => $signature->id, 'nama' => 'Original', 'rasa' => 'Brown Sugar',
            'ukuran' => 'Reguler', 'harga' => 20000,
        ]);

        $manis = Kategori::create(['nama' => 'Dessert & Cookies']);
        $this->dessert = Menu::create([
            'kategori_id' => $manis->id, 'nama' => 'Soy Milk Pudding', 'ukuran' => '', 'harga' => 15000,
        ]);

        $this->kasir = User::factory()->create(['role' => 'kasir', 'nama' => 'Adrian']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    /**
     * @param  array<int, array{0: Menu, 1: int}>  $items
     */
    private function transaksiLunas(array $items, string $metode = 'cash', ?array $customer = null): int
    {
        Sanctum::actingAs($this->kasir);

        $id = $this->postJson('/api/transaksi', $customer === null ? [] : ['customer' => $customer])
            ->assertCreated()->json('data.id');

        foreach ($items as [$menu, $qty]) {
            $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $menu->id, 'qty' => $qty])->assertOk();
        }

        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => $metode])->assertOk();

        return $id;
    }

    public function test_transaksi_lunas_langsung_muncul_di_ringkasan_dashboard(): void
    {
        $this->transaksiLunas([[$this->reguler, 2]]); // 2 x 20.000

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/ringkasan')
            ->assertOk()
            ->assertJsonPath('data_tersedia', true)
            ->assertJsonPath('data.total_revenue', 40000)
            ->assertJsonPath('data.total_qty', 2);

        // Satu baris laporan per baris item, ber-prefix TRX-.
        $baris = LaporanTransaksi::first();
        $this->assertStringStartsWith(LaporanTransaksi::PREFIX_POS, $baris->kode);
        $this->assertSame('Original', $baris->nama_produk);
        $this->assertSame('Reguler', $baris->ukuran);
        // Dinormalkan ke kosakata laporan. `metode_bayar` transaksinya tetap
        // 'cash', tapi kolom `platform` memakai istilah yang sama dengan 345
        // baris CSV historis, kalau tidak, filter platform di dashboard
        // menampilkan 'Tunai' dan 'cash' sebagai dua entri berbeda.
        $this->assertSame('Tunai', $baris->platform);
        $this->assertSame(40000, $baris->total);
    }

    public function test_bayar_dua_kali_tidak_menggandakan_omzet(): void
    {
        $id = $this->transaksiLunas([[$this->reguler, 1]]);

        // Panggilan kedua ditolak karena transaksinya sudah lunas, tapi yang
        // dijaga di sini adalah proyeksinya: `updateOrCreate` berkunci `kode`
        // membuat pengulangan tidak pernah menambah baris.
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertStatus(409);

        app(LaporanProjector::class)->sinkronkan(Transaksi::find($id));
        app(LaporanProjector::class)->sinkronkan(Transaksi::find($id));

        $this->assertSame(1, LaporanTransaksi::count());
        $this->assertSame(20000, (int) LaporanTransaksi::sum('total'));
    }

    public function test_item_reward_terproyeksi_dengan_total_nol_tapi_qty_tetap_terhitung(): void
    {
        PengaturanLoyalty::query()->delete();

        $customer = ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'];

        Sanctum::actingAs($this->kasir);
        $id = $this->postJson('/api/transaksi', ['customer' => $customer])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->reguler->id, 'qty' => 1])->assertOk();

        // Saldo dicukupkan supaya reward gratis menu bisa ditukar.
        Loyalty::query()->update(['poin' => 5000, 'poin_kedaluwarsa_pada' => null]);

        $this->postJson("/api/transaksi/{$id}/redeem-poin", ['kode_redeem' => 'gratis_original'])
            ->assertOk();
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();

        $reward = LaporanTransaksi::where('total', 0)->first();
        $this->assertNotNull($reward, 'Item reward harus ikut diproyeksikan.');
        $this->assertSame(1, $reward->qty, 'Qty terjual harus jujur, minuman gratis tetap mengonsumsi stok.');

        // Total qty ikut menghitung reward, total revenue tidak.
        Sanctum::actingAs($this->manager());
        $this->getJson('/api/dashboard/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.total_qty', 2)
            ->assertJsonPath('data.total_revenue', 20000);
    }

    public function test_poin_dibagi_rata_ke_item_dan_sisanya_di_item_terakhir(): void
    {
        // 3 x 20.000 = 60.000 → 60 poin pada rate bawaan Rp 1.000/poin,
        // dibagi ke 2 baris item.
        $this->transaksiLunas(
            [[$this->reguler, 3], [$this->dessert, 1]],
            'cash',
            ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'],
        );

        $total = (int) LaporanTransaksi::sum('poin_loyalty');
        $poinTransaksi = (int) Transaksi::first()->point_earned;

        // Yang dijaga: penjumlahan poin di laporan tetap sama dengan poin yang
        // benar-benar diberikan, tidak hilang di pembulatan pembagian.
        $this->assertSame($poinTransaksi, $total);
        $this->assertSame(2, LaporanTransaksi::count());
    }

    public function test_proyeksi_ulang_tidak_menyentuh_baris_csv_historis(): void
    {
        $historis = LaporanTransaksi::create([
            'kode' => 'TR-JUN2026-0001', 'tanggal' => '2026-06-01', 'platform' => 'QRIS',
            'nama_pelanggan' => 'Annisa', 'nama_produk' => 'Soya Honey Lemon',
            'ukuran' => 'Reguler', 'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000,
            'poin_loyalty' => 20,
        ]);

        $this->transaksiLunas([[$this->reguler, 1]]);
        $this->assertSame(2, LaporanTransaksi::count());

        $this->artisan('laporan:proyeksi-ulang')->assertSuccessful();

        $this->assertSame(2, LaporanTransaksi::count());
        $this->assertDatabaseHas('laporan_transaksi', [
            'kode' => 'TR-JUN2026-0001',
            'kasir_user_id' => null, // data lama memang tidak merekam kasir
            'total' => 20000,
        ]);
        $this->assertSame($historis->id, LaporanTransaksi::where('kode', 'TR-JUN2026-0001')->first()->id);
    }

    public function test_proyeksi_ulang_idempoten_dan_aman_berkali_kali(): void
    {
        $this->transaksiLunas([[$this->reguler, 2]]);

        $this->artisan('laporan:proyeksi-ulang')->assertSuccessful();
        $this->artisan('laporan:proyeksi-ulang')->assertSuccessful();

        $this->assertSame(1, LaporanTransaksi::count());
        $this->assertSame(40000, (int) LaporanTransaksi::sum('total'));
    }

    public function test_sembunyikan_tidak_diketahui_membuang_bucket_null(): void
    {
        // Baris berlabel dan baris tanpa platform hidup berdampingan.
        LaporanTransaksi::create([
            'kode' => 'TR-A', 'tanggal' => '2026-06-01', 'platform' => 'QRIS',
            'nama_produk' => 'Original', 'ukuran' => 'Reguler',
            'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000,
        ]);
        LaporanTransaksi::create([
            'kode' => 'TR-B', 'tanggal' => '2026-06-01', 'platform' => null,
            'nama_produk' => 'Original', 'ukuran' => null,
            'qty' => 1, 'harga_satuan' => 15000, 'total' => 15000,
        ]);

        Sanctum::actingAs($this->manager());

        // Default: bucket kosong tetap ada, TAPI berlabel, bukan key kosong
        // yang dirender chart sebagai batang tak bernama.
        $respon = $this->getJson('/api/dashboard/platform')->assertOk();
        $platform = array_column($respon->json('data'), 'platform');
        $this->assertContains(LaporanQuery::LABEL_TIDAK_DIKETAHUI, $platform);
        $this->assertContains('QRIS', $platform);

        // Disembunyikan: bucket itu hilang dari hasil DAN dari totalnya.
        $respon = $this->getJson('/api/dashboard/platform?sembunyikan_tidak_diketahui=true')->assertOk();
        $data = $respon->json('data');
        $this->assertSame(['QRIS'], array_column($data, 'platform'));
        $this->assertSame(20000, array_sum(array_column($data, 'revenue')));

        // Berlaku juga di revenue per ukuran.
        $ukuran = array_column($this->getJson('/api/dashboard/revenue-ukuran')->json('data'), 'ukuran');
        $this->assertContains(LaporanQuery::LABEL_TIDAK_DIKETAHUI, $ukuran);

        $ukuran = array_column(
            $this->getJson('/api/dashboard/revenue-ukuran?sembunyikan_tidak_diketahui=true')->json('data'),
            'ukuran',
        );
        $this->assertNotContains(LaporanQuery::LABEL_TIDAK_DIKETAHUI, $ukuran);
    }

    public function test_periode_label_dan_hari_berbahasa_indonesia(): void
    {
        // 2026-07-27 adalah hari Senin.
        foreach (['2026-07-27', '2026-07-29'] as $i => $tanggal) {
            LaporanTransaksi::create([
                'kode' => 'TR-'.$i, 'tanggal' => $tanggal, 'platform' => 'Cash',
                'nama_produk' => 'Original', 'ukuran' => 'Reguler',
                'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000,
            ]);
        }

        Sanctum::actingAs($this->manager());

        $data = $this->getJson('/api/dashboard/time-series?grain=harian')->assertOk()->json('data');

        // `periode` mentah TIDAK dihapus, dipakai sorting & key stabil.
        $this->assertSame('2026-07-27', $data[0]['periode']);
        $this->assertSame('Sen, 27 Jul', $data[0]['periode_label']);
        $this->assertSame('Senin', $data[0]['hari']);

        // Grain lain: label ada, `hari` null karena bucketnya bukan satu hari.
        $bulanan = $this->getJson('/api/dashboard/time-series?grain=bulanan')->assertOk()->json('data');
        $this->assertSame('Juli 2026', $bulanan[0]['periode_label']);
        $this->assertNull($bulanan[0]['hari']);

        $tahunan = $this->getJson('/api/dashboard/time-series?grain=tahunan')->assertOk()->json('data');
        $this->assertSame('2026', $tahunan[0]['periode_label']);
        $this->assertNull($tahunan[0]['hari']);
    }

    public function test_hari_tanpa_transaksi_tetap_muncul_sebagai_bucket_nol(): void
    {
        foreach (['2026-07-27', '2026-07-30'] as $i => $tanggal) {
            LaporanTransaksi::create([
                'kode' => 'TR-'.$i, 'tanggal' => $tanggal, 'platform' => 'Cash',
                'nama_produk' => 'Original', 'ukuran' => 'Reguler',
                'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000,
            ]);
        }

        Sanctum::actingAs($this->manager());

        $data = $this->getJson('/api/dashboard/time-series?grain=harian&start=2026-07-27&end=2026-07-30')
            ->assertOk()->json('data');

        // 27, 28, 29, 30, 28 & 29 tidak punya transaksi tapi tetap keluar,
        // supaya grafik tidak menyambung dua titik berjauhan dan membaca
        // naik-turun yang tidak pernah terjadi.
        $this->assertCount(4, $data);
        $this->assertSame(['2026-07-27', '2026-07-28', '2026-07-29', '2026-07-30'], array_column($data, 'periode'));
        $this->assertSame(20000, $data[0]['revenue']);
        $this->assertSame(0, $data[1]['revenue']);
        $this->assertSame(0, $data[1]['transaksi']);
        $this->assertSame('Selasa', $data[1]['hari']);
        $this->assertSame(20000, $data[3]['revenue']);
    }

    /**
     * REGRESSION T5 di layer laporan: tanggal proyeksi diambil dari
     * `waktu_lunas` dalam WIB. Transaksi 23.30 WIB tanggal 5 harus masuk bucket
     * tanggal 5, bukan tanggal 6.
     */
    public function test_tanggal_proyeksi_memakai_wib(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 23:30', WaktuToko::ZONA));
        $this->transaksiLunas([[$this->reguler, 1]]);

        // Sisi pagi: 06.00 WIB tanggal 6 tersimpan sebagai 23.00 UTC tanggal 5,
        // tapi menurut WIB ia penjualan tanggal 6.
        $this->travelTo(Carbon::parse('2026-08-06 06:00', WaktuToko::ZONA));
        $this->transaksiLunas([[$this->reguler, 1]]);

        $this->travelBack();

        $this->assertSame(
            ['2026-08-05', '2026-08-06'],
            LaporanTransaksi::orderBy('tanggal')->pluck('tanggal')->map->format('Y-m-d')->all(),
        );

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/time-series?grain=harian&start=2026-08-05&end=2026-08-05')
            ->assertOk()
            ->assertJsonPath('data.0.periode', '2026-08-05')
            ->assertJsonPath('data.0.revenue', 20000);
    }

    public function test_invarian_baris_trx_selalu_punya_kasir(): void
    {
        $this->transaksiLunas([[$this->reguler, 1]]);
        $this->transaksiLunas([[$this->dessert, 2]], 'qris');

        $tanpaKasir = LaporanTransaksi::query()
            ->where('kode', 'like', LaporanTransaksi::PREFIX_POS.'%')
            ->whereNull('kasir_user_id')
            ->count();

        $this->assertSame(0, $tanpaKasir, 'Baris berawalan TRX- tanpa kasir berarti ada yang bocor di proyeksi.');

        $baris = LaporanTransaksi::first();
        $this->assertSame($this->kasir->id, $baris->kasir_user_id);
        $this->assertSame('Adrian', $baris->kasir_nama);
    }

    public function test_transaksi_pending_tidak_diproyeksikan(): void
    {
        Sanctum::actingAs($this->kasir);

        $id = $this->postJson('/api/transaksi', [])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->reguler->id, 'qty' => 1])->assertOk();

        // Belum jadi penjualan, jadi belum boleh ada di laporan.
        $this->assertSame(0, LaporanTransaksi::count());
    }
}
