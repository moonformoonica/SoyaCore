<?php

namespace Tests\Feature;

use App\Models\PengaturanToko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Halaman Pengaturan: tab "Profil Saya" + tab "Info Toko".
 *
 * Tab "Pengaturan Struk" sengaja di luar lingkup — printer & auto-cetak
 * adalah preferensi perangkat kasir, bukan data yang perlu disimpan server.
 */
class PengaturanProfilTokoTest extends TestCase
{
    use RefreshDatabase;

    private function manager(string $password = 'password123'): User
    {
        return User::factory()->create([
            'nama' => 'Ghefira Meyta',
            'email' => 'ghefira@gmail.com',
            'role' => 'manager',
            'password' => $password,
        ]);
    }

    // ---------------------------------------------------------------
    // Profil Saya
    // ---------------------------------------------------------------

    public function test_me_membawa_no_telepon(): void
    {
        $manager = $this->manager();
        $manager->update(['no_telepon' => '+62 812 3456 789']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.nama', 'Ghefira Meyta')
            ->assertJsonPath('user.email', 'ghefira@gmail.com')
            ->assertJsonPath('user.no_telepon', '+62 812 3456 789')
            ->assertJsonPath('user.role', 'manager');
    }

    public function test_edit_profil_mengubah_nama_email_dan_telepon(): void
    {
        Sanctum::actingAs($this->manager());

        $this->patchJson('/api/me', [
            'nama' => 'Ghefira M.',
            'email' => 'ghefira.meyta@gmail.com',
            'no_telepon' => '+62 813 0000 111',
        ])
            ->assertOk()
            ->assertJsonPath('user.nama', 'Ghefira M.')
            ->assertJsonPath('user.email', 'ghefira.meyta@gmail.com')
            ->assertJsonPath('user.no_telepon', '+62 813 0000 111');

        $this->assertDatabaseHas('users', [
            'email' => 'ghefira.meyta@gmail.com',
            'no_telepon' => '+62 813 0000 111',
        ]);
    }

    public function test_edit_profil_sebagian_tidak_menghapus_field_lain(): void
    {
        $manager = $this->manager();
        $manager->update(['no_telepon' => '+62 812 3456 789']);
        Sanctum::actingAs($manager);

        $this->patchJson('/api/me', ['nama' => 'Ghefira M.'])
            ->assertOk()
            ->assertJsonPath('user.nama', 'Ghefira M.')
            ->assertJsonPath('user.email', 'ghefira@gmail.com')
            ->assertJsonPath('user.no_telepon', '+62 812 3456 789');
    }

    public function test_nomor_telepon_boleh_dikosongkan(): void
    {
        $manager = $this->manager();
        $manager->update(['no_telepon' => '+62 812 3456 789']);
        Sanctum::actingAs($manager);

        $this->patchJson('/api/me', ['no_telepon' => null])
            ->assertOk()
            ->assertJsonPath('user.no_telepon', null);
    }

    /**
     * Ini yang paling penting di endpoint profil: kasir tidak boleh bisa
     * mengangkat dirinya sendiri jadi manager, atau menghidupkan kembali
     * akun yang dinonaktifkan, lewat halaman profilnya sendiri.
     */
    public function test_role_dan_is_active_tidak_bisa_diubah_lewat_profil(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true]);
        Sanctum::actingAs($kasir);

        $this->patchJson('/api/me', [
            'nama' => 'Kasir Nakal',
            'role' => 'manager',
            'is_active' => false,
        ])->assertOk();

        $kasir->refresh();
        $this->assertSame('kasir', $kasir->role);
        $this->assertTrue($kasir->is_active);

        // dan benar-benar masih ditolak di endpoint manager-only
        $this->patchJson('/api/pengaturan/toko', ['nama_toko' => 'Toko Bajakan'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');
    }

    public function test_email_bentrok_ditolak_tapi_email_sendiri_boleh(): void
    {
        User::factory()->create(['email' => 'sudah@dipakai.com']);
        Sanctum::actingAs($this->manager());

        $this->patchJson('/api/me', ['email' => 'sudah@dipakai.com'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validasi_gagal');

        // menyimpan ulang email sendiri tidak dianggap bentrok
        $this->patchJson('/api/me', ['email' => 'ghefira@gmail.com'])
            ->assertOk()
            ->assertJsonPath('user.email', 'ghefira@gmail.com');
    }

    public function test_edit_profil_menolak_input_tidak_valid(): void
    {
        Sanctum::actingAs($this->manager());

        $this->patchJson('/api/me', ['email' => 'bukan-email'])->assertStatus(422);
        $this->patchJson('/api/me', ['nama' => ''])->assertStatus(422);
        $this->patchJson('/api/me', ['no_telepon' => str_repeat('9', 31)])->assertStatus(422);
        $this->patchJson('/api/me', [])->assertStatus(422); // tidak ada yang diubah

        $this->assertDatabaseHas('users', ['email' => 'ghefira@gmail.com', 'nama' => 'Ghefira Meyta']);
    }

    // ---------------------------------------------------------------
    // Ganti Password
    // ---------------------------------------------------------------

    public function test_ganti_password_berhasil_dan_password_baru_dipakai_login(): void
    {
        $manager = $this->manager('password123');
        Sanctum::actingAs($manager);

        $this->postJson('/api/me/password', [
            'password_lama' => 'password123',
            'password_baru' => 'rahasiabaru456',
            'password_baru_confirmation' => 'rahasiabaru456',
        ])->assertOk();

        $this->assertTrue(Hash::check('rahasiabaru456', $manager->fresh()->password));

        // login lama gagal, login baru berhasil
        $this->postJson('/api/login', ['email' => 'ghefira@gmail.com', 'password' => 'password123'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'kredensial_salah');

        $this->postJson('/api/login', ['email' => 'ghefira@gmail.com', 'password' => 'rahasiabaru456'])
            ->assertOk()
            ->assertJsonPath('user.email', 'ghefira@gmail.com');
    }

    public function test_password_lama_salah_ditolak_dengan_kode_error_sendiri(): void
    {
        $manager = $this->manager('password123');
        Sanctum::actingAs($manager);

        $this->postJson('/api/me/password', [
            'password_lama' => 'salahtebak',
            'password_baru' => 'rahasiabaru456',
            'password_baru_confirmation' => 'rahasiabaru456',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'password_lama_salah');

        $this->assertTrue(Hash::check('password123', $manager->fresh()->password));
    }

    public function test_ganti_password_menolak_konfirmasi_beda_pendek_dan_sama_dengan_lama(): void
    {
        $manager = $this->manager('password123');
        Sanctum::actingAs($manager);

        // konfirmasi tidak cocok
        $this->postJson('/api/me/password', [
            'password_lama' => 'password123',
            'password_baru' => 'rahasiabaru456',
            'password_baru_confirmation' => 'bedasendiri',
        ])->assertStatus(422)->assertJsonPath('error', 'validasi_gagal');

        // kurang dari 8 karakter
        $this->postJson('/api/me/password', [
            'password_lama' => 'password123',
            'password_baru' => 'pendek',
            'password_baru_confirmation' => 'pendek',
        ])->assertStatus(422);

        // sama persis dengan password lama
        $this->postJson('/api/me/password', [
            'password_lama' => 'password123',
            'password_baru' => 'password123',
            'password_baru_confirmation' => 'password123',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('password123', $manager->fresh()->password));
    }

    /**
     * Kalau password diganti karena akunnya diduga bocor, sesi penyusup
     * harus ikut mati — tapi tab yang sedang dipakai jangan ter-logout.
     */
    public function test_ganti_password_mencabut_token_lain_tapi_token_sendiri_tetap_hidup(): void
    {
        $manager = $this->manager('password123');

        $lain = $manager->createToken('perangkat-lain');
        $ini = $manager->createToken('perangkat-ini');

        $this->assertSame(2, $manager->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$ini->plainTextToken)
            ->postJson('/api/me/password', [
                'password_lama' => 'password123',
                'password_baru' => 'rahasiabaru456',
                'password_baru_confirmation' => 'rahasiabaru456',
            ])->assertOk();

        // Diperiksa di level DB: yang tersisa harus PERSIS token yang dipakai
        // barusan. Assertion lewat HTTP tidak dipakai untuk ini karena guard
        // Sanctum yang sudah resolve bertahan antar request dalam satu test,
        // sehingga token yang sudah dihapus masih terlihat valid.
        $this->assertSame([$ini->accessToken->getKey()], $manager->tokens()->pluck('id')->all());

        // token lain benar-benar hilang dari DB, bukan sekadar ditandai
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $lain->accessToken->getKey()]);

        // guard di-reset dulu supaya request berikut benar-benar mengecek token
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$lain->plainTextToken)
            ->getJson('/api/me')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Info Toko
    // ---------------------------------------------------------------

    public function test_info_toko_memakai_nilai_bawaan_saat_belum_disetel(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));

        $this->getJson('/api/pengaturan/toko')
            ->assertOk()
            ->assertJsonPath('data.nama_toko', "Gres'Soy")
            ->assertJsonPath('data.jam_buka', '08:00')
            ->assertJsonPath('data.jam_tutup', '20:00')
            ->assertJsonPath('data.no_telepon', null)
            ->assertJsonPath('data.alamat', null)
            ->assertJsonPath('data.diperbarui_pada', null)
            ->assertJsonPath('data.diperbarui_oleh', null);

        $this->assertDatabaseCount('pengaturan_toko', 0);
    }

    public function test_manager_simpan_info_toko_lengkap(): void
    {
        $manager = $this->manager();
        Sanctum::actingAs($manager);

        $this->patchJson('/api/pengaturan/toko', [
            'nama_toko' => "Gres'Soy Gresik",
            'no_telepon' => '+62 31 1234567',
            'alamat' => 'Jl. Usman Sadar No. 12, Gresik',
            'jam_buka' => '07:30',
            'jam_tutup' => '21:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.nama_toko', "Gres'Soy Gresik")
            ->assertJsonPath('data.no_telepon', '+62 31 1234567')
            ->assertJsonPath('data.alamat', 'Jl. Usman Sadar No. 12, Gresik')
            ->assertJsonPath('data.jam_buka', '07:30')
            ->assertJsonPath('data.jam_tutup', '21:00')
            ->assertJsonPath('data.diperbarui_oleh', 'Ghefira Meyta');

        // kasir langsung melihat data yang sama (dipakai header nota)
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
        $this->getJson('/api/pengaturan/toko')
            ->assertOk()
            ->assertJsonPath('data.nama_toko', "Gres'Soy Gresik")
            ->assertJsonPath('data.jam_buka', '07:30');
    }

    public function test_hanya_satu_baris_info_toko_dan_patch_sebagian_aman(): void
    {
        Sanctum::actingAs($this->manager());

        // simpan alamat saja — nama_toko harus jatuh ke bawaan, bukan kosong
        $this->patchJson('/api/pengaturan/toko', ['alamat' => 'Jl. Mawar 1'])
            ->assertOk()
            ->assertJsonPath('data.alamat', 'Jl. Mawar 1')
            ->assertJsonPath('data.nama_toko', "Gres'Soy")
            ->assertJsonPath('data.jam_buka', '08:00');

        // lalu ubah jam saja — alamat tidak boleh hilang
        $this->patchJson('/api/pengaturan/toko', ['jam_tutup' => '22:00'])
            ->assertOk()
            ->assertJsonPath('data.alamat', 'Jl. Mawar 1')
            ->assertJsonPath('data.jam_tutup', '22:00')
            ->assertJsonPath('data.jam_buka', '08:00');

        $this->assertDatabaseCount('pengaturan_toko', 1);
    }

    public function test_jam_tutup_boleh_lewat_tengah_malam(): void
    {
        Sanctum::actingAs($this->manager());

        $this->patchJson('/api/pengaturan/toko', ['jam_buka' => '08:00', 'jam_tutup' => '02:00'])
            ->assertOk()
            ->assertJsonPath('data.jam_buka', '08:00')
            ->assertJsonPath('data.jam_tutup', '02:00');
    }

    public function test_info_toko_menolak_input_tidak_valid(): void
    {
        Sanctum::actingAs($this->manager());

        $this->patchJson('/api/pengaturan/toko', ['nama_toko' => ''])->assertStatus(422);
        $this->patchJson('/api/pengaturan/toko', ['jam_buka' => '8 pagi'])->assertStatus(422);
        $this->patchJson('/api/pengaturan/toko', ['jam_buka' => '25:00'])->assertStatus(422);
        $this->patchJson('/api/pengaturan/toko', ['alamat' => str_repeat('a', 501)])->assertStatus(422);
        $this->patchJson('/api/pengaturan/toko', [])->assertStatus(422);

        $this->assertDatabaseCount('pengaturan_toko', 0);
    }

    public function test_kasir_boleh_baca_info_toko_tapi_tidak_boleh_ubah(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));

        $this->getJson('/api/pengaturan/toko')->assertOk();

        $this->patchJson('/api/pengaturan/toko', ['nama_toko' => 'Toko Kasir'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'tidak_berwenang');

        $this->assertDatabaseCount('pengaturan_toko', 0);
        $this->assertSame("Gres'Soy", PengaturanToko::current()->nama_toko);
    }

    public function test_semua_endpoint_pengaturan_butuh_login(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->patchJson('/api/me', ['nama' => 'X'])->assertStatus(401);
        $this->postJson('/api/me/password', [])->assertStatus(401);
        $this->getJson('/api/pengaturan/toko')->assertStatus(401);
        $this->patchJson('/api/pengaturan/toko', ['nama_toko' => 'X'])->assertStatus(401);
    }
}
