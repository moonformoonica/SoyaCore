<?php

namespace Tests\Feature;

use App\Exports\LaporanExport;
use App\Models\LaporanRevenueUkuran;
use App\Models\LaporanTransaksi;
use App\Models\User;
use App\Services\LaporanQuery;
use App\Services\RekapKasirHarian;
use Database\Seeders\LaporanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DashboardLaporanTest extends TestCase
{
    use RefreshDatabase;

    private const FULL_REVENUE = 26257000;

    /**
     * Revenue per ukuran hanya mencakup minuman, dessert & cookies
     * (Cup/Pack) sengaja dikecualikan, jadi lebih kecil dari FULL_REVENUE.
     */
    private const MINUMAN_REVENUE = 21192000;

    private const NON_MINUMAN_REVENUE = 5065000;

    private const FULL_ROWS = 882;

    /**
     * `Rekap Kasir` diletakkan setelah `Ringkasan` supaya terlihat lebih dulu
     * daripada sheet detail, itu yang dibaca manager.
     */
    private const SHEET_LENGKAP = [
        'Ringkasan', 'Rekap Kasir', 'Detail Transaksi', 'Revenue per Ukuran',
        'Time Series', 'RFM Pelanggan', 'Rekomendasi Switch',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LaporanSeeder::class);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function kasir(): User
    {
        return User::factory()->create(['role' => 'kasir']);
    }

    public function test_meta_melaporkan_min_max_dan_total_baris_secara_live(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/meta')
            ->assertOk()
            ->assertJsonPath('tanggal_min', '2026-06-01')
            ->assertJsonPath('tanggal_max', '2026-07-30')
            ->assertJsonPath('total_baris', self::FULL_ROWS)
            ->assertJsonCount(8, 'ukuran')
            ->assertJsonCount(6, 'platform')
            ->assertJsonCount(4, 'segmen');
    }

    public function test_ringkasan_window_penuh(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/ringkasan')
            ->assertOk()
            ->assertJsonPath('data_tersedia', true)
            ->assertJsonPath('data.total_revenue', self::FULL_REVENUE)
            ->assertJsonPath('data.total_transaksi', self::FULL_ROWS)
            ->assertJsonPath('data.total_qty', 1078);
    }

    public function test_revenue_ukuran_mereproduksi_fixture_persis(): void
    {
        Sanctum::actingAs($this->manager());

        $expected = LaporanRevenueUkuran::orderByDesc('total_revenue')->get()
            ->map(fn ($r) => [
                'ukuran' => $r->ukuran,
                'jumlah_terjual' => $r->jumlah_terjual,
                'total_revenue' => $r->total_revenue,
                'jumlah_transaksi' => $r->jumlah_transaksi,
                'rata_rata_transaksi' => $r->rata_rata_transaksi,
            ])->all();

        $response = $this->getJson('/api/dashboard/revenue-ukuran')->assertOk();

        $this->assertSame($expected, $response->json('data'));
        $this->assertSame(self::MINUMAN_REVENUE, array_sum(array_column($response->json('data'), 'total_revenue')));

        // Cakupan minuman-saja harus dijelaskan ke frontend, bukan diam-diam.
        $this->assertNotNull($response->json('catatan'));

        // Selisih terhadap /ringkasan memang sebesar revenue dessert & cookies.
        $this->assertSame(self::FULL_REVENUE - self::MINUMAN_REVENUE, self::NON_MINUMAN_REVENUE);
        $this->assertEmpty(array_intersect(
            ['Cup', 'Pack'],
            array_column($response->json('data'), 'ukuran'),
        ));
    }

    public function test_time_series_bulanan_dua_bucket(): void
    {
        Sanctum::actingAs($this->manager());

        $data = $this->getJson('/api/dashboard/time-series?grain=bulanan')
            ->assertOk()
            ->assertJsonPath('periode.grain', 'bulanan')
            ->json('data');

        $this->assertCount(2, $data);
        $this->assertSame(['2026-06', '2026-07'], array_column($data, 'periode'));
        $this->assertSame(self::FULL_REVENUE, array_sum(array_column($data, 'revenue')));
    }

    public function test_produk_terlaris_dan_platform_dan_loyalty_terisi(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/produk-terlaris?by=revenue&limit=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data' => [['nama_produk', 'rasa', 'qty', 'revenue', 'transaksi']]]);

        $this->getJson('/api/dashboard/platform')
            ->assertOk()
            ->assertJsonStructure(['data' => [['platform', 'transaksi', 'revenue', 'qty']]]);

        $this->getJson('/api/dashboard/loyalty?limit=3')
            ->assertOk()
            ->assertJsonPath('data_tersedia', true)
            ->assertJsonCount(3, 'data.top_pelanggan');
    }

    public function test_rfm_dihitung_dari_laporan_transaksi_bukan_snapshot(): void
    {
        Sanctum::actingAs($this->manager());

        $respon = $this->getJson('/api/dashboard/rfm')
            ->assertOk()
            ->assertJsonStructure(['ringkasan_segmen', 'data'])
            ->assertJsonCount(345, 'data');

        // Label periode ikut rentang data yang benar-benar ada, bukan teks
        // yang dipatok di kode.
        $this->assertSame('1 Jun 2026 - 30 Jul 2026', $respon->json('periode_label'));

        // Angka yang bisa direproduksi persis dari CSV Kamila: satu pelanggan
        // yang belanja sekali, dan seorang pelanggan sering yang sudah lama
        // tidak datang.
        $baris = collect($respon->json('data'))->keyBy('nama_pelanggan');

        $this->assertSame(1, $baris['Abi']['frequency']);
        $this->assertSame(15000, $baris['Abi']['monetary']);
        $this->assertSame('Pelanggan Baru', $baris['Abi']['segmen']);

        $this->assertSame(2, $baris['Abdullah']['frequency']);
        $this->assertSame(119000, $baris['Abdullah']['monetary']);

        // Recency memakai acuan hari SETELAH transaksi terakhir di data,
        // sehingga pelanggan di tanggal terbaru bernilai 1, bukan 0.
        $this->assertSame(1, $baris->min('recency'));
    }

    public function test_rfm_ikut_berubah_saat_ada_transaksi_baru_selesai(): void
    {
        Sanctum::actingAs($this->manager());

        $sebelum = collect($this->getJson('/api/dashboard/rfm')->json('data'))
            ->keyBy('nama_pelanggan');

        $this->assertArrayNotHasKey('Pelanggan Baru Sekali', $sebelum->all());

        // Satu baris proyeksi POS, bentuk yang sama dengan yang ditulis
        // LaporanProjector saat kasir menandai lunas.
        \App\Models\LaporanTransaksi::create([
            'kode' => \App\Models\LaporanTransaksi::PREFIX_POS.'900-1',
            'tanggal' => '2026-07-30',
            'platform' => 'cash',
            'nama_pelanggan' => 'Pelanggan Baru Sekali',
            'nama_produk' => 'Original',
            'ukuran' => 'Reguler',
            'qty' => 1,
            'harga_satuan' => 17000,
            'total' => 17000,
            'poin_loyalty' => 17,
        ]);

        $sesudah = collect($this->getJson('/api/dashboard/rfm')->json('data'))
            ->keyBy('nama_pelanggan');

        $this->assertArrayHasKey('Pelanggan Baru Sekali', $sesudah->all());
        $this->assertSame(1, $sesudah['Pelanggan Baru Sekali']['frequency']);
        $this->assertSame(17000, $sesudah['Pelanggan Baru Sekali']['monetary']);
        $this->assertSame('Pelanggan Baru', $sesudah['Pelanggan Baru Sekali']['segmen']);
    }

    public function test_switch_statis_dengan_periode_label(): void
    {
        Sanctum::actingAs($this->manager());

        // Penamaan segmen berubah di data revisi Juni-Juli 2026:
        // "Pelanggan Loyal" -> "Loyal", "Pelanggan Potensial" -> "Potensial",
        // "Hampir Hilang" diganti "Pelanggan Baru".
        $respon = $this->getJson('/api/dashboard/rfm?segmen=Loyal')->assertOk();

        $segmen = array_column($respon->json('data'), 'segmen');
        $this->assertNotEmpty($segmen);
        $this->assertSame(['Loyal'], array_unique($segmen));

        // Ringkasan segmen dihitung dari SELURUH pelanggan, tidak ikut
        // tersaring; kalau ikut, donut chart-nya berubah jadi satu potong.
        $this->assertSame(345, array_sum($respon->json('ringkasan_segmen')));

        // Switch kini DIHITUNG dari laporan_transaksi, bukan dibaca dari
        // snapshot 35 baris. Aturannya diterapkan rata ke semua pelanggan
        // sehingga daftarnya lebih panjang; yang dijaga di sini adalah
        // bentuk dan isinya masuk akal, bukan jumlah baris yang kebetulan.
        $respon = $this->getJson('/api/dashboard/switch')
            ->assertOk()
            ->assertJsonPath('periode_label', '1 Jun 2026 - 30 Jul 2026');

        $data = $respon->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $baris) {
            // Yang sudah pernah beli botol tidak perlu ditawari beralih.
            $this->assertSame(0, $baris['beli_botol']);
            $this->assertContains($baris['ukuran_saat_ini'], ['Reguler', 'Large']);
            $this->assertStringStartsWith('Tawarkan', $baris['rekomendasi']);
        }

        // Terurut total belanja menurun.
        $belanja = array_column($data, 'total_belanja');
        $urut = $belanja;
        rsort($urut);
        $this->assertSame($urut, $belanja);

        // Pencarian menyaring berdasarkan teks rekomendasi.
        $hanyaBotol = $this->getJson('/api/dashboard/switch?rekomendasi=Botol')->assertOk()->json('data');
        $this->assertNotEmpty($hanyaBotol);
        foreach ($hanyaBotol as $baris) {
            $this->assertStringContainsString('Botol', $baris['rekomendasi']);
        }
    }

    public function test_switch_ikut_bergerak_saat_ada_transaksi_baru(): void
    {
        Sanctum::actingAs($this->manager());

        $sebelum = collect($this->getJson('/api/dashboard/switch')->json('data'))
            ->keyBy('nama_pelanggan');

        // Pelanggan baru yang langsung membeli 3 gelas Reguler dalam satu
        // kunjungan: memenuhi ambang pcs sekaligus tergolong beli banyak.
        LaporanTransaksi::create([
            'kode' => 'TRX-9001-1', 'tanggal' => '2026-07-30', 'platform' => 'cash',
            'nama_pelanggan' => 'Pelanggan Borong', 'nama_produk' => 'Soya Original',
            'ukuran' => 'Reguler', 'qty' => 3, 'harga_satuan' => 17000, 'total' => 51000,
            'poin_loyalty' => 51,
        ]);

        $sesudah = collect($this->getJson('/api/dashboard/switch')->json('data'))
            ->keyBy('nama_pelanggan');

        $this->assertArrayNotHasKey('Pelanggan Borong', $sebelum->all());
        $this->assertArrayHasKey('Pelanggan Borong', $sesudah->all());

        $baru = $sesudah['Pelanggan Borong'];
        $this->assertSame(3, $baru['beli_reguler']);
        $this->assertSame(1, $baru['total_transaksi']);
        $this->assertSame(51000, $baru['total_belanja']);
        $this->assertStringContainsString('Botol 1L', $baru['rekomendasi']);
    }

    /**
     * Porsi dashboard yang boleh dibuka kasir, performa harian saja.
     *
     * @return list<string>
     */
    private static function pathKasir(): array
    {
        return [
            '/api/dashboard/meta',
            '/api/dashboard/ringkasan',
            '/api/dashboard/produk-terlaris',
        ];
    }

    /**
     * Sisanya manager-only: seluruh data per-pelanggan + export.
     *
     * @return list<string>
     */
    private static function pathManager(): array
    {
        return [
            '/api/dashboard/time-series', '/api/dashboard/revenue-ukuran',
            '/api/dashboard/platform', '/api/dashboard/loyalty',
            '/api/dashboard/rfm', '/api/dashboard/switch', '/api/laporan/export',
        ];
    }

    public function test_semua_route_dashboard_butuh_login(): void
    {
        foreach ([...self::pathKasir(), ...self::pathManager()] as $p) {
            $this->getJson($p)->assertStatus(401);
        }
    }

    public function test_kasir_boleh_ringkasan_dan_produk_terlaris(): void
    {
        Sanctum::actingAs($this->kasir());

        foreach (self::pathKasir() as $p) {
            $this->getJson($p)->assertOk();
        }
    }

    /**
     * Batas aksesnya harus tetap ketat: data per-pelanggan (RFM, loyalty,
     * switch) dan export tidak boleh bocor ke kasir.
     */
    public function test_kasir_ditolak_di_laporan_lanjutan_dan_export(): void
    {
        Sanctum::actingAs($this->kasir());

        foreach (self::pathManager() as $p) {
            $this->getJson($p)
                ->assertStatus(403)
                ->assertJsonPath('error', 'tidak_berwenang');
        }
    }

    public function test_manager_boleh_semua(): void
    {
        // Export di-fake supaya tidak render file di loop.
        Excel::fake();
        Sanctum::actingAs($this->manager());

        foreach ([...self::pathKasir(), ...self::pathManager()] as $p) {
            $this->getJson($p)->assertOk();
        }
    }

    public function test_window_kosong_mengembalikan_200_dengan_flag_dan_nol(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/ringkasan?start=2026-08-01&end=2026-08-31')
            ->assertOk()
            ->assertJsonPath('periode.start', '2026-08-01')
            ->assertJsonPath('periode.end', '2026-08-31')
            ->assertJsonPath('data_tersedia', false)
            ->assertJsonPath('data.total_revenue', 0)
            ->assertJsonPath('data.total_transaksi', 0)
            ->assertJsonPath('data.pelanggan_unik', 0);

        $this->getJson('/api/dashboard/time-series?start=2026-08-01&end=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data_tersedia', false)
            ->assertJsonPath('data', []);

        $this->getJson('/api/dashboard/produk-terlaris?start=2026-08-01&end=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data_tersedia', false)
            ->assertJsonPath('data', []);
    }

    public function test_validasi_params_salah_mengembalikan_422(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/ringkasan?grain=harianan')->assertStatus(422);
        $this->getJson('/api/dashboard/ringkasan?start=2026-07-30&end=2026-06-01')->assertStatus(422);
        $this->getJson('/api/dashboard/ringkasan?start=30-07-2026')->assertStatus(422);
    }

    public function test_export_fake_download_nama_file_dan_judul_sheet(): void
    {
        Excel::fake();
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/laporan/export')->assertOk();

        Excel::assertDownloaded('Laporan_SoyaCore_harian_2026-06-01_2026-07-30.xlsx', function ($export) {
            $titles = array_map(fn ($s) => $s->title(), $export->sheets());

            return $titles === self::SHEET_LENGKAP;
        });
    }

    public function test_export_menghasilkan_xlsx_valid_yang_bisa_dibuka(): void
    {
        $binary = Excel::raw($this->export('2026-06-01', '2026-07-30'), ExcelWriter::XLSX);

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, $binary);

        $spreadsheet = IOFactory::load($tmp);
        $this->assertSame(self::SHEET_LENGKAP, $spreadsheet->getSheetNames());

        @unlink($tmp);
    }

    public function test_export_window_kosong_tetap_xlsx_valid(): void
    {
        $binary = Excel::raw($this->export('2026-08-01', '2026-08-31'), ExcelWriter::XLSX);

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, $binary);

        $spreadsheet = IOFactory::load($tmp);
        $this->assertCount(count(self::SHEET_LENGKAP), $spreadsheet->getSheetNames());

        @unlink($tmp);
    }

    private function export(?string $start, ?string $end, ?int $kasirUserId = null): LaporanExport
    {
        return new LaporanExport(
            'harian',
            $start,
            $end,
            app(LaporanQuery::class),
            app(RekapKasirHarian::class),
            $kasirUserId,
        );
    }
}
