<?php

namespace Tests\Feature;

use App\Exports\LaporanExport;
use App\Models\LaporanRevenueUkuran;
use App\Models\User;
use App\Services\LaporanQuery;
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
     * Revenue per ukuran hanya mencakup minuman — dessert & cookies
     * (Cup/Pack) sengaja dikecualikan, jadi lebih kecil dari FULL_REVENUE.
     */
    private const MINUMAN_REVENUE = 21192000;

    private const NON_MINUMAN_REVENUE = 5065000;

    private const FULL_ROWS = 882;

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

    public function test_rfm_dan_switch_statis_dengan_periode_label(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/rfm')
            ->assertOk()
            ->assertJsonPath('periode_label', '1 Jun 2026 – 30 Jul 2026')
            ->assertJsonStructure(['ringkasan_segmen', 'data'])
            ->assertJsonCount(345, 'data');

        // Penamaan segmen berubah di data revisi Juni–Juli 2026:
        // "Pelanggan Loyal" -> "Loyal", "Pelanggan Potensial" -> "Potensial",
        // "Hampir Hilang" diganti "Pelanggan Baru".
        $this->getJson('/api/dashboard/rfm?segmen=Loyal')
            ->assertOk()
            ->assertJsonPath('data.0.segmen', 'Loyal')
            ->assertJsonCount(21, 'data');

        $this->getJson('/api/dashboard/switch')
            ->assertOk()
            ->assertJsonPath('periode_label', '1 Jun 2026 – 30 Jul 2026')
            ->assertJsonCount(35, 'data');
    }

    /**
     * Porsi dashboard yang boleh dibuka kasir — performa harian saja.
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

            return $titles === [
                'Ringkasan', 'Detail Transaksi', 'Revenue per Ukuran',
                'Time Series', 'RFM Pelanggan', 'Rekomendasi Switch',
            ];
        });
    }

    public function test_export_menghasilkan_xlsx_valid_yang_bisa_dibuka(): void
    {
        $export = new LaporanExport('harian', '2026-06-01', '2026-07-30', app(LaporanQuery::class));
        $binary = Excel::raw($export, ExcelWriter::XLSX);

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, $binary);

        $spreadsheet = IOFactory::load($tmp);
        $this->assertSame([
            'Ringkasan', 'Detail Transaksi', 'Revenue per Ukuran',
            'Time Series', 'RFM Pelanggan', 'Rekomendasi Switch',
        ], $spreadsheet->getSheetNames());

        @unlink($tmp);
    }

    public function test_export_window_kosong_tetap_xlsx_valid(): void
    {
        $export = new LaporanExport('harian', '2026-08-01', '2026-08-31', app(LaporanQuery::class));
        $binary = Excel::raw($export, ExcelWriter::XLSX);

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, $binary);

        $spreadsheet = IOFactory::load($tmp);
        $this->assertCount(6, $spreadsheet->getSheetNames());

        @unlink($tmp);
    }
}
