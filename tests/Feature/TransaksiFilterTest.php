<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\LaporanTransaksi;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Models\User;
use App\Support\WaktuToko;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Filter, urutan, dan ringkasan daftar transaksi manager (Blok A2 + A3),
 * beserta kolom `sumber` yang membedakan pesanan kasir vs SoyaScan (Blok A1).
 *
 * Termasuk regression test zona waktu (T5): sebelum perbaikan,
 * `whereDate('created_at', ...)` memakai `config('app.timezone')` = UTC,
 * sehingga transaksi PAGI (sebelum 07.00 WIB) jatuh ke tanggal sebelumnya dan
 * tidak muncul saat manager membuka "hari ini".
 */
class TransaksiFilterTest extends TestCase
{
    use RefreshDatabase;

    private Menu $reguler;

    private Menu $large;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Signature']);
        $this->reguler = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 17000,
        ]);
        $this->large = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Taro Thanos', 'ukuran' => 'Large', 'harga' => 30000,
        ]);

        $this->kasir = User::factory()->create(['role' => 'kasir', 'nama' => 'Adrian']);
        Sanctum::actingAs($this->kasir);
    }

    /**
     * Transaksi kasir yang langsung dilunasi. Mengembalikan id-nya.
     *
     * @param  array<string, mixed>|null  $customer
     */
    private function transaksiLunas(Menu $menu, int $qty = 1, string $metode = 'cash', ?array $customer = null): int
    {
        $id = $this->postJson('/api/transaksi', $customer === null ? [] : ['customer' => $customer])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $menu->id, 'qty' => $qty])->assertOk();
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => $metode])->assertOk();

        return $id;
    }

    private function transaksiPending(): int
    {
        $id = $this->postJson('/api/transaksi', [])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->reguler->id, 'qty' => 1])->assertOk();

        return $id;
    }

    public function test_sumber_terisi_benar_dari_kasir_maupun_soyascan(): void
    {
        $this->transaksiLunas($this->reguler);

        $this->postJson('/api/order', [
            'nama' => 'Budi',
            'nomor_wa' => '0812-3456-7890',
            'items' => [['menu_id' => $this->reguler->id, 'qty' => 1]],
        ])->assertCreated();

        // Asal pesanan dibaca dari kolom `sumber`, BUKAN dari huruf kodenya.
        // Kasir dan SoyaScan sekarang berbagi satu seri kode mingguan, jadi
        // `LIKE '#K%'` sudah tidak berarti apa-apa lagi.
        $this->assertSame(1, Transaksi::where('sumber', 'kasir')->count());
        $this->assertSame(1, Transaksi::where('sumber', 'self_order')->count());

        // Keduanya memang memperoleh kode dari seri yang sama, berurutan.
        $this->assertSame(
            ['#A00', '#A01'],
            Transaksi::orderBy('id')->pluck('kode_pesanan')->all(),
        );

        // Label siap tampil ikut dikirim supaya frontend tidak memetakan sendiri.
        $respon = $this->getJson('/api/transaksi?sumber=self_order')->assertOk();
        $respon->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sumber', 'self_order')
            ->assertJsonPath('data.0.sumber_label', 'SoyaScan');

        $this->getJson('/api/transaksi?sumber=kasir')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sumber_label', 'Kasir');
    }

    /**
     * Backfill migrasi: baris yang dibuat SEBELUM kolom `transaksi.sumber` ada
     * harus menurunkan nilainya dari item pertamanya, dan transaksi tanpa item
     * jatuh ke `'kasir'`.
     *
     * Kolomnya dibuang dulu supaya kondisi pra-migrasi benar-benar
     * direproduksi, lalu `up()` migrasi itu dijalankan apa adanya, bukan
     * menyalin ulang SQL backfill-nya ke dalam test, yang justru akan lulus
     * meski migrasinya salah.
     */
    public function test_backfill_sumber_menurunkan_dari_item_pertama(): void
    {
        $migrasi = require database_path('migrations/2026_08_01_000001_add_sumber_to_transaksi_table.php');

        Schema::table('transaksi', fn (Blueprint $table) => $table->dropColumn('sumber'));

        $dariSoyaScan = DB::table('transaksi')->insertGetId([
            'user_id' => null, 'kode_pesanan' => '#A00', 'total' => 17000,
            'status' => 'lunas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('detail_transaksi')->insert([
            'transaksi_id' => $dariSoyaScan, 'menu_id' => $this->reguler->id, 'qty' => 1,
            'harga_satuan' => 17000, 'subtotal' => 17000, 'sumber' => 'self_order',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $tanpaItem = DB::table('transaksi')->insertGetId([
            'user_id' => $this->kasir->id, 'kode_pesanan' => '#Y00', 'total' => 0,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migrasi->up();

        $this->assertSame('self_order', DB::table('transaksi')->find($dariSoyaScan)->sumber);
        $this->assertSame('kasir', DB::table('transaksi')->find($tanpaItem)->sumber);
    }

    public function test_filter_rentang_tanggal_inklusif_di_kedua_ujung(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 10:00', WaktuToko::ZONA));
        $this->transaksiLunas($this->reguler);

        $this->travelTo(Carbon::parse('2026-08-03 10:00', WaktuToko::ZONA));
        $this->transaksiLunas($this->reguler);

        $this->travelTo(Carbon::parse('2026-08-05 10:00', WaktuToko::ZONA));
        $this->transaksiLunas($this->reguler);

        $this->getJson('/api/transaksi?tanggal_mulai=2026-08-01&tanggal_selesai=2026-08-05')
            ->assertOk()->assertJsonPath('meta.jumlah_transaksi', 3);

        // Kedua ujung ikut terhitung, 01 dan 03 masuk, 05 tidak.
        $this->getJson('/api/transaksi?tanggal_mulai=2026-08-01&tanggal_selesai=2026-08-03')
            ->assertOk()->assertJsonPath('meta.jumlah_transaksi', 2);

        $this->getJson('/api/transaksi?tanggal_mulai=2026-08-03&tanggal_selesai=2026-08-03')
            ->assertOk()->assertJsonPath('meta.jumlah_transaksi', 1);

        $this->travelBack();
    }

    public function test_tanggal_mulai_lebih_besar_dari_selesai_ditolak_422(): void
    {
        $this->getJson('/api/transaksi?tanggal_mulai=2026-08-10&tanggal_selesai=2026-08-01')
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_setiap_preset_menghasilkan_rentang_yang_benar(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15 10:00', WaktuToko::ZONA));

        // hari ini, kemarin, 6 hari lalu, 20 hari lalu, dan bulan lalu.
        foreach (['2026-08-15', '2026-08-14', '2026-08-10', '2026-07-27', '2026-07-05'] as $tanggal) {
            $this->travelTo(Carbon::parse($tanggal.' 10:00', WaktuToko::ZONA));
            $this->transaksiLunas($this->reguler);
        }

        $this->travelTo(Carbon::parse('2026-08-15 12:00', WaktuToko::ZONA));

        $harapan = [
            'hari_ini' => 1,   // 15 Agu
            'kemarin' => 1,    // 14 Agu
            '7_hari' => 3,     // 10, 14, 15 Agu (hari ini termasuk salah satunya)
            '30_hari' => 4,    // + 27 Jul
            'bulan_ini' => 3,  // 10, 14, 15 Agu
        ];

        foreach ($harapan as $preset => $jumlah) {
            $this->getJson('/api/transaksi?preset='.$preset)
                ->assertOk()
                ->assertJsonPath('meta.jumlah_transaksi', $jumlah);
        }

        $this->getJson('/api/transaksi?preset=minggu_depan')->assertStatus(422);

        $this->travelBack();
    }

    public function test_batas_eksplisit_menang_atas_preset(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15 10:00', WaktuToko::ZONA));
        $this->transaksiLunas($this->reguler);

        $this->travelTo(Carbon::parse('2026-08-01 10:00', WaktuToko::ZONA));
        $this->transaksiLunas($this->reguler);

        $this->travelTo(Carbon::parse('2026-08-15 12:00', WaktuToko::ZONA));

        // preset=hari_ini akan menghasilkan 1, tapi batas eksplisit menang.
        $this->getJson('/api/transaksi?preset=hari_ini&tanggal_mulai=2026-08-01&tanggal_selesai=2026-08-15')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 2);

        $this->travelBack();
    }

    public function test_filter_gabungan_status_sumber_dan_cari_mempersempit_dengan_benar(): void
    {
        $this->transaksiLunas($this->reguler, 1, 'cash', ['nama' => 'Budi Santoso', 'no_wa' => '0812-3456-7890']);
        $this->transaksiLunas($this->large, 1, 'qris', ['nama' => 'Citra', 'no_wa' => '0813-1111-2222']);
        $this->transaksiPending();

        // status saja
        $this->getJson('/api/transaksi?status=lunas')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/transaksi?status=pending')->assertOk()->assertJsonCount(1, 'data');

        // status + sumber + cari nama customer (case-insensitive)
        $this->getJson('/api/transaksi?status=lunas&sumber=kasir&cari=budi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.nama', 'Budi Santoso');

        // cari lewat nomor WA yang diketik dalam ejaan lokal (0812…),
        // sementara yang tersimpan sudah ternormalisasi (62812…)
        $this->getJson('/api/transaksi?cari=0813')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.nama', 'Citra');

        // cari lewat kode pesanan
        $this->getJson('/api/transaksi?cari=%23A00')->assertOk()->assertJsonCount(1, 'data');

        // metode bayar
        $this->getJson('/api/transaksi?metode_bayar=qris')->assertOk()->assertJsonCount(1, 'data');

        // gabungan yang saling menghapus hasil
        $this->getJson('/api/transaksi?status=pending&metode_bayar=qris')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Riwayat transaksi pelanggan harus ketemu baik dari nomor LENGKAP (ejaan apa
     * pun) maupun dari POTONGAN nomor, terutama 4 digit terakhir, yang itulah
     * yang biasanya disebut pelanggan di konter.
     *
     * Yang tersimpan selalu bentuk ternormalisasi `62…`, jadi tanpa
     * NomorWa::kandidatCari() pencarian "0812…" tidak akan pernah cocok.
     */
    public function test_cari_riwayat_dari_nomor_penuh_maupun_4_digit_terakhir(): void
    {
        $this->transaksiLunas($this->reguler, 1, 'cash', ['nama' => 'Budi', 'no_wa' => '0812-3456-7890']);
        $this->transaksiLunas($this->large, 1, 'cash', ['nama' => 'Citra', 'no_wa' => '0857-1111-2222']);

        $tersimpan = Customer::where('nama', 'Budi')->value('no_wa');
        $this->assertSame('6281234567890', $tersimpan);

        // 1) Nomor LENGKAP, tiga ejaan berbeda, semuanya menunjuk Budi.
        foreach (['081234567890', '0812-3456-7890', '+62 812 3456 7890', '6281234567890'] as $ketikan) {
            $this->getJson('/api/transaksi?cari='.urlencode($ketikan))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.customer.nama', 'Budi');
        }

        // 2) EMPAT DIGIT TERAKHIR.
        $this->getJson('/api/transaksi?cari=7890')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.nama', 'Budi');

        $this->getJson('/api/transaksi?cari=2222')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.nama', 'Citra');

        // 3) Potongan tengah tetap jalan.
        $this->getJson('/api/transaksi?cari=3456')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.nama', 'Budi');

        // 4) Ringkasan `meta` ikut menyempit, bukan tetap seluruh tabel.
        $this->getJson('/api/transaksi?cari=7890')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 1)
            ->assertJsonPath('meta.total_omzet', 17000);
    }

    public function test_cari_nama_customer_tidak_bergantung_huruf_besar_kecil(): void
    {
        $this->transaksiLunas($this->reguler, 1, 'cash', ['nama' => 'Budi Santoso', 'no_wa' => '0812-3456-7890']);

        // LIKE case-sensitive di PostgreSQL, tanpa LOWER() di kedua sisi, ini
        // lolos di SQLite tapi gagal di produksi.
        foreach (['budi', 'BUDI', 'Budi', 'sAnToSo'] as $ketikan) {
            $this->getJson('/api/transaksi?cari='.$ketikan)
                ->assertOk()
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_wildcard_like_tidak_bocor_dari_input_cari(): void
    {
        $this->transaksiLunas($this->reguler, 1, 'cash', ['nama' => 'Budi', 'no_wa' => '0812-3456-7890']);

        // '%' harus diperlakukan sebagai karakter biasa, bukan wildcard yang
        // memuntahkan seluruh tabel.
        $this->getJson('/api/transaksi?cari='.urlencode('%'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/transaksi?cari='.urlencode('_'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filter_total_dan_ada_redeem(): void
    {
        $this->transaksiLunas($this->reguler);        // 17.000
        $this->transaksiLunas($this->large);          // 30.000

        $this->getJson('/api/transaksi?total_min=20000')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/transaksi?total_max=20000')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/transaksi?total_min=17000&total_max=30000')->assertOk()->assertJsonCount(2, 'data');

        // total_max < total_min ditolak, tapi total_max SENDIRIAN tetap sah
        $this->getJson('/api/transaksi?total_min=30000&total_max=1000')->assertStatus(422);
        $this->getJson('/api/transaksi?tanggal_selesai=2026-12-31')->assertOk();

        // Belum ada yang redeem
        $this->getJson('/api/transaksi?ada_redeem=true')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/transaksi?ada_redeem=false')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/transaksi?ada_redeem=mungkin')->assertStatus(422);
    }

    public function test_meta_ringkasan_cocok_dengan_hasil_terfilter_bukan_seluruh_tabel(): void
    {
        $this->transaksiLunas($this->reguler, 2);  // 2 x 17.000 = 34.000, qty 2
        $this->transaksiLunas($this->large, 3);    // 3 x 30.000 = 90.000, qty 3

        // Tanpa filter: seluruh tabel.
        $this->getJson('/api/transaksi')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 2)
            ->assertJsonPath('meta.total_omzet', 124000)
            ->assertJsonPath('meta.total_qty', 5);

        // Difilter: angkanya IKUT menyempit, bukan tetap seluruh tabel.
        $this->getJson('/api/transaksi?total_min=50000')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 1)
            ->assertJsonPath('meta.total_omzet', 90000)
            ->assertJsonPath('meta.total_qty', 3);

        // Paginasi bawaan tetap ada dan tidak tertimpa ringkasan.
        $this->getJson('/api/transaksi?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.jumlah_transaksi', 2);
    }

    public function test_urut_terbaru_dan_terlama(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 10:00', WaktuToko::ZONA));
        $pertama = $this->transaksiLunas($this->reguler);

        $this->travelTo(Carbon::parse('2026-08-02 10:00', WaktuToko::ZONA));
        $kedua = $this->transaksiLunas($this->reguler);

        $this->travelBack();

        $this->getJson('/api/transaksi')->assertOk()->assertJsonPath('data.0.id', $kedua);
        $this->getJson('/api/transaksi?urut=terbaru')->assertOk()->assertJsonPath('data.0.id', $kedua);
        $this->getJson('/api/transaksi?urut=terlama')->assertOk()->assertJsonPath('data.0.id', $pertama);

        $this->getJson('/api/transaksi?urut=acak')->assertStatus(422);
    }

    /**
     * REGRESSION T5. `app.timezone` masih UTC, jadi `whereDate('created_at')`
     * memotong hari pada 07.00 WIB. Transaksi pukul 06.00 WIB tanggal 5
     * tersimpan sebagai 23.00 UTC tanggal 4, dan sebelum perbaikan ia hilang
     * dari filter tanggal 5, persis saat kasir shift pagi membuka daftarnya.
     */
    public function test_transaksi_pagi_wib_tetap_masuk_tanggal_hari_itu(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 06:00', WaktuToko::ZONA));
        $pagi = $this->transaksiLunas($this->reguler);

        // Ujung yang lain: 23.30 WIB tanggal 5 tidak boleh bocor ke tanggal 6.
        $this->travelTo(Carbon::parse('2026-08-05 23:30', WaktuToko::ZONA));
        $malam = $this->transaksiLunas($this->large);

        $this->travelBack();

        $this->getJson('/api/transaksi?tanggal=2026-08-05')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 2);

        $this->getJson('/api/transaksi?tanggal=2026-08-04')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 0);

        $this->getJson('/api/transaksi?tanggal=2026-08-06')
            ->assertOk()
            ->assertJsonPath('meta.jumlah_transaksi', 0);

        // Keduanya benar-benar tersimpan pada tanggal UTC yang berbeda,
        // itulah yang membuat test ini bermakna, bukan kebetulan lolos.
        $this->assertSame('2026-08-04', Transaksi::find($pagi)->created_at->utc()->toDateString());
        $this->assertSame('2026-08-05', Transaksi::find($malam)->created_at->utc()->toDateString());
    }

    public function test_kode_pesanan_mingguan_mengikuti_batas_minggu_wib(): void
    {
        // Rabu 06.00 WIB dan Rabu 23.30 WIB masih minggu yang sama, jadi
        // nomornya berlanjut. Batas minggunya WIB, bukan zona server: dengan
        // `app.timezone` = UTC, pesanan Senin dini hari akan terhitung sebagai
        // minggu sebelumnya dan serinya salah mulai.
        $this->travelTo(Carbon::parse('2026-08-05 06:00', WaktuToko::ZONA));
        $this->postJson('/api/transaksi', [])->assertCreated()->assertJsonPath('data.kode_pesanan', '#A00');

        $this->travelTo(Carbon::parse('2026-08-05 23:30', WaktuToko::ZONA));
        $this->postJson('/api/transaksi', [])->assertCreated()->assertJsonPath('data.kode_pesanan', '#A01');

        // Minggu (hari) masih ikut minggu yang sama karena minggunya mulai Senin.
        $this->travelTo(Carbon::parse('2026-08-09 20:00', WaktuToko::ZONA));
        $this->postJson('/api/transaksi', [])->assertCreated()->assertJsonPath('data.kode_pesanan', '#A02');

        // Senin berikutnya, seri mulai ulang.
        $this->travelTo(Carbon::parse('2026-08-10 06:00', WaktuToko::ZONA));
        $this->postJson('/api/transaksi', [])->assertCreated()->assertJsonPath('data.kode_pesanan', '#A00');

        $this->travelBack();
    }

    /**
     * Tiga transaksi impor. `laporan_transaksi.kode` UNIK, jadi satu baris CSV
     * memang satu transaksi berisi satu produk, bukan beberapa item.
     */
    private function buatHistoris(): void
    {
        LaporanTransaksi::insert([
            [
                'kode' => 'TR-JUN2026-0001', 'tanggal' => '2026-06-01', 'platform' => 'QRIS',
                'nama_pelanggan' => 'Annisa', 'nama_produk' => 'Soya Honey Lemon', 'rasa' => 'Honey Lemon',
                'ukuran' => 'Reguler', 'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000, 'poin_loyalty' => 20,
            ],
            [
                'kode' => 'TR-JUN2026-0002', 'tanggal' => '2026-06-02', 'platform' => 'QRIS',
                'nama_pelanggan' => 'Annisa', 'nama_produk' => 'Soya Original', 'rasa' => 'Original',
                'ukuran' => 'Large', 'qty' => 2, 'harga_satuan' => 21000, 'total' => 42000, 'poin_loyalty' => 42,
            ],
            [
                'kode' => 'TR-JUL2026-0003', 'tanggal' => '2026-07-15', 'platform' => 'GrabFood',
                'nama_pelanggan' => 'Bagas', 'nama_produk' => 'Soya Thai Tea', 'rasa' => 'Thai Tea',
                'ukuran' => 'Reguler', 'qty' => 1, 'harga_satuan' => 22000, 'total' => 22000, 'poin_loyalty' => 22,
            ],
        ]);
    }

    public function test_daftar_transaksi_memuat_baris_historis_juni_juli(): void
    {
        $this->buatHistoris();
        $this->transaksiLunas($this->reguler); // satu transaksi POS hari ini

        $data = collect($this->getJson('/api/transaksi')->assertOk()->json('data'))
            ->keyBy('kode_pesanan');

        // Tiga baris impor dan satu baris POS berdampingan di satu daftar.
        $this->assertCount(4, $data);

        $juni = $data['TR-JUN2026-0001'];
        $this->assertTrue($juni['historis']);
        $this->assertSame('lunas', $juni['status']);
        $this->assertSame('Annisa', $juni['customer']['nama']);
        $this->assertSame('QRIS', $juni['sumber_label']);
        $this->assertSame('qris', $juni['metode_bayar']);
        $this->assertSame(20000, $juni['total']);
        $this->assertSame(20, $juni['point_earned']);
        $this->assertCount(1, $juni['items']);
        $this->assertSame('Soya Honey Lemon', $juni['items'][0]['nama_menu']);

        // Platform yang bukan metode bayar tidak dipaksakan jadi metode bayar.
        $this->assertNull($data['TR-JUL2026-0003']['metode_bayar']);
        $this->assertSame('GrabFood', $data['TR-JUL2026-0003']['sumber_label']);

        // Baris POS tetap punya id dan tidak ditandai historis, itulah yang
        // dipakai frontend untuk memutuskan tombol aksinya hidup atau mati.
        $pos = $data->firstWhere('historis', false);
        $this->assertNotNull($pos['id']);
    }

    public function test_baris_historis_tidak_punya_id_sehingga_aksinya_mati(): void
    {
        $this->buatHistoris();

        $historis = collect($this->getJson('/api/transaksi')->json('data'))
            ->where('historis', true);

        $this->assertNotEmpty($historis);
        foreach ($historis as $baris) {
            // Tanpa id, Detail dan Batalkan memang tidak punya sasaran.
            $this->assertNull($baris['id']);
            $this->assertNull($baris['kasir_pembuat']);
            $this->assertSame(0, $baris['poin_ditukar']);
        }
    }

    public function test_ringkasan_menjumlahkan_transaksi_pos_dan_historis(): void
    {
        $this->buatHistoris();
        $this->transaksiLunas($this->reguler); // 17.000, 1 item

        $meta = $this->getJson('/api/transaksi')->assertOk()->json('meta');

        // 3 historis + 1 POS
        $this->assertSame(4, $meta['jumlah_transaksi']);
        // 20.000 + 42.000 + 22.000 + 17.000
        $this->assertSame(101000, $meta['total_omzet']);
        // 1 + 2 + 1 pcs historis, 1 pcs POS
        $this->assertSame(5, $meta['total_qty']);
    }

    public function test_filter_tanggal_dan_sumber_berlaku_untuk_baris_historis(): void
    {
        $this->buatHistoris();
        $this->transaksiLunas($this->reguler);

        // Rentang Juni saja: dua transaksi impor yang lolos, POS hari ini tidak.
        $juni = $this->getJson('/api/transaksi?tanggal_mulai=2026-06-01&tanggal_selesai=2026-06-30')
            ->assertOk()->json('data');
        $this->assertCount(2, $juni);
        $this->assertSame(
            ['TR-JUN2026-0001', 'TR-JUN2026-0002'],
            collect($juni)->pluck('kode_pesanan')->sort()->values()->all(),
        );

        // `sumber=historis` menyisakan baris impor saja, transaksi POS hilang.
        $historisSaja = $this->getJson('/api/transaksi?sumber=historis')->assertOk()->json('data');
        $this->assertCount(3, $historisSaja);
        $this->assertSame([true], array_unique(array_column($historisSaja, 'historis')));

        // Sebaliknya, `sumber=kasir` tidak boleh kemasukan baris impor.
        $kasirSaja = $this->getJson('/api/transaksi?sumber=kasir')->assertOk()->json('data');
        $this->assertSame([false], array_column($kasirSaja, 'historis'));
    }

    public function test_filter_status_selain_lunas_membuang_seluruh_baris_historis(): void
    {
        $this->buatHistoris();
        $this->transaksiPending();

        // Data impor semuanya sudah lunas, jadi filter `pending` tidak boleh
        // menampilkan satu pun baris historis.
        $pending = $this->getJson('/api/transaksi?status=pending')->assertOk()->json('data');
        $this->assertNotEmpty($pending);
        $this->assertSame([false], array_unique(array_column($pending, 'historis')));
    }

    public function test_cari_menemukan_transaksi_historis_lewat_kode_dan_nama(): void
    {
        $this->buatHistoris();

        $lewatKode = $this->getJson('/api/transaksi?cari=TR-JUL2026')->assertOk()->json('data');
        $this->assertCount(1, $lewatKode);
        $this->assertSame('Bagas', $lewatKode[0]['customer']['nama']);

        // Huruf kecil pun ketemu, sama seperti pencarian pada daftar POS.
        $lewatNama = $this->getJson('/api/transaksi?cari=annisa')->assertOk()->json('data');
        $this->assertCount(2, $lewatNama);
        $this->assertSame(
            ['TR-JUN2026-0001', 'TR-JUN2026-0002'],
            collect($lewatNama)->pluck('kode_pesanan')->sort()->values()->all(),
        );
    }

    public function test_baris_proyeksi_pos_tidak_tampil_dua_kali(): void
    {
        // Transaksi POS yang lunas ikut diproyeksikan ke laporan_transaksi
        // dengan kode berawalan TRX-. Kalau baris proyeksi itu ikut dibaca
        // sebagai "historis", satu transaksi akan muncul dua kali di daftar.
        $this->transaksiLunas($this->reguler);

        $this->assertGreaterThan(0, LaporanTransaksi::where('kode', 'like', 'TRX-%')->count());

        $data = $this->getJson('/api/transaksi')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertFalse($data[0]['historis']);
    }

    public function test_parameter_tanggal_lama_tetap_didukung(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 10:00', WaktuToko::ZONA));
        $this->transaksiLunas($this->reguler);
        $this->travelBack();

        $this->getJson('/api/transaksi?tanggal=2026-08-05')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
