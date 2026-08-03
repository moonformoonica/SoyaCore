<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\LaporanTransaksi;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Models\User;
use App\Support\WaktuToko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Tiga halaman, tiga unduhan Excel yang berbeda isinya.
 *
 * Yang dijaga di sini: file yang diunduh dari sebuah halaman berisi tabel
 * halaman itu, mengikuti filter yang sedang aktif, dan namanya menyebut
 * kategorinya. Kegagalan pada ketiganya tidak memunculkan error apa pun,
 * manager hanya mendapat file yang isinya bukan yang dia lihat di layar.
 */
class ExportPerHalamanTest extends TestCase
{
    use RefreshDatabase;

    private Menu $reguler;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Signature']);
        $this->reguler = Menu::create([
            'kategori_id' => $kategori->id, 'nama' => 'Original', 'ukuran' => 'Reguler', 'harga' => 20000,
        ]);

        $this->kasir = User::factory()->create(['role' => 'kasir', 'nama' => 'Kasir Satu']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'nama' => 'Manajer']);
    }

    /** @return int Id transaksi yang sudah lunas. */
    private function transaksiLunas(int $qty = 1, string $metode = 'cash'): int
    {
        Sanctum::actingAs($this->kasir);

        $id = $this->postJson('/api/transaksi', [])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->reguler->id, 'qty' => $qty])->assertOk();
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => $metode])->assertOk();

        return $id;
    }

    /**
     * @return array<string, array<int, array<int, mixed>>> Judul sheet => baris.
     */
    private function unduh(string $url): array
    {
        Sanctum::actingAs($this->manager());

        $isi = $this->get($url)->assertOk()->streamedContent();

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, $isi);

        $spreadsheet = IOFactory::load($tmp);

        $hasil = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            // `formatData: false`, sama alasannya dengan LaporanKasirTest:
            // kolom angka memakai format ribuan, dan pembacaan ber-format
            // mengubah 20000 jadi string "20,000".
            $hasil[$sheet->getTitle()] = $sheet->toArray(null, true, false);
        }

        @unlink($tmp);

        return $hasil;
    }

    // =====================================================================
    // Laporan Kasir
    // =====================================================================

    public function test_export_laporan_kasir_hanya_satu_sheet_berisi_tabel_halamannya(): void
    {
        $this->transaksiLunas(2);

        $sheets = $this->unduh('/api/laporan/kasir/export');

        // Satu sheet saja. File tujuh sheet memaksa manager mencari lagi tabel
        // yang tadi sudah ada di depan matanya.
        $this->assertSame(['Laporan Kasir'], array_keys($sheets));

        $baris = $sheets['Laporan Kasir'];
        $this->assertSame('Kasir', $baris[0][0]);
        $this->assertSame('Kasir Satu', $baris[1][0]);
        $this->assertSame(1, (int) $baris[1][1]);      // jumlah transaksi
        $this->assertSame(40000, (int) $baris[1][2]);  // omzet
        $this->assertSame(2, (int) $baris[1][3]);      // qty

        // Baris TOTAL ikut diunduh, seperti yang terlihat di layar.
        $this->assertSame('TOTAL', end($baris)[0]);
        $this->assertSame(40000, (int) end($baris)[2]);
    }

    public function test_baris_total_dibedakan_dari_baris_data(): void
    {
        $this->transaksiLunas();

        Sanctum::actingAs($this->manager());
        $isi = $this->get('/api/laporan/kasir/export')->assertOk()->streamedContent();

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, $isi);
        $sheet = IOFactory::load($tmp)->getSheetByName('Laporan Kasir');

        // Baris 2 = satu-satunya kasir, baris 3 = TOTAL. Tanpa dibedakan,
        // angka TOTAL ikut terbaca sebagai kasir lagi dan orang yang
        // menjumlahkan kolomnya sendiri menghitung isinya dua kali.
        $this->assertFalse($sheet->getStyle('A2')->getFont()->getBold());
        $this->assertTrue($sheet->getStyle('A3')->getFont()->getBold());
        $this->assertSame('TOTAL', $sheet->getCell('A3')->getValue());

        @unlink($tmp);
    }

    public function test_export_laporan_kasir_memisahkan_jumlah_dan_nilai_metode_bayar(): void
    {
        $this->transaksiLunas(1, 'cash');
        $this->transaksiLunas(1, 'qris');

        $baris = $this->unduh('/api/laporan/kasir/export')['Laporan Kasir'][1];

        // Kolom 6-9: transaksi tunai, nilai tunai, transaksi QRIS, nilai QRIS.
        // Di layar keduanya digabung jadi teks "1× · Rp 20.000"; di Excel harus
        // tetap berupa angka supaya bisa dijumlahkan lagi.
        $this->assertSame([1, 20000, 1, 20000], array_map('intval', array_slice($baris, 5, 4)));
    }

    public function test_export_laporan_kasir_mengikuti_rentang_tanggal(): void
    {
        $this->transaksiLunas();

        $sheets = $this->unduh('/api/laporan/kasir/export?tanggal_mulai=2020-01-01&tanggal_selesai=2020-01-31');

        // Rentang tanpa transaksi: header saja, tanpa baris TOTAL yang
        // menjumlahkan data di luar rentangnya.
        $this->assertCount(1, $sheets['Laporan Kasir']);
    }

    public function test_nama_file_export_laporan_kasir_menyebut_kategori_dan_rentang(): void
    {
        Excel::fake();
        Sanctum::actingAs($this->manager());

        $this->get('/api/laporan/kasir/export?tanggal_mulai=2026-06-01&tanggal_selesai=2026-07-30')->assertOk();

        Excel::assertDownloaded('Laporan Kasir_SoyaCore_2026-06-01 Hingga 2026-07-30.xlsx');
    }

    public function test_nama_file_tanpa_filter_tanggal_tetap_menyebut_tanggal_sungguhan(): void
    {
        $this->transaksiLunas();

        Excel::fake();
        Sanctum::actingAs($this->manager());

        $this->get('/api/laporan/kasir/export')->assertOk();

        // Batas yang tidak diisi disimpulkan dari datanya, bukan ditulis
        // "Awal Hingga Akhir" yang tidak memberi tahu apa pun.
        $hariIni = WaktuToko::tanggalHariIni();
        Excel::assertDownloaded('Laporan Kasir_SoyaCore_'.$hariIni.' Hingga '.$hariIni.'.xlsx');
    }

    // =====================================================================
    // Transaksi
    // =====================================================================

    public function test_export_transaksi_hanya_satu_sheet_berisi_daftar_transaksi(): void
    {
        $id = $this->transaksiLunas(2);

        $sheets = $this->unduh('/api/laporan/transaksi/export');
        $this->assertSame(['Transaksi'], array_keys($sheets));

        $baris = $sheets['Transaksi'];
        $this->assertSame('Id Transaksi', $baris[0][0]);
        $this->assertSame($id, (int) $baris[1][0]);
        $this->assertSame(40000, (int) $baris[1][8]);   // total
        $this->assertSame(2, (int) $baris[1][11]);      // jumlah item
        $this->assertSame('Tunai', $baris[1][14]);
        $this->assertSame('Selesai', $baris[1][15]);
    }

    public function test_export_transaksi_mengikuti_filter_status(): void
    {
        $this->transaksiLunas();

        // Satu pesanan yang dibiarkan pending.
        Sanctum::actingAs($this->kasir);
        $this->postJson('/api/transaksi', [])->assertCreated();

        $semua = $this->unduh('/api/laporan/transaksi/export')['Transaksi'];
        $this->assertCount(3, $semua, 'Header + dua transaksi.');

        $terfilter = $this->unduh('/api/laporan/transaksi/export?status=pending')['Transaksi'];
        $this->assertCount(2, $terfilter, 'Header + satu transaksi pending.');
        $this->assertSame('Proses', $terfilter[1][15]);
    }

    /**
     * Batas `per_page` ada supaya tabel HTML tetap ringan. File Excel justru
     * dipakai untuk yang tidak muat di layar, jadi paginasi tidak boleh ikut
     * memotongnya.
     */
    public function test_export_transaksi_tidak_terpotong_paginasi(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->transaksiLunas();
        }

        $baris = $this->unduh('/api/laporan/transaksi/export?per_page=15')['Transaksi'];

        $this->assertCount(21, $baris, 'Header + 20 transaksi, bukan 15 baris halaman pertama.');
    }

    public function test_export_transaksi_ikut_memuat_baris_impor_csv(): void
    {
        LaporanTransaksi::create([
            'kode' => 'TR-JUL2026-0001', 'tanggal' => '2026-07-01', 'platform' => 'GrabFood',
            'nama_pelanggan' => 'Sharen', 'nama_produk' => 'Choco Maniac', 'ukuran' => 'Large',
            'qty' => 2, 'harga_satuan' => 15000, 'total' => 30000, 'poin_loyalty' => 30,
        ]);

        $baris = $this->unduh('/api/laporan/transaksi/export?tanggal_mulai=2026-07-01&tanggal_selesai=2026-07-01')['Transaksi'];

        $this->assertCount(2, $baris);
        $this->assertSame('TR-JUL2026-0001', $baris[1][3]);
        $this->assertSame('Sharen', $baris[1][4]);
        $this->assertSame('GrabFood', $baris[1][6]);
        // Data impor tidak punya id transaksi maupun kasir, dan itu ditandai,
        // bukan dibiarkan jadi sel kosong yang terbaca sebagai data hilang.
        $this->assertSame('—', $baris[1][0]);
    }

    public function test_nama_file_export_transaksi_menyebut_kategori_dan_rentang(): void
    {
        Excel::fake();
        Sanctum::actingAs($this->manager());

        $this->get('/api/laporan/transaksi/export?tanggal_mulai=2026-06-01&tanggal_selesai=2026-07-30')->assertOk();

        Excel::assertDownloaded('Laporan Transaksi_SoyaCore_2026-06-01 Hingga 2026-07-30.xlsx');
    }

    // =====================================================================
    // Hak akses
    // =====================================================================

    public function test_kasir_ditolak_di_kedua_export_baru(): void
    {
        Sanctum::actingAs($this->kasir);

        $this->getJson('/api/laporan/kasir/export')->assertStatus(403);
        $this->getJson('/api/laporan/transaksi/export')->assertStatus(403);
    }

    public function test_rentang_tanggal_terbalik_ditolak_422(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/laporan/kasir/export?tanggal_mulai=2026-07-30&tanggal_selesai=2026-06-01')
            ->assertStatus(422);
        $this->getJson('/api/laporan/transaksi/export?tanggal_mulai=2026-07-30&tanggal_selesai=2026-06-01')
            ->assertStatus(422);
    }

    public function test_transaksi_batal_sebagian_tidak_terbaca_sama_dengan_lunas(): void
    {
        $id = $this->transaksiLunas(3);

        $item = Transaksi::find($id)->detailTransaksi()->first();
        $this->postJson("/api/transaksi/{$id}/pembatalan", [
            'alasan' => 'Salah pesan satu cup',
            'items' => [['detail_transaksi_id' => $item->id, 'qty' => 1]],
        ])->assertCreated();

        $baris = $this->unduh('/api/laporan/transaksi/export')['Transaksi'];

        $this->assertSame('Batal Sebagian', $baris[1][15]);
    }
}
