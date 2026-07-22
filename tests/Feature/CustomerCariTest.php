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
