<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_dengan_kredensial_benar_mengembalikan_token(): void
    {
        $user = User::factory()->create(['email' => 'kasir@gressoy.test']);

        $response = $this->postJson('/api/login', [
            'email' => 'kasir@gressoy.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'nama', 'email', 'role']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');
    }

    public function test_login_password_salah_ditolak_422(): void
    {
        User::factory()->create(['email' => 'kasir@gressoy.test']);

        $this->postJson('/api/login', [
            'email' => 'kasir@gressoy.test',
            'password' => 'salah-total',
        ])->assertStatus(422)->assertJsonPath('error', 'kredensial_salah');
    }

    public function test_login_akun_nonaktif_ditolak_403(): void
    {
        User::factory()->create([
            'email' => 'mantan@gressoy.test',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'mantan@gressoy.test',
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('error', 'akun_nonaktif');
    }

    public function test_endpoint_terproteksi_tanpa_token_ditolak_401(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_logout_mencabut_token_yang_sedang_dipakai(): void
    {
        User::factory()->create(['email' => 'kasir@gressoy.test']);

        $token = $this->postJson('/api/login', [
            'email' => 'kasir@gressoy.test',
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        // Guard meng-cache user dalam satu proses test — reset supaya
        // request berikutnya benar-benar re-resolve token dari DB.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
