<?php

namespace Tests\Feature;

use App\Models\LaporanRfm;
use App\Services\LaporanImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LaporanImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/csv-laporan');

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }

        // Semua file wajib ada — importer melempar exception kalau hilang.
        $this->tulis('Data_Transaksi_Bersih.csv', <<<'CSV'
            ID Transaksi,Tanggal,Platform,Nama Pelanggan,No WhatsApp,Nama Produk,Rasa,Ukuran,Jumlah (pcs),Harga Satuan (Rp),Total (Rp),Poin Loyalty,Catatan,Hari,Urutan_Hari
            TR-1,2026-06-01,QRIS,Budi,,Soya Original,Original,Reguler,1,17000,17000,17,,Senin,1
            CSV);

        $this->tulis('Data_Revenue_Ukuran.csv', <<<'CSV'
            Ukuran,Jumlah_Terjual,Total_Revenue,Jumlah_Transaksi,Rata_rata_Transaksi
            Reguler,1,17000,1,17000
            CSV);

        $this->tulis('Data_Switch_Ukuran.csv', <<<'CSV'
            Nama Pelanggan,Rasa Favorit,Ukuran Saat Ini,Beli Reguler (pcs),Beli Large (pcs),Beli Botol (pcs),Total Transaksi,Qty per Kunjungan,Total Belanja (Rp),Rekomendasi
            Budi,Original,Reguler,1,0,0,1,1.0,17000,"Tawarkan Large, sudah sering Reguler"
            CSV);

        $this->tulisRfmLengkap();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*.csv') as $f) {
            unlink($f);
        }

        parent::tearDown();
    }

    private function tulis(string $nama, string $isi): void
    {
        file_put_contents($this->dir.'/'.$nama, $isi."\n");
    }

    private function tulisRfmLengkap(): void
    {
        $this->tulis('Data_RFM_Pelanggan.csv', <<<'CSV'
            Nama Pelanggan,Recency,Frekuensi_Kedatangan,Total_Pcs_Dibeli,Monetary,Total_Poin_Loyalty,Frequency,R_Score,F_Score,M_Score,RFM_Total,Segmen
            Aden,27,17,18,513000,348,17.4,3,4,4,11,Loyal
            CSV);
    }

    private function impor(): array
    {
        return (new LaporanImporter($this->dir))->import();
    }

    public function test_impor_memetakan_kolom_rfm_sesuai_nama_header(): void
    {
        $this->impor();

        $aden = LaporanRfm::where('nama_pelanggan', 'Aden')->firstOrFail();

        $this->assertSame(27, $aden->recency);
        $this->assertSame(17, $aden->frequency);          // Frekuensi_Kedatangan
        $this->assertSame(18, $aden->total_pcs_dibeli);
        $this->assertSame(513000, $aden->monetary);
        $this->assertSame(348, $aden->total_poin_loyalty);
        $this->assertSame(17.4, $aden->frequency_skor);   // kolom "Frequency" (desimal)
        $this->assertSame('Loyal', $aden->segmen);
    }

    /**
     * Inti perlindungan: kolom disisipkan di tengah tidak boleh menggeser
     * pemetaan. Pemetaan posisional versi lama akan menaruh Monetary ke
     * r_score dan F_Score ke segmen tanpa error apa pun.
     */
    public function test_kolom_baru_di_tengah_tidak_menggeser_pemetaan(): void
    {
        $this->tulis('Data_RFM_Pelanggan.csv', <<<'CSV'
            Nama Pelanggan,Kolom_Iseng,Recency,Frekuensi_Kedatangan,Total_Pcs_Dibeli,Monetary,Total_Poin_Loyalty,Frequency,R_Score,F_Score,M_Score,RFM_Total,Segmen
            Aden,xyz,27,17,18,513000,348,17.4,3,4,4,11,Loyal
            CSV);

        $this->impor();

        $aden = LaporanRfm::where('nama_pelanggan', 'Aden')->firstOrFail();

        $this->assertSame(513000, $aden->monetary);
        $this->assertSame(3, $aden->r_score);
        $this->assertSame('Loyal', $aden->segmen);
    }

    public function test_header_hilang_ditolak_dengan_pesan_jelas(): void
    {
        $this->tulis('Data_RFM_Pelanggan.csv', <<<'CSV'
            Nama Pelanggan,Recency,Frekuensi_Kedatangan,Monetary,R_Score,F_Score,M_Score,RFM_Total,Segmen
            Aden,27,17,513000,3,4,4,11,Loyal
            CSV);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Total_Pcs_Dibeli/');

        $this->impor();
    }

    public function test_impor_idempotent_dijalankan_dua_kali(): void
    {
        $this->impor();
        $hasil = $this->impor();

        $this->assertSame(1, $hasil['laporan_rfm']);
        $this->assertSame(1, LaporanRfm::count());
    }

    public function test_urutan_kolom_berubah_tetap_benar(): void
    {
        $this->tulis('Data_RFM_Pelanggan.csv', <<<'CSV'
            Segmen,Nama Pelanggan,RFM_Total,M_Score,F_Score,R_Score,Frequency,Total_Poin_Loyalty,Monetary,Total_Pcs_Dibeli,Frekuensi_Kedatangan,Recency
            Loyal,Aden,11,4,4,3,17.4,348,513000,18,17,27
            CSV);

        $this->impor();

        $aden = LaporanRfm::where('nama_pelanggan', 'Aden')->firstOrFail();

        $this->assertSame(27, $aden->recency);
        $this->assertSame(513000, $aden->monetary);
        $this->assertSame(348, $aden->total_poin_loyalty);
        $this->assertSame('Loyal', $aden->segmen);
    }

    public function test_baris_non_minuman_diimpor_dengan_poin_nol(): void
    {
        $this->tulis('Data_Transaksi_Bersih.csv', <<<'CSV'
            ID Transaksi,Tanggal,Platform,Nama Pelanggan,No WhatsApp,Nama Produk,Rasa,Ukuran,Jumlah (pcs),Harga Satuan (Rp),Total (Rp),Poin Loyalty,Catatan,Hari,Urutan_Hari
            TR-2,2026-06-01,Tunai,Tata,,Soya Tahwa Kembang Tahu,Tahwa Kembang Tahu,Cup,1,15000,15000,0,Non-minuman,Senin,1
            CSV);

        $this->impor();

        $this->assertDatabaseHas('laporan_transaksi', [
            'kode' => 'TR-2',
            'poin_loyalty' => 0,
            'catatan' => 'Non-minuman',
        ]);
    }
}
