<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kategori;
use App\Models\Loyalty;
use App\Models\Menu;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Menu $susu;

    private Menu $tahu;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Kategori::create(['nama' => 'Soya Signature']);
        $this->susu = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Original',
            'ukuran' => 'Reguler',
            'harga' => 17000,
        ]);
        $this->tahu = Menu::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Original',
            'ukuran' => 'Large',
            'harga' => 21000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'nama' => 'Budi',
            'nomor_wa' => '0812-3456-7890',
            // PERUBAHAN KONTRAK: `nomor_meja` sudah tidak dikirim lagi.
            'items' => [
                ['menu_id' => $this->susu->id, 'qty' => 2],
                ['menu_id' => $this->tahu->id, 'qty' => 1],
            ],
        ], $override);
    }

    public function test_order_publik_tanpa_auth_membuat_transaksi_pending(): void
    {
        $respon = $this->postJson('/api/order', $this->payload())->assertCreated();

        // 2x17000 + 1x21000 = 55000, dihitung server
        $respon->assertJsonPath('status', 'pending')
            ->assertJsonPath('total', 55000)
            ->assertJsonPath('kode_pesanan', '#A00')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.nama_menu', 'Original');

        $this->assertStringContainsString('#A00', $respon->json('pesan'));

        $transaksi = Transaksi::first();
        $this->assertNull($transaksi->user_id); // belum ada kasir pembuat
        $this->assertNull($transaksi->dibayar_oleh); // belum dibayar siapa pun
        $this->assertSame('self_order', $transaksi->sumber);
        $this->assertSame('self_order', $transaksi->detailTransaksi()->first()->sumber);

        // pelanggan baru dapat bonus pendaftaran, dan cuma itu, poin dari
        // belanjanya sendiri TIDAK bertambah selama transaksi masih pending
        $this->assertSame(Loyalty::POIN_BONUS_DAFTAR, Loyalty::first()->poin);
    }

    public function test_metode_bayar_pilihan_pelanggan_tersimpan(): void
    {
        foreach (['cash', 'qris'] as $metode) {
            $this->postJson('/api/order', $this->payload(['metode_bayar' => $metode]))
                ->assertCreated()
                ->assertJsonPath('metode_bayar', $metode);

            $this->assertSame($metode, Transaksi::latest('id')->first()->metode_bayar);
        }
    }

    public function test_metode_bayar_opsional_default_null(): void
    {
        // Klien lama yang belum kirim metode_bayar tetap boleh order.
        $this->postJson('/api/order', $this->payload())
            ->assertCreated()
            ->assertJsonPath('metode_bayar', null);

        $this->assertNull(Transaksi::first()->metode_bayar);
    }

    public function test_metode_bayar_nilai_invalid_ditolak(): void
    {
        // 'tunai' adalah label UI, bukan nilai tersimpan, harus ditolak.
        $this->postJson('/api/order', $this->payload(['metode_bayar' => 'tunai']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_harga_kiriman_client_diabaikan_total_tetap_dari_server(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['harga'] = 1; // percobaan manipulasi harga
        $payload['total'] = 5;

        $this->postJson('/api/order', $payload)
            ->assertCreated()
            ->assertJsonPath('total', 55000)
            ->assertJsonPath('items.0.harga_satuan', 17000);
    }

    public function test_kode_pesanan_berurutan_dan_reset_tiap_minggu(): void
    {
        $this->travelTo('2026-08-05 10:00:00'); // Rabu

        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A00');
        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A01');

        // Ganti hari, MASIH minggu yang sama: nomornya lanjut, tidak mengulang.
        $this->travelTo('2026-08-07 09:00:00'); // Jumat
        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A02');

        // Senin berikutnya seri dimulai lagi dari awal.
        $this->travelTo('2026-08-10 08:00:00');
        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A00');

        $this->travelBack();
    }

    public function test_kode_tidak_terpakai_ulang_setelah_transaksi_dihapus(): void
    {
        $this->travelTo('2026-08-05 10:00:00');

        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A00');
        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A01');

        // Transaksi tengah dihapus, jadi jumlah barisnya turun. Generator yang
        // cuma menghitung baris akan memberikan #A01 lagi ke pesanan
        // berikutnya, dan dua pesanan berbeda berbagi satu kode dalam minggu
        // yang sama.
        $dihapus = Transaksi::where('kode_pesanan', '#A00')->firstOrFail();
        $dihapus->detailTransaksi()->delete();
        $dihapus->delete();

        $this->postJson('/api/order', $this->payload())->assertJsonPath('kode_pesanan', '#A02');

        $this->assertSame(
            ['#A01', '#A02'],
            Transaksi::orderBy('id')->pluck('kode_pesanan')->all(),
        );

        $this->travelBack();
    }

    public function test_variasi_format_nomor_wa_memakai_customer_yang_sama(): void
    {
        $this->postJson('/api/order', $this->payload(['nomor_wa' => '0812-3456-7890']));
        $this->postJson('/api/order', $this->payload(['nomor_wa' => '+62 812 3456 7890']));
        $this->postJson('/api/order', $this->payload(['nomor_wa' => '812345 67890']));

        $this->assertSame(1, Customer::count());
        $this->assertSame('6281234567890', Customer::first()->no_wa);
    }

    public function test_validasi_kontrak_v1(): void
    {
        // items kosong
        $this->postJson('/api/order', $this->payload(['items' => []]))
            ->assertStatus(422)->assertJsonPath('error', 'items_kosong');

        // qty tidak valid
        $this->postJson('/api/order', $this->payload(['items' => [['menu_id' => $this->susu->id, 'qty' => 0]]]))
            ->assertStatus(422)->assertJsonPath('error', 'qty_invalid');

        // menu tidak ada / nonaktif
        $this->postJson('/api/order', $this->payload(['items' => [['menu_id' => 9999, 'qty' => 1]]]))
            ->assertStatus(422)->assertJsonPath('error', 'menu_tidak_tersedia');

        $this->susu->update(['is_active' => false]);
        $this->postJson('/api/order', $this->payload(['items' => [['menu_id' => $this->susu->id, 'qty' => 1]]]))
            ->assertStatus(422)->assertJsonPath('error', 'menu_tidak_tersedia');

        // nomor WA invalid
        $this->postJson('/api/order', $this->payload(['nomor_wa' => 'abc']))
            ->assertStatus(422)->assertJsonPath('error', 'nomor_wa_invalid');

        // field wajib hilang
        $this->postJson('/api/order', ['nama' => 'Budi'])
            ->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_status_pesanan_publik_ikut_berubah_saat_kasir_menandai_lunas(): void
    {
        $this->postJson('/api/order', $this->payload())->assertCreated();

        $this->getJson('/api/order/%23A00')
            ->assertOk()
            ->assertExactJson(['status' => 'pending']);

        Transaksi::first()->update(['status' => 'lunas']);

        $this->getJson('/api/order/%23A00')
            ->assertOk()
            ->assertExactJson(['status' => 'lunas']);
    }

    public function test_status_pesanan_tidak_membocorkan_data_pelanggan(): void
    {
        $this->postJson('/api/order', $this->payload())->assertCreated();

        $respon = $this->getJson('/api/order/%23A00')->assertOk();

        // Kode pesanan gampang ditebak, jadi response-nya tidak boleh berisi
        // apa pun selain status.
        $this->assertSame(['status'], array_keys($respon->json()));

        $mentah = $respon->getContent();
        foreach (['Budi', '6281234567890', 'Original', '55000'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $mentah);
        }
    }

    public function test_status_pesanan_menerima_kode_tanpa_pagar_dan_huruf_kecil(): void
    {
        $this->postJson('/api/order', $this->payload())->assertCreated();

        foreach (['%23A00', 'A00', 'a00', '%23a00'] as $bentuk) {
            $this->getJson("/api/order/{$bentuk}")
                ->assertOk()
                ->assertJsonPath('status', 'pending');
        }
    }

    public function test_status_pesanan_memakai_pesanan_terbaru_saat_kode_terpakai_ulang(): void
    {
        // Seri di-reset tiap minggu, jadi #A00 bisa ada lebih dari satu di
        // tabel. Yang dimaksud SoyaScan selalu yang paling baru.
        $this->travelTo('2026-08-05 10:00:00');
        $this->postJson('/api/order', $this->payload())->assertCreated();
        Transaksi::first()->update(['status' => 'lunas']);

        $this->travelTo('2026-08-12 10:00:00'); // minggu berikutnya

        $this->postJson('/api/order', $this->payload())
            ->assertCreated()
            ->assertJsonPath('kode_pesanan', '#A00');

        $this->assertSame(2, Transaksi::where('kode_pesanan', '#A00')->count());

        $this->getJson('/api/order/%23A00')
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->travelBack();
    }

    public function test_status_pesanan_kode_tidak_dikenal_404(): void
    {
        $this->getJson('/api/order/%23Z99')
            ->assertNotFound()
            ->assertJsonPath('error', 'pesanan_tidak_ditemukan');
    }

    public function test_status_pesanan_kasir_juga_bisa_dicek(): void
    {
        // Pesanan kasir dan SoyaScan kini berbagi satu seri kode, jadi yang
        // membedakan asalnya adalah kolom `sumber`, bukan huruf kodenya.
        $transaksi = Transaksi::create([
            'kode_pesanan' => '#B07',
            'sumber' => 'kasir',
            'total' => 17000,
            'status' => 'pending',
        ]);

        $this->getJson('/api/order/%23B07')
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $transaksi->update(['status' => 'batal']);

        $this->getJson('/api/order/%23B07')
            ->assertOk()
            ->assertJsonPath('status', 'batal');
    }

    public function test_qris_toko_bisa_diambil_publik_tanpa_auth(): void
    {
        \App\Models\PengaturanToko::create([
            'nama_toko' => 'GresSOY',
            'no_telepon' => '08123456789',
            'alamat' => 'Jl. Rahasia Internal 1',
            'qris_gambar' => 'qris/statis.png',
        ]);

        $respon = $this->getJson('/api/toko')->assertOk();

        $this->assertSame('GresSOY', $respon->json('data.nama_toko'));
        $this->assertStringContainsString('qris/statis.png', $respon->json('data.qris_url'));

        // Endpoint ini publik, jadi isinya hanya yang memang sudah dilihat
        // pelanggan. Nomor telepon, alamat, dan jejak pengubah tidak ikut.
        $this->assertSame(['nama_toko', 'qris_url'], array_keys($respon->json('data')));
        $this->assertStringNotContainsString('08123456789', $respon->getContent());
        $this->assertStringNotContainsString('Rahasia Internal', $respon->getContent());
    }

    public function test_qris_toko_null_saat_belum_diunggah(): void
    {
        $this->getJson('/api/toko')
            ->assertOk()
            ->assertJsonPath('data.qris_url', null);
    }

    public function test_qris_yang_diunggah_belakangan_tetap_terjangkau_pesanan_lama(): void
    {
        // Pesanan dibuat saat QRIS belum ada: response-nya memang null.
        $this->postJson('/api/order', $this->payload(['metode_bayar' => 'qris']))
            ->assertCreated()
            ->assertJsonPath('qris_url', null);

        // Manager baru mengunggah QRIS setelah pesanan itu masuk.
        \App\Models\PengaturanToko::create([
            'nama_toko' => 'GresSOY',
            'qris_gambar' => 'qris/statis.png',
        ]);

        // Layar pembayaran yang masih terbuka bertanya ulang lewat /api/toko,
        // jadi pelanggan tidak perlu memesan ulang cuma untuk melihat QRIS.
        $this->getJson('/api/toko')
            ->assertOk()
            ->assertJsonPath('data.qris_url', fn ($url) => str_contains((string) $url, 'qris/statis.png'));
    }

    public function test_menu_publik_terkelompok_per_kategori_hanya_yang_aktif(): void
    {
        $this->tahu->update(['is_active' => false]);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(1, 'kategori')
            ->assertJsonPath('kategori.0.nama', 'Soya Signature')
            ->assertJsonCount(1, 'kategori.0.menu')
            ->assertJsonPath('kategori.0.menu.0.harga', 17000);
    }
}
