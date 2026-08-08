<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\LaporanTransaksi;
use App\Models\Menu;
use App\Models\User;
use App\Support\GolonganUkuran;
use App\Support\PlatformPembayaran;
use Database\Seeders\LaporanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Enam butir revisi dashboard dari review pembimbing:
 *
 * 1. Tren, harinya berdasarkan apa (grain `hari_dalam_minggu`)
 * 2. Bar "10 menu kurang diminati" harus dari yang paling sedikit
 * 3. Revenue cup, rincian ukuran ml yang paling sering keluar
 * 4. Filter platform, `qris` huruf kecil yang nyempil
 * 5. Pola treatment tiap segmen pelanggan
 * 6. Kemasan botol tetap dapat takaran gula (di SoyaScanOpsiMenuTest)
 */
class DashboardRevisiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LaporanSeeder::class);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    // ---------------------------------------------------------------- tren

    public function test_grain_hari_dalam_minggu_menggabungkan_seluruh_senin(): void
    {
        Sanctum::actingAs($this->manager());

        $data = $this->getJson('/api/dashboard/time-series?grain=hari_dalam_minggu')
            ->assertOk()
            ->json('data');

        $this->assertCount(7, $data);

        // Urut Senin sampai Minggu, BUKAN alfabetis. Mengurutkan nama hari
        // menghasilkan Jumat, Kamis, Minggu, Rabu, Sabtu, Selasa, Senin.
        $this->assertSame(
            ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
            array_column($data, 'hari'),
        );

        // Totalnya harus sama dengan grain harian: pengelompokan berubah,
        // datanya tidak.
        $harian = $this->getJson('/api/dashboard/time-series?grain=harian')->json('data');

        $this->assertSame(
            array_sum(array_column($harian, 'revenue')),
            array_sum(array_column($data, 'revenue')),
        );
    }

    public function test_rata_rata_per_hari_membagi_dengan_jumlah_kemunculan_hari(): void
    {
        Sanctum::actingAs($this->manager());

        // 2026-06-01 adalah Senin. Rentang 8 hari memuat Senin dua kali
        // (1 dan 8 Juni), sisanya sekali.
        $data = collect(
            $this->getJson('/api/dashboard/time-series?grain=hari_dalam_minggu&start=2026-06-01&end=2026-06-08')
                ->assertOk()
                ->json('data')
        )->keyBy('hari');

        $this->assertSame(2, $data['Senin']['jumlah_hari']);
        $this->assertSame(1, $data['Selasa']['jumlah_hari']);

        // Tanpa pembagi ini, Senin terlihat dua kali lebih ramai semata karena
        // kebetulan muncul dua kali di rentangnya.
        $this->assertSame(
            (int) round($data['Senin']['revenue'] / 2),
            $data['Senin']['rata_rata_per_hari'],
        );
    }

    public function test_grain_tidak_dikenal_ditolak(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/dashboard/time-series?grain=per_jam')->assertStatus(422);
    }

    // ------------------------------------------------- menu kurang diminati

    public function test_arah_terendah_dimulai_dari_yang_paling_sedikit_terjual(): void
    {
        Sanctum::actingAs($this->manager());

        $data = $this->getJson('/api/dashboard/produk-terlaris?arah=terendah&limit=10')
            ->assertOk()
            ->json('data');

        $qty = array_column($data, 'qty');

        // Elemen pertama = paling sedikit, dan qty MENAIK sepanjang array.
        // Urutan array inilah urutan batang di chart, jadi arahnya harus benar
        // di sini, bukan dibalik lagi oleh frontend.
        $terurut = $qty;
        sort($terurut);
        $this->assertSame($terurut, $qty);
        $this->assertSame(min($qty), $qty[0]);
    }

    public function test_arah_tertinggi_tetap_menurun(): void
    {
        Sanctum::actingAs($this->manager());

        $qty = array_column(
            $this->getJson('/api/dashboard/produk-terlaris?limit=10')->assertOk()->json('data'),
            'qty',
        );

        $terurut = $qty;
        rsort($terurut);
        $this->assertSame($terurut, $qty);
    }

    public function test_produk_dengan_qty_sama_urutannya_stabil_antar_panggilan(): void
    {
        Sanctum::actingAs($this->manager());

        $ambil = fn () => array_map(
            fn ($b) => $b['nama_produk'].'|'.$b['rasa'],
            $this->getJson('/api/dashboard/produk-terlaris?arah=terendah&limit=10')->json('data'),
        );

        // Di daftar terendah banyak produk ber-qty sama. Tanpa tie-breaker,
        // urutannya ditentukan database dan chart terlihat bergoyang tiap kali
        // halaman dimuat padahal datanya tidak berubah.
        $this->assertSame($ambil(), $ambil());
    }

    public function test_sertakan_nol_memunculkan_menu_yang_belum_pernah_terjual(): void
    {
        Sanctum::actingAs($this->manager());

        // LaporanSeeder hanya mengisi tabel laporan, katalog menunya kosong,
        // jadi kategorinya dibuat di sini.
        $kategori = Kategori::firstOrCreate(['nama' => 'Soya Signature']);

        $menuBaru = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Aaa Menu Belum Pernah Laku',
            'rasa' => 'Uji Coba',
            'harga' => 20000,
            'ukuran' => 'Reguler',
            'is_active' => true,
        ]);

        $tanpa = array_column(
            $this->getJson('/api/dashboard/produk-terlaris?arah=terendah&limit=10')->json('data'),
            'nama_produk',
        );
        $this->assertNotContains($menuBaru->nama, $tanpa);

        $dengan = $this->getJson('/api/dashboard/produk-terlaris?arah=terendah&sertakan_nol=true&limit=10')
            ->assertOk()
            ->json('data');

        // Menu bernilai nol berada paling depan: 0 lebih kecil dari 1, dan
        // itulah jawaban yang dicari grafik "kurang diminati".
        $this->assertSame($menuBaru->nama, $dengan[0]['nama_produk']);
        $this->assertSame(0, $dengan[0]['qty']);
    }

    // ------------------------------------------------------ revenue ukuran

    public function test_revenue_ukuran_dikelompokkan_per_golongan(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->getJson('/api/dashboard/revenue-ukuran')->assertOk();
        $data = $response->json('data');

        // Cup lebih dulu, lalu botol, lalu lainnya.
        $golongan = array_values(array_unique(array_column($data, 'golongan')));
        $this->assertSame(
            array_values(array_intersect(GolonganUkuran::semua(), $golongan)),
            $golongan,
        );

        // Di dalam tiap golongan, jumlah terjual menurun supaya ukuran yang
        // paling sering keluar terbaca di baris pertama.
        foreach (GolonganUkuran::semua() as $kode) {
            $qty = array_column(
                array_values(array_filter($data, fn ($b) => $b['golongan'] === $kode)),
                'jumlah_terjual',
            );

            $terurut = $qty;
            rsort($terurut);
            $this->assertSame($terurut, $qty, "Golongan {$kode} tidak terurut menurun.");
        }
    }

    public function test_persen_dari_golongan_berjumlah_seratus_tiap_golongan(): void
    {
        Sanctum::actingAs($this->manager());

        $data = $this->getJson('/api/dashboard/revenue-ukuran')->assertOk()->json('data');

        $total = [];
        foreach ($data as $baris) {
            $total[$baris['golongan']] = ($total[$baris['golongan']] ?? 0) + $baris['persen_dari_golongan'];
        }

        foreach ($total as $kode => $jumlah) {
            // Toleransi pembulatan satu desimal per baris.
            $this->assertEqualsWithDelta(100.0, $jumlah, 0.5, "Golongan {$kode} tidak berjumlah 100%.");
        }
    }

    public function test_ringkasan_golongan_menyebut_ukuran_paling_sering_keluar(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->getJson('/api/dashboard/revenue-ukuran')->assertOk();

        $ringkasan = collect($response->json('ringkasan_golongan'))->keyBy('golongan');
        $data = collect($response->json('data'));

        foreach ($ringkasan as $kode => $g) {
            $barisGolongan = $data->where('golongan', $kode);

            // Subtotal harus sama dengan penjumlahan barisnya, kalau tidak,
            // manager yang menjumlahkan sendiri akan mengira ada data hilang.
            $this->assertSame($barisGolongan->sum('jumlah_terjual'), $g['jumlah_terjual']);
            $this->assertSame($barisGolongan->sum('total_revenue'), $g['total_revenue']);

            // Ukuran terlaris = yang jumlah terjualnya paling besar di golongan itu.
            $this->assertSame(
                $barisGolongan->sortByDesc('jumlah_terjual')->first()['ukuran'],
                $g['ukuran_terlaris'],
            );
        }
    }

    // ---------------------------------------------------------- platform

    public function test_platform_tidak_punya_duplikat_beda_kapitalisasi(): void
    {
        Sanctum::actingAs($this->manager());

        $nilai = LaporanTransaksi::query()
            ->whereNotNull('platform')
            ->distinct()
            ->pluck('platform')
            ->all();

        // Setelah normalisasi, tidak boleh ada satu pun nilai yang masih
        // memakai kosakata POS (`cash`, `qris` huruf kecil).
        foreach ($nilai as $platform) {
            $this->assertFalse(
                PlatformPembayaran::perluDinormalkan($platform),
                "Platform '{$platform}' masih memakai kosakata lama.",
            );
        }

        // Dan tidak ada dua nilai yang hanya berbeda huruf besar-kecil.
        $lower = array_map('mb_strtolower', $nilai);
        $this->assertSame(count($nilai), count(array_unique($lower)));
    }

    public function test_pemetaan_platform_menjaga_channel_apa_adanya(): void
    {
        // Kolom `platform` memang campuran metode bayar dan channel. Memaksa
        // semuanya ke salah satu nilai baku justru merusak nilai channel yang
        // sejak awal sudah benar.
        $this->assertSame('Tunai', PlatformPembayaran::dari('cash'));
        $this->assertSame('Tunai', PlatformPembayaran::dari('CASH'));
        $this->assertSame('QRIS', PlatformPembayaran::dari('qris'));
        $this->assertSame('GrabFood', PlatformPembayaran::dari('GrabFood'));
        $this->assertSame('Transfer', PlatformPembayaran::dari('Transfer'));
        $this->assertNull(PlatformPembayaran::dari(null));
        $this->assertNull(PlatformPembayaran::dari('  '));
    }

    // ------------------------------------------------------------ segmen

    public function test_rfm_membawa_treatment_untuk_setiap_segmen(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->getJson('/api/dashboard/rfm')->assertOk();

        $treatment = collect($response->json('segmen_treatment'));
        $ringkasan = $response->json('ringkasan_segmen');

        $this->assertCount(4, $treatment);

        // Diurutkan prioritas penanganan, bukan jumlah anggota.
        $this->assertSame(
            ['Loyal', 'Potensial', 'Pelanggan Baru', 'Butuh Perhatian'],
            $treatment->pluck('segmen')->all(),
        );

        foreach ($treatment as $s) {
            $this->assertNotEmpty($s['karakteristik']);
            $this->assertNotEmpty($s['tujuan']);
            $this->assertNotEmpty($s['treatment']);
            $this->assertNotEmpty($s['reward_disarankan']);

            // Jumlahnya harus cocok dengan ringkasan_segmen di response yang
            // sama, dua angka berbeda di satu layar untuk hal yang sama adalah
            // cara tercepat kehilangan kepercayaan pada laporannya.
            $this->assertSame($ringkasan[$s['segmen']] ?? 0, $s['jumlah_pelanggan']);
        }

        $this->assertEqualsWithDelta(100.0, $treatment->sum('persen'), 0.5);
    }

    public function test_reward_yang_disarankan_benar_benar_ada_di_katalog(): void
    {
        Sanctum::actingAs($this->manager());

        $kode = collect($this->getJson('/api/dashboard/rfm')->json('segmen_treatment'))
            ->pluck('reward_disarankan');

        $katalog = collect($this->getJson('/api/pengaturan/loyalty/katalog')->assertOk()->json('data'))
            ->pluck('kode');

        // Rekomendasi yang menunjuk kode tidak ada akan membuat kasir mencari
        // tombol yang tidak pernah muncul di layarnya.
        foreach ($kode as $k) {
            $this->assertContains($k, $katalog->all(), "Kode reward '{$k}' tidak ada di katalog.");
        }
    }

    public function test_segmen_loyal_tidak_merekomendasikan_diskon(): void
    {
        Sanctum::actingAs($this->manager());

        $loyal = collect($this->getJson('/api/dashboard/rfm')->json('segmen_treatment'))
            ->firstWhere('segmen', 'Loyal');

        // Keputusan bisnis yang paling mudah tergeser tanpa sengaja: mereka
        // sudah membeli tanpa insentif, jadi diskon di sini hanya memotong
        // margin dari penjualan yang toh tetap terjadi.
        $this->assertStringStartsWith('gratis_', $loyal['reward_disarankan']);
    }
}
