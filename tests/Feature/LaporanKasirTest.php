<?php

namespace Tests\Feature;

use App\Exports\LaporanExport;
use App\Models\Kategori;
use App\Models\LaporanTransaksi;
use App\Models\Menu;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\LaporanQuery;
use App\Services\RekapKasirHarian;
use App\Support\WaktuToko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Blok C, pembedaan data per akun kasir.
 *
 * Inti permintaan pembimbing: saat pergantian shift (yang artinya sekadar
 * berganti akun login), kontribusi tiap kasir harus tetap terbaca. Tidak ada
 * mekanisme shift di sini, tidak ada buka/tutup shift, modal awal, maupun
 * hitung kas fisik.
 */
class LaporanKasirTest extends TestCase
{
    use RefreshDatabase;

    private Menu $reguler;

    private Menu $large;

    private User $kasir1;

    private User $kasir2;

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
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'nama' => 'Manajer']);
    }

    private function buatPesanan(User $kasir, Menu $menu, int $qty = 1): int
    {
        Sanctum::actingAs($kasir);

        $id = $this->postJson('/api/transaksi', [])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $menu->id, 'qty' => $qty])->assertOk();

        return $id;
    }

    private function tandaiLunas(User $kasir, int $id, string $metode = 'cash'): void
    {
        Sanctum::actingAs($kasir);
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => $metode])->assertOk();
    }

    /**
     * REGRESSION T6: inti perbaikannya.
     *
     * Sebelum ini `bayar()` menimpa `user_id` dengan akun yang menandai lunas,
     * jadi Kasir 1 lenyap tanpa jejak justru pada skenario yang ingin
     * dibedakan pembimbing.
     */
    public function test_kasir1_membuat_pesanan_kasir2_menandai_lunas(): void
    {
        $id = $this->buatPesanan($this->kasir1, $this->reguler);
        $this->tandaiLunas($this->kasir2, $id);

        $transaksi = Transaksi::find($id);

        $this->assertSame($this->kasir1->id, $transaksi->user_id, 'Pembuat pesanan tidak boleh tertimpa.');
        $this->assertSame($this->kasir2->id, $transaksi->dibayar_oleh);
    }

    public function test_omzet_masuk_ke_akun_penyelesai_pembayaran(): void
    {
        $id = $this->buatPesanan($this->kasir1, $this->reguler); // 20.000
        $this->tandaiLunas($this->kasir2, $id);

        Sanctum::actingAs($this->manager());
        $data = $this->getJson('/api/laporan/kasir')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($this->kasir2->id, $data[0]['user_id']);
        $this->assertSame('Kasir Dua', $data[0]['nama']);
        $this->assertSame(20000, $data[0]['total_omzet']);

        // Dan itulah angka yang menunjukkan serah terima pesanan.
        $this->assertSame(1, $data[0]['jumlah_transaksi_dibuat_kasir_lain']);
    }

    public function test_pesanan_soyascan_yang_dibayar_kasir2_masuk_ke_kasir2(): void
    {
        $this->postJson('/api/order', [
            'nama' => 'Budi', 'nomor_wa' => '0812-3456-7890',
            'items' => [['menu_id' => $this->reguler->id, 'qty' => 1]],
        ])->assertCreated();

        $id = Transaksi::where('sumber', 'self_order')->first()->id;
        $this->tandaiLunas($this->kasir2, $id, 'qris');

        $transaksi = Transaksi::find($id);
        $this->assertNull($transaksi->user_id, 'SoyaScan memang tidak punya kasir pembuat.');
        $this->assertSame($this->kasir2->id, $transaksi->dibayar_oleh);

        Sanctum::actingAs($this->manager());
        $data = $this->getJson('/api/laporan/kasir')->assertOk()->json('data');

        $this->assertSame($this->kasir2->id, $data[0]['user_id']);
        $this->assertSame(20000, $data[0]['total_omzet']);
        // Tidak dihitung "dibuat kasir lain": tidak ada kasir lain yang membuatnya.
        $this->assertSame(0, $data[0]['jumlah_transaksi_dibuat_kasir_lain']);
        $this->assertSame(20000, $data[0]['rincian_metode_bayar']['qris']['total']);
        $this->assertSame(0, $data[0]['rincian_metode_bayar']['cash']['total']);
    }

    public function test_laporan_dipotong_berdasarkan_waktu_lunas(): void
    {
        // Dibuat kemarin malam…
        $this->travelTo(Carbon::parse('2026-08-04 22:00', WaktuToko::ZONA));
        $id = $this->buatPesanan($this->kasir1, $this->reguler);

        // …dibayar pagi ini.
        $this->travelTo(Carbon::parse('2026-08-05 08:00', WaktuToko::ZONA));
        $this->tandaiLunas($this->kasir1, $id);

        $this->travelBack();

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/laporan/kasir?tanggal_mulai=2026-08-05&tanggal_selesai=2026-08-05')
            ->assertOk()
            ->assertJsonPath('meta.total_omzet', 20000);

        // Bukan tanggal pembuatannya.
        $this->getJson('/api/laporan/kasir?tanggal_mulai=2026-08-04&tanggal_selesai=2026-08-04')
            ->assertOk()
            ->assertJsonPath('meta.total_omzet', 0);
    }

    public function test_transaksi_pending_tidak_muncul_di_laporan_kasir_mana_pun(): void
    {
        $this->buatPesanan($this->kasir1, $this->reguler);

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/laporan/kasir')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total_omzet', 0);
    }

    public function test_satu_baris_per_akun_terurut_omzet_menurun_dan_meta_cocok(): void
    {
        // Kasir 1: 20.000. Kasir 2: 30.000 + 30.000 = 60.000.
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->reguler));
        $this->tandaiLunas($this->kasir2, $this->buatPesanan($this->kasir2, $this->large));
        $this->tandaiLunas($this->kasir2, $this->buatPesanan($this->kasir2, $this->large), 'qris');

        Sanctum::actingAs($this->manager());
        $respon = $this->getJson('/api/laporan/kasir')->assertOk();
        $data = $respon->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('Kasir Dua', $data[0]['nama']); // omzet terbesar di atas
        $this->assertSame(60000, $data[0]['total_omzet']);
        $this->assertSame('Kasir Satu', $data[1]['nama']);
        $this->assertSame(20000, $data[1]['total_omzet']);

        // meta harus bisa direkonsiliasi dengan penjumlahan barisnya.
        $respon->assertJsonPath('meta.jumlah_kasir', 2)
            ->assertJsonPath('meta.jumlah_transaksi', 3)
            ->assertJsonPath('meta.total_omzet', 80000);

        $this->assertSame(
            array_sum(array_column($data, 'total_omzet')),
            $respon->json('meta.total_omzet'),
        );

        // …dan dengan dashboard, yang membaca layer laporan.
        $this->assertSame(80000, (int) LaporanTransaksi::sum('total'));
    }

    public function test_laporan_kasir_manager_saja(): void
    {
        Sanctum::actingAs($this->kasir1);

        $this->getJson('/api/laporan/kasir')
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');
    }

    public function test_filter_dibuat_oleh_dan_dibayar_oleh_memberi_hasil_berbeda(): void
    {
        // Pesanan yang berpindah tangan: dibuat Kasir 1, dibayar Kasir 2.
        $berpindah = $this->buatPesanan($this->kasir1, $this->reguler);
        $this->tandaiLunas($this->kasir2, $berpindah);

        // Pesanan yang ditangani Kasir 2 dari awal sampai akhir.
        $milikKasir2 = $this->buatPesanan($this->kasir2, $this->large);
        $this->tandaiLunas($this->kasir2, $milikKasir2);

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/transaksi?dibuat_oleh='.$this->kasir1->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $berpindah);

        $this->getJson('/api/transaksi?dibayar_oleh='.$this->kasir2->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/transaksi?dibuat_oleh='.$this->kasir2->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milikKasir2);
    }

    /**
     * Jebakan C5: kalau daftar pending ikut difilter ke akun sendiri, Kasir 2
     * tidak akan menemukan pesanan Kasir 1 dan pelanggan terlantar di depan
     * konter dengan minuman yang sudah dibuat.
     */
    public function test_pesanan_pending_kasir1_tetap_terlihat_oleh_kasir2(): void
    {
        $id = $this->buatPesanan($this->kasir1, $this->reguler);

        Sanctum::actingAs($this->kasir2);

        $this->getJson('/api/transaksi?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    /**
     * Kasir 2 tidak perlu memasukkan ulang data apa pun: transaksinya tersimpan
     * di database, bukan di sesi login.
     */
    public function test_kasir2_membayar_pesanan_kasir1_tanpa_kirim_ulang_data(): void
    {
        Sanctum::actingAs($this->kasir1);
        $id = $this->postJson('/api/transaksi', [
            'customer' => ['nama' => 'Budi', 'no_wa' => '0812-3456-7890'],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/{$id}/items", ['menu_id' => $this->reguler->id, 'qty' => 2])->assertOk();

        $sebelum = Transaksi::with('detailTransaksi')->find($id);

        // Body-nya cuma metode bayar, tidak ada customer, tidak ada item.
        Sanctum::actingAs($this->kasir2);
        $this->postJson("/api/transaksi/{$id}/bayar", ['metode_bayar' => 'cash'])->assertOk();

        $sesudah = Transaksi::with('detailTransaksi')->find($id);

        $this->assertSame($sebelum->customer_id, $sesudah->customer_id);
        $this->assertSame($sebelum->total, $sesudah->total);
        $this->assertSame($sebelum->user_id, $sesudah->user_id);
        $this->assertSame(
            $sebelum->detailTransaksi->pluck('qty')->all(),
            $sesudah->detailTransaksi->pluck('qty')->all(),
        );

        // Yang berubah hanya tiga hal.
        $this->assertSame('lunas', $sesudah->status);
        $this->assertNotNull($sesudah->waktu_lunas);
        $this->assertSame($this->kasir2->id, $sesudah->dibayar_oleh);
    }

    public function test_alias_user_id_diperlakukan_sebagai_dibayar_oleh_dengan_fallback_pending(): void
    {
        // Lunas oleh Kasir 2, dibuat Kasir 1.
        $lunas = $this->buatPesanan($this->kasir1, $this->reguler);
        $this->tandaiLunas($this->kasir2, $lunas);

        // Pending milik Kasir 1, belum dibayar siapa pun.
        $pending = $this->buatPesanan($this->kasir1, $this->large);

        Sanctum::actingAs($this->manager());

        // Kartu statistik Kasir 2: transaksi yang dia tutup.
        $this->getJson('/api/transaksi?user_id='.$this->kasir2->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lunas);

        // Kartu statistik Kasir 1: pesanan pending-nya sendiri tetap terlihat
        // lewat fallback, jadi tidak hilang dari kartunya.
        $this->getJson('/api/transaksi?user_id='.$this->kasir1->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending);
    }

    public function test_resource_mengekspos_kedua_peran_dan_key_kasir_lama(): void
    {
        $id = $this->buatPesanan($this->kasir1, $this->reguler);

        Sanctum::actingAs($this->kasir1);

        // Belum dibayar: `kasir` jatuh ke pembuat.
        $this->getJson("/api/transaksi/{$id}")
            ->assertOk()
            ->assertJsonPath('data.kasir_pembuat.nama', 'Kasir Satu')
            ->assertJsonPath('data.kasir_penyelesai', null)
            ->assertJsonPath('data.kasir.nama', 'Kasir Satu');

        $this->tandaiLunas($this->kasir2, $id);

        // Sudah dibayar: `kasir` menunjuk penyelesai, pembuat tetap terbaca.
        $this->getJson("/api/transaksi/{$id}")
            ->assertOk()
            ->assertJsonPath('data.kasir_pembuat.nama', 'Kasir Satu')
            ->assertJsonPath('data.kasir_penyelesai.nama', 'Kasir Dua')
            ->assertJsonPath('data.kasir.nama', 'Kasir Dua');
    }

    // ---------------------------------------------------------------- export

    public function test_kolom_kasir_terisi_dan_langsung_terbaca_di_export(): void
    {
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->reguler));

        // Terbaca DETIK ITU JUGA, bukti proyeksinya sinkron, bukan queued job.
        $baris = LaporanTransaksi::first();
        $this->assertSame($this->kasir1->id, $baris->kasir_user_id);
        $this->assertSame('Kasir Satu', $baris->kasir_nama);

        $sheets = $this->bacaExport(null, null);
        $detail = $sheets['Detail Transaksi'];

        // Kolom "Kasir" ada setelah "Platform".
        $this->assertSame('Kasir', $detail[0][3]);
        $this->assertSame('Kasir Satu', $detail[1][3]);
    }

    public function test_baris_csv_historis_tampil_sebagai_tanda_pisah(): void
    {
        LaporanTransaksi::create([
            'kode' => 'TR-JUN2026-0001', 'tanggal' => '2026-06-01', 'platform' => 'QRIS',
            'nama_pelanggan' => 'Annisa', 'nama_produk' => 'Soya Honey Lemon',
            'ukuran' => 'Reguler', 'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000,
        ]);

        $detail = $this->bacaExport(null, null)['Detail Transaksi'];

        // Sel kosong terbaca sebagai data hilang; ini memang transaksi dari
        // sebelum SoyaCore dipakai, jadi diberi tanda.
        $this->assertSame('—', $detail[1][3]);
    }

    public function test_sheet_rekap_kasir_satu_baris_per_tanggal_kali_kasir(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 10:00', WaktuToko::ZONA));
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->reguler));   // 20.000
        $this->tandaiLunas($this->kasir2, $this->buatPesanan($this->kasir2, $this->large), 'qris'); // 30.000

        $this->travelTo(Carbon::parse('2026-08-06 10:00', WaktuToko::ZONA));
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->large));      // 30.000

        $this->travelBack();

        $rekap = $this->bacaExport('2026-08-05', '2026-08-06')['Rekap Kasir'];

        // Baris 0 = heading. Lalu: 05 Kasir Dua, 05 Kasir Satu, TOTAL 05,
        // 06 Kasir Satu, TOTAL KESELURUHAN.
        //
        // 6 Agustus SENGAJA tidak punya baris TOTAL: hari itu cuma satu kasir,
        // dan menjumlahkan satu baris menghasilkan salinan persis baris di
        // atasnya. Pada periode yang seluruhnya data historis (satu "kasir" per
        // tanggal) baris seperti itu menumpuk puluhan dan menenggelamkan
        // datanya sendiri.
        $this->assertSame('Tanggal', $rekap[0][0]);
        $this->assertSame('Kasir', $rekap[0][1]);

        $baris = array_slice($rekap, 1);
        $label = array_map(fn ($b) => $b[0].'|'.$b[1], $baris);

        $this->assertSame([
            '2026-08-05|Kasir Dua',
            '2026-08-05|Kasir Satu',
            '2026-08-05|'.RekapKasirHarian::LABEL_TOTAL_TANGGAL,
            '2026-08-06|Kasir Satu',
            '|'.RekapKasirHarian::LABEL_TOTAL_SEMUA,
        ], $label);

        // Total per tanggal dan total keseluruhan cocok dengan penjumlahan
        // barisnya, manager membaca file ini tanpa membuat pivot sendiri.
        // Tanggal yang kehilangan baris TOTAL-nya tetap ikut ke total
        // keseluruhan, itu syarat file ini bisa direkonsiliasi dengan Ringkasan.
        $omzet = fn (array $b) => (int) $b[4];
        $this->assertSame(50000, $omzet($baris[2]));                       // TOTAL 05
        $this->assertSame($omzet($baris[0]) + $omzet($baris[1]), $omzet($baris[2]));
        $this->assertSame(30000, $omzet($baris[3]));                       // 06 Kasir Satu
        $this->assertSame(80000, $omzet($baris[4]));                       // TOTAL KESELURUHAN

        // Cash/QRIS terpisah per baris.
        $this->assertSame(30000, (int) $baris[0][7]); // Kasir Dua, QRIS
        $this->assertSame(20000, (int) $baris[1][6]); // Kasir Satu, Cash
    }

    public function test_sheet_rekap_kasir_memuat_baris_data_historis_dan_totalnya_cocok_ringkasan(): void
    {
        LaporanTransaksi::create([
            'kode' => 'TR-JUN2026-0001', 'tanggal' => '2026-06-01', 'platform' => 'QRIS',
            'nama_produk' => 'Soya Honey Lemon', 'ukuran' => 'Reguler',
            'qty' => 1, 'harga_satuan' => 20000, 'total' => 20000,
        ]);

        $this->travelTo(Carbon::parse('2026-06-02 10:00', WaktuToko::ZONA));
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->reguler)); // 20.000
        $this->travelBack();

        $sheets = $this->bacaExport('2026-06-01', '2026-06-30');
        $baris = array_slice($sheets['Rekap Kasir'], 1);

        $namaKasir = array_column($baris, 1);
        $this->assertContains(RekapKasirHarian::LABEL_HISTORIS, $namaKasir);

        // Kalau baris historis dibuang, total sheet ini tidak akan pernah cocok
        // dengan Ringkasan dan manager mengira ada data hilang.
        $totalKeseluruhan = (int) end($baris)[4];
        $this->assertSame(40000, $totalKeseluruhan);

        $ringkasan = collect($sheets['Ringkasan'])->firstWhere(0, 'Total Revenue (Rp)');
        $this->assertSame($totalKeseluruhan, (int) $ringkasan[1]);
    }

    public function test_kasir_user_id_menyaring_semua_sheet_dan_masuk_nama_file(): void
    {
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->reguler));   // 20.000
        $this->tandaiLunas($this->kasir2, $this->buatPesanan($this->kasir2, $this->large));     // 30.000

        $sheets = $this->bacaExport(null, null, $this->kasir1->id);

        // Ringkasan menyebut kasirnya, dan angkanya hanya miliknya.
        $this->assertSame('Kasir Satu', collect($sheets['Ringkasan'])->firstWhere(0, 'Kasir')[1]);
        $this->assertSame(20000, (int) collect($sheets['Ringkasan'])->firstWhere(0, 'Total Revenue (Rp)')[1]);

        // Detail Transaksi: hanya baris Kasir Satu.
        $detail = array_slice($sheets['Detail Transaksi'], 1);
        $this->assertCount(1, $detail);
        $this->assertSame('Kasir Satu', $detail[0][3]);

        // Rekap Kasir: tidak ada nama kasir lain.
        $rekap = array_column(array_slice($sheets['Rekap Kasir'], 1), 1);
        $this->assertNotContains('Kasir Dua', $rekap);

        // Nama kasir ikut ke nama file.
        Excel::fake();
        Sanctum::actingAs($this->manager());
        $this->getJson('/api/laporan/export?kasir_user_id='.$this->kasir1->id)->assertOk();

        $hariIni = WaktuToko::tanggalHariIni();
        Excel::assertDownloaded('Laporan_SoyaCore_'.$hariIni.' Hingga '.$hariIni.'_kasir_satu.xlsx');
    }

    public function test_export_kasir_user_id_tidak_dikenal_ditolak(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/laporan/export?kasir_user_id=99999')
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    /**
     * REGRESSION T5 di Excel: ini sumber ketidakcocokan yang paling sering
     * terjadi antara angka di layar dan angka di file.
     */
    public function test_kolom_tanggal_di_excel_memakai_wib(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 23:30', WaktuToko::ZONA));
        $this->tandaiLunas($this->kasir1, $this->buatPesanan($this->kasir1, $this->reguler));
        $this->travelBack();

        $detail = $this->bacaExport(null, null)['Detail Transaksi'];

        $this->assertSame('2026-08-05', $detail[1][1]);
    }

    /**
     * Membaca isi sheet export sebagai array 2 dimensi per judul sheet.
     *
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function bacaExport(?string $start, ?string $end, ?int $kasirUserId = null): array
    {
        $export = new LaporanExport(
            'harian', $start, $end,
            app(LaporanQuery::class), app(RekapKasirHarian::class),
            $kasirUserId,
            $kasirUserId === null ? null : User::find($kasirUserId)?->nama,
        );

        $tmp = tempnam(sys_get_temp_dir(), 'soyacore_').'.xlsx';
        file_put_contents($tmp, Excel::raw($export, ExcelWriter::XLSX));

        $spreadsheet = IOFactory::load($tmp);

        $hasil = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            // `formatData: false` WAJIB di sini. Kolom angka di export memakai
            // format ribuan `#,##0`, jadi pembacaan ber-format mengembalikan
            // string "50,000" dan `(int)` di assertion memotongnya jadi 50.
            // Yang diuji isinya, bukan tampilannya.
            $hasil[$sheet->getTitle()] = $sheet->toArray(null, true, false);
        }

        @unlink($tmp);

        return $hasil;
    }
}
