<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loyalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCariTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $budi = Customer::create(['nama' => 'Budi Santoso', 'no_wa' => '6281234567890']);
        Loyalty::create(['customer_id' => $budi->id, 'poin' => 400]);

        // Tanpa baris loyalty — customer lama yang belum pernah dapat poin.
        Customer::create(['nama' => 'Budiman', 'no_wa' => '6289999999999']);

        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
    }

    public function test_cari_no_wa_mengembalikan_nama_dan_poin(): void
    {
        $this->getJson('/api/customers/cari?no_wa=6281234567890')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Budi Santoso')
            ->assertJsonPath('data.0.poin', 400);
    }

    /**
     * Inti auto-detect: kasir mengetik "0812..." tapi DB menyimpan "62812...".
     */
    public function test_no_wa_dinormalisasi_sebelum_dicocokkan(): void
    {
        foreach (['0812-3456-7890', '+62 812 3456 7890', '81234567890'] as $input) {
            $this->getJson('/api/customers/cari?no_wa='.urlencode($input))
                ->assertOk()
                ->assertJsonPath('data.0.nama', 'Budi Santoso');
        }
    }

    public function test_customer_tanpa_baris_loyalty_dianggap_nol_poin(): void
    {
        $this->getJson('/api/customers/cari?no_wa=6289999999999')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Budiman')
            ->assertJsonPath('data.0.poin', 0);
    }

    /**
     * Saran nomor terdaftar: kasir baru mengetik sebagian, nomor yang cocok
     * sudah muncul — tidak perlu hafal nomor lengkapnya.
     */
    public function test_nomor_sebagian_memunculkan_nomor_terdaftar(): void
    {
        // awalan dalam ejaan lokal ("0812") maupun format simpan ("6281")
        foreach (['0812', '6281', '812'] as $sebagian) {
            $this->getJson('/api/customers/cari?no_wa='.urlencode($sebagian))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.nama', 'Budi Santoso')
                ->assertJsonPath('data.0.no_wa', '6281234567890');
        }

        // potongan tengah/ekor nomor juga ketemu
        $this->getJson('/api/customers/cari?no_wa=4567890')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Budi Santoso');

        // awalan yang dipakai bersama mengembalikan dua-duanya
        $this->getJson('/api/customers/cari?no_wa=628')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Kebiasaan nyata kasir: yang diingat pelanggan itu digit BELAKANG, bukan
     * awalan. Ini dulu gagal — normalisasi() menempelkan "62" ke potongan yang
     * diawali 0/8 (karena dirancang untuk nomor lengkap), sehingga "8122"
     * dicari sebagai "628122" dan tidak pernah ketemu di "6281245688122".
     */
    public function test_digit_belakang_dan_tengah_menemukan_nomor(): void
    {
        Customer::create(['nama' => 'Kamila', 'no_wa' => '6281245688122']);

        // 4 digit terakhir — diawali 8, kasus yang dilaporkan
        $this->getJson('/api/customers/cari?no_wa=8122')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Kamila')
            ->assertJsonPath('data.0.no_wa', '6281245688122');

        // potongan ekor & tengah lain, termasuk yang diawali 0 dan 8
        foreach (['88122', '5688122', '4568', '0812456'] as $potongan) {
            $this->getJson('/api/customers/cari?no_wa='.urlencode($potongan))
                ->assertOk()
                ->assertJsonPath('data.0.nama', 'Kamila');
        }
    }

    /**
     * Potongan ekor yang diawali 0 juga tidak boleh ditempeli "62".
     */
    public function test_digit_belakang_diawali_nol_tetap_ketemu(): void
    {
        Customer::create(['nama' => 'Rani', 'no_wa' => '6281377700815']);

        $this->getJson('/api/customers/cari?no_wa=0815')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Rani');
    }

    public function test_nomor_persis_muncul_paling_atas(): void
    {
        // nomor lain yang memuat nomor Budi sebagai awalan
        Customer::create(['nama' => 'Ahmad', 'no_wa' => '62812345678901']);

        $this->getJson('/api/customers/cari?no_wa=6281234567890')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // meski "Ahmad" lebih dulu secara alfabet, yang persis cocok di atas
            ->assertJsonPath('data.0.nama', 'Budi Santoso')
            ->assertJsonPath('data.1.nama', 'Ahmad');
    }

    /**
     * Tanpa batas minimal, "8" mencocokkan hampir semua nomor Indonesia
     * (semuanya tersimpan sebagai 628xxx) dan endpoint ini jadi dump
     * daftar pelanggan.
     */
    public function test_nomor_terlalu_pendek_tidak_mendump_daftar_pelanggan(): void
    {
        foreach (['8', '0', '62', '+'] as $terlaluPendek) {
            $this->getJson('/api/customers/cari?no_wa='.urlencode($terlaluPendek))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_wildcard_like_tidak_bocor_dari_input_no_wa(): void
    {
        // wildcard murni tidak menyisakan digit apa pun -> bukan pencarian
        foreach (['%', '%%', '_', '%8%'] as $input) {
            $this->getJson('/api/customers/cari?no_wa='.urlencode($input))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }

        // dan wildcard yang menempel di angka tidak menambah kecocokan:
        // "628%" harus berperilaku persis seperti "628", bukan seperti pola
        $polos = $this->getJson('/api/customers/cari?no_wa=628')->assertOk()->json('data');
        $berwildcard = $this->getJson('/api/customers/cari?no_wa='.urlencode('628%'))->assertOk()->json('data');

        $this->assertSame($polos, $berwildcard);
    }

    /**
     * Kontrak yang dipegang UI: satu kotak nomor harus menemukan pelanggan
     * terdaftar baik dari nomor LENGKAP (ejaan apa pun) maupun dari 4 DIGIT
     * TERAKHIR — itu yang biasanya disebut pelanggan di konter.
     */
    public function test_nomor_penuh_dan_4_digit_terakhir_menemukan_pelanggan_yang_sama(): void
    {
        // 1) Nomor lengkap, empat ejaan berbeda.
        foreach (['081234567890', '0812-3456-7890', '+62 812 3456 7890', '6281234567890'] as $ketikan) {
            $this->getJson('/api/customers/cari?no_wa='.urlencode($ketikan))
                ->assertOk()
                ->assertJsonPath('data.0.nama', 'Budi Santoso')
                ->assertJsonPath('data.0.no_wa', '6281234567890')
                ->assertJsonPath('data.0.poin', 400);
        }

        // 2) Empat digit terakhir.
        $this->getJson('/api/customers/cari?no_wa=7890')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Budi Santoso');

        // Nomor lain dengan ekor berbeda tidak ikut terbawa.
        $this->getJson('/api/customers/cari?no_wa=9999')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Budiman');
    }

    /**
     * `LIKE` case-sensitive di PostgreSQL. Tanpa `LOWER()` di kedua sisi, test
     * ini lulus di SQLite lokal tapi kasir di produksi tidak akan menemukan
     * "Budi Santoso" saat mengetik "budi".
     */
    public function test_cari_nama_tidak_bergantung_huruf_besar_kecil(): void
    {
        foreach (['budi', 'BUDI', 'Budi', 'sAnToSo'] as $ketikan) {
            $this->getJson('/api/customers/cari?nama='.$ketikan)
                ->assertOk()
                ->assertJsonPath('data.0.nama', 'Budi Santoso');
        }
    }

    public function test_cari_nama_parsial_bisa_mengembalikan_banyak_hasil(): void
    {
        $this->getJson('/api/customers/cari?nama=budi')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nama', 'Budi Santoso')
            ->assertJsonPath('data.1.nama', 'Budiman');
    }

    /**
     * Pelanggan baru = state normal saat kasir masih mengetik, bukan 404.
     */
    public function test_tidak_ketemu_mengembalikan_200_dengan_data_kosong(): void
    {
        $this->getJson('/api/customers/cari?no_wa=628000000000')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * "Bu%" dan "B_di" hanya cocok kalau wildcard-nya dieksekusi mentah —
     * setelah di-escape keduanya jadi pencarian teks literal, 0 hasil.
     */
    public function test_wildcard_like_tidak_bocor_dari_input_nama(): void
    {
        foreach (['Bu%', 'B_di', '%%'] as $input) {
            $this->getJson('/api/customers/cari?nama='.urlencode($input))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_query_kosong_ditolak(): void
    {
        $this->getJson('/api/customers/cari')
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_tanpa_login_ditolak(): void
    {
        app()['auth']->forgetGuards();

        $this->getJson('/api/customers/cari?no_wa=6281234567890')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }
}
