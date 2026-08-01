<?php

namespace Tests\Feature;

use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AkunKasirTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => 'manager', 'nama' => 'Ghefira']);
        $this->kasir = User::factory()->create(['role' => 'kasir', 'nama' => 'Evan']);
    }

    /**
     * Auth lewat token betulan, dan itu perlu dua hal sekaligus.
     *
     * `actingAs()` tidak dipakai karena ia menempel ke instance test dan
     * bertahan ke request berikutnya, test yang justru ingin membuktikan
     * sebuah token DITOLAK malah lulus karena masih dianggap user lama.
     *
     * `forgetGuards()` juga wajib: guard menyimpan user yang sudah diresolve
     * selama sisa test, jadi mengganti header `Authorization` saja tidak
     * mengubah siapa yang dikenali server.
     */
    private function pakaiToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function sebagaiManager(): self
    {
        return $this->pakaiToken($this->manager->createToken('api')->plainTextToken);
    }

    public function test_manager_bisa_membuat_akun_kasir_kedua(): void
    {
        $respon = $this->actingAs($this->manager)
            ->postJson('/api/users', [
                'nama' => 'Rani',
                'email' => 'kasir2@gressoy.test',
                'password' => 'rahasia123',
                'role' => 'kasir',
            ])
            ->assertCreated()
            ->assertJsonPath('user.nama', 'Rani')
            ->assertJsonPath('user.role', 'kasir')
            ->assertJsonPath('user.is_active', true);

        $this->assertArrayNotHasKey('password', $respon->json('user'));

        // Akun barunya benar-benar bisa dipakai login sendiri, itulah inti
        // dari punya akun terpisah.
        $this->postJson('/api/login', [
            'email' => 'kasir2@gressoy.test',
            'password' => 'rahasia123',
        ])->assertOk()->assertJsonPath('user.nama', 'Rani');
    }

    public function test_kasir_tidak_boleh_mengelola_akun(): void
    {
        $this->actingAs($this->kasir)->getJson('/api/users')
            ->assertStatus(403)->assertJsonPath('error', 'tidak_berwenang');

        $this->actingAs($this->kasir)->postJson('/api/users', [
            'nama' => 'Nakal',
            'email' => 'nakal@gressoy.test',
            'password' => 'rahasia123',
            'role' => 'manager',
        ])->assertStatus(403);

        // dan tetap tidak bisa mengangkat dirinya sendiri jadi manager
        $this->actingAs($this->kasir)
            ->patchJson("/api/users/{$this->kasir->id}", ['role' => 'manager'])
            ->assertStatus(403);

        $this->assertSame('kasir', $this->kasir->fresh()->role);
    }

    public function test_daftar_akun_tidak_pernah_memuat_password(): void
    {
        $respon = $this->actingAs($this->manager)->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertStringNotContainsString('password', $respon->getContent());
        $this->assertSame(
            ['id', 'nama', 'email', 'no_telepon', 'role', 'is_active', 'bisa_dihapus'],
            array_keys($respon->json('data.0')),
        );
    }

    public function test_email_tidak_boleh_dipakai_dua_akun(): void
    {
        $this->actingAs($this->manager)->postJson('/api/users', [
            'nama' => 'Kembar',
            'email' => $this->kasir->email,
            'password' => 'rahasia123',
            'role' => 'kasir',
        ])->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_menonaktifkan_kasir_langsung_memutus_sesinya(): void
    {
        // Token betulan, bukan actingAs(): yang diuji di sini justru apakah
        // token lama masih diterima setelah akunnya dinonaktifkan, dan
        // actingAs() melewati lapisan itu sama sekali.
        $token = $this->kasir->createToken('api')->plainTextToken;

        // Sebelum dinonaktifkan, tokennya jalan.
        $this->pakaiToken($token)
            ->getJson('/api/me')->assertOk();

        $this->sebagaiManager()
            ->patchJson("/api/users/{$this->kasir->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        // Sanctum tidak memeriksa is_active per request, jadi kalau tokennya
        // tidak ikut dicabut, kasir yang baru dinonaktifkan masih bisa jalan
        // terus memakai sesi lamanya.
        $this->pakaiToken($token)
            ->getJson('/api/me')->assertStatus(401);

        $this->postJson('/api/login', [
            'email' => $this->kasir->email,
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('error', 'akun_nonaktif');
    }

    public function test_manager_bisa_reset_password_kasir_yang_lupa(): void
    {
        $token = $this->kasir->createToken('api')->plainTextToken;

        $this->sebagaiManager()
            ->postJson("/api/users/{$this->kasir->id}/password", ['password_baru' => 'passwordbaru1'])
            ->assertOk();

        $this->assertTrue(Hash::check('passwordbaru1', $this->kasir->fresh()->password));

        // Sesi lama ikut ditutup, password lama sudah tidak berlaku.
        $this->pakaiToken($token)
            ->getJson('/api/me')->assertStatus(401);

        $this->postJson('/api/login', [
            'email' => $this->kasir->email,
            'password' => 'passwordbaru1',
        ])->assertOk();
    }

    public function test_manager_tidak_bisa_mengunci_dirinya_sendiri(): void
    {
        $this->actingAs($this->manager)
            ->patchJson("/api/users/{$this->manager->id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('error', 'tidak_bisa_nonaktifkan_diri_sendiri');

        $this->actingAs($this->manager)
            ->patchJson("/api/users/{$this->manager->id}", ['role' => 'kasir'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'tidak_bisa_ubah_role_sendiri');

        $this->assertTrue($this->manager->fresh()->is_active);
        $this->assertSame('manager', $this->manager->fresh()->role);
    }

    public function test_manager_aktif_terakhir_tidak_bisa_dinonaktifkan(): void
    {
        $managerLain = User::factory()->create(['role' => 'manager']);

        // Selama masih ada manager aktif lain, boleh.
        $this->actingAs($this->manager)
            ->patchJson("/api/users/{$managerLain->id}", ['is_active' => false])
            ->assertOk();

        // Sekarang $this->manager satu-satunya yang tersisa. Manager lain yang
        // sudah nonaktif diangkat lagi supaya ada yang bisa melakukan aksinya,
        // lalu giliran $this->manager yang dicoba diturunkan.
        $this->actingAs($this->manager)
            ->patchJson("/api/users/{$managerLain->id}", ['is_active' => true])
            ->assertOk();

        $this->actingAs($managerLain)
            ->patchJson("/api/users/{$this->manager->id}", ['is_active' => false])
            ->assertOk();

        // $managerLain kini satu-satunya manager aktif; dia mencoba menurunkan
        // dirinya lewat akun... tidak ada manager lain yang bisa. Uji lewat
        // penjagaan role pada akun itu sendiri:
        $this->actingAs($managerLain)
            ->patchJson("/api/users/{$managerLain->id}", ['role' => 'kasir'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'tidak_bisa_ubah_role_sendiri');

        $this->assertSame('manager', $managerLain->fresh()->role);
    }

    public function test_penjagaan_manager_terakhir_berlaku_lintas_akun(): void
    {
        // Manager kedua yang menurunkan manager pertama sementara dia sendiri
        // sudah nonaktif, tidak boleh menyisakan nol manager aktif.
        $managerNonaktif = User::factory()->create(['role' => 'manager', 'is_active' => false]);

        $this->actingAs($managerNonaktif)
            ->patchJson("/api/users/{$this->manager->id}", ['role' => 'kasir'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'manager_terakhir');

        $this->assertSame('manager', $this->manager->fresh()->role);
    }

    public function test_akun_belum_pernah_bertransaksi_bisa_dihapus(): void
    {
        $baru = User::factory()->create(['role' => 'kasir', 'nama' => 'Salah Ketik']);
        $token = $baru->createToken('api')->plainTextToken;

        // Backend menandai lebih dulu, supaya tombolnya bisa dimatikan di UI
        // dan penolakannya tidak datang setelah diklik.
        $daftar = collect($this->sebagaiManager()->getJson('/api/users')->json('data'))
            ->keyBy('nama');
        $this->assertTrue($daftar['Salah Ketik']['bisa_dihapus']);

        $this->sebagaiManager()
            ->deleteJson("/api/users/{$baru->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $baru->id]);

        // Tokennya ikut mati bersama akunnya.
        $this->pakaiToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_akun_yang_pernah_bertransaksi_tidak_bisa_dihapus(): void
    {
        Transaksi::create([
            'kode_pesanan' => '#A00',
            'sumber' => 'kasir',
            'total' => 17000,
            'status' => 'lunas',
            'dibayar_oleh' => $this->kasir->id,
        ]);

        $daftar = collect($this->sebagaiManager()->getJson('/api/users')->json('data'))
            ->keyBy('nama');
        $this->assertFalse($daftar['Evan']['bisa_dihapus']);

        $this->sebagaiManager()
            ->deleteJson("/api/users/{$this->kasir->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'akun_punya_riwayat');

        $this->assertDatabaseHas('users', ['id' => $this->kasir->id]);
    }

    public function test_riwayat_pembatalan_saja_sudah_menahan_penghapusan(): void
    {
        // Kasir bisa belum pernah menutup pembayaran tapi sudah memproses
        // pembatalan, dan laporan pembatalan tetap menyebut namanya.
        $transaksi = Transaksi::create([
            'kode_pesanan' => '#A00',
            'sumber' => 'kasir',
            'total' => 17000,
            'status' => 'batal',
        ]);

        Pembatalan::create([
            'transaksi_id' => $transaksi->id,
            'user_id' => $this->kasir->id,
            'alasan' => 'salah pesan',
            'nilai_dibatalkan' => 17000,
        ]);

        $this->sebagaiManager()
            ->deleteJson("/api/users/{$this->kasir->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'akun_punya_riwayat');
    }

    public function test_manager_tidak_bisa_menghapus_akunnya_sendiri(): void
    {
        $this->sebagaiManager()
            ->deleteJson("/api/users/{$this->manager->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'tidak_bisa_hapus_diri_sendiri');

        $this->assertDatabaseHas('users', ['id' => $this->manager->id]);
    }

    public function test_manager_aktif_terakhir_tidak_bisa_dihapus(): void
    {
        $managerLain = User::factory()->create(['role' => 'manager', 'nama' => 'Manager Cadangan']);

        // $this->manager memakai token milik dirinya, jadi yang dihapus di sini
        // adalah manager kedua. Selama masih ada dua, boleh.
        $this->sebagaiManager()->deleteJson("/api/users/{$managerLain->id}")->assertOk();

        // Sekarang tersisa satu manager aktif. Dia dicoba dihapus oleh kasir
        // yang tidak berwenang, lalu oleh manager itu sendiri: dua-duanya
        // ditolak, jadi toko tidak pernah kehabisan manager.
        $this->pakaiToken($this->kasir->createToken('api')->plainTextToken)
            ->deleteJson("/api/users/{$this->manager->id}")
            ->assertStatus(403);

        $this->sebagaiManager()
            ->deleteJson("/api/users/{$this->manager->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $this->manager->id]);
    }

    public function test_kasir_tidak_boleh_menghapus_akun(): void
    {
        $korban = User::factory()->create(['role' => 'kasir']);

        $this->pakaiToken($this->kasir->createToken('api')->plainTextToken)
            ->deleteJson("/api/users/{$korban->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $korban->id]);
    }

    public function test_update_tanpa_field_apa_pun_ditolak(): void
    {
        $this->actingAs($this->manager)
            ->patchJson("/api/users/{$this->kasir->id}", [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');
    }

    public function test_role_selain_kasir_dan_manager_ditolak(): void
    {
        $this->actingAs($this->manager)->postJson('/api/users', [
            'nama' => 'Aneh',
            'email' => 'aneh@gressoy.test',
            'password' => 'rahasia123',
            'role' => 'admin',
        ])->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');
    }
}
