<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\ResetPasswordUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\LaporanTransaksi;
use App\Models\Pembatalan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manajemen akun kasir oleh manager.
 *
 * HAPUS HANYA UNTUK AKUN YANG BELUM PUNYA RIWAYAT. Akun kasir dirujuk
 * `transaksi.dibayar_oleh`, `transaksi.user_id`, `pembatalan.user_id`, dan
 * `laporan_transaksi.kasir_user_id`. Menghapus akun yang pernah bertransaksi
 * membuat laporan bulan-bulan sebelumnya kehilangan atribusi: omzet yang
 * tadinya atas nama seseorang berubah jadi "— (tanpa akun)" surut ke belakang,
 * dan angka yang sudah pernah dicetak tidak bisa lagi direproduksi.
 *
 * Jadi dua jalan yang berbeda, dan bedanya bukan selera:
 *
 * - Akun salah buat, salah ketik, atau dibuat untuk mencoba dan belum pernah
 *   dipakai bertransaksi: BOLEH dihapus permanen. Tidak ada laporan yang
 *   menunjuk ke sana, jadi tidak ada yang rusak.
 * - Kasir yang sudah pernah menutup pembayaran lalu berhenti kerja:
 *   DINONAKTIFKAN (`is_active = false`). Tidak bisa login lagi, riwayat
 *   penjualannya tetap utuh dan tetap bernama.
 *
 * `bisa_dihapus` ikut dikirim di daftar akun supaya tombolnya bisa dimatikan
 * lebih dulu, bukan baru ditolak setelah manager menekannya.
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('role')      // manager dulu, baru kasir
            ->orderBy('nama')
            ->get()
            ->map(fn (User $u) => $this->profil($u) + [
                'bisa_dihapus' => ! $this->punyaRiwayat($u),
            ]);

        return response()->json(['data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Default `true` sebenarnya sudah ada di kolomnya, tapi hanya di level
        // database, model hasil `create()` tidak ikut mengetahuinya, jadi
        // tanpa baris ini response akun baru berisi `is_active: null` dan
        // halaman akun merendernya seolah kasirnya nonaktif.
        $data['is_active'] ??= true;

        $user = User::create($data); // password di-hash oleh cast 'hashed'

        return response()->json(['user' => $this->profil($user)], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $this->pastikanBukanMenguncidiriSendiri($request, $user, $data);
        $this->pastikanMasihAdaManagerAktif($user, $data);

        $user->update($data);

        // Sanctum tidak ikut memeriksa `is_active` di tiap request; pengecekan
        // itu hanya ada di login. Tanpa mencabut tokennya, kasir yang baru
        // dinonaktifkan tetap bisa memakai aplikasi sampai tokennya kedaluwarsa
        // sendiri, yang justru saat itulah dia paling tidak boleh bisa.
        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $user->tokens()->delete();
        }

        return response()->json(['user' => $this->profil($user)]);
    }

    public function resetPassword(ResetPasswordUserRequest $request, User $user): JsonResponse
    {
        $user->update(['password' => $request->validated()['password_baru']]);

        // Password lama sudah tidak berlaku, jadi sesi yang masih memakainya
        // ikut ditutup. Termasuk perangkat yang mungkin justru jadi alasan
        // password ini di-reset.
        $user->tokens()->delete();

        return response()->json([
            'message' => "Password {$user->nama} berhasil direset. Semua perangkat yang masih login sudah dikeluarkan.",
        ]);
    }

    /**
     * Hapus permanen. Hanya untuk akun yang belum pernah menyentuh transaksi
     * apa pun; sisanya diarahkan ke nonaktifkan. Alasannya di docblock class.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            throw new ApiException(
                'tidak_bisa_hapus_diri_sendiri',
                'Akun sendiri tidak bisa dihapus. Minta manager lain yang melakukannya.',
                422,
            );
        }

        // Manager aktif terakhir dijaga dengan alasan yang sama seperti pada
        // nonaktifkan: menghapusnya mengunci seluruh pengelolaan toko, dan
        // pemulihannya menuntut akses database langsung.
        $this->pastikanMasihAdaManagerAktif($user, ['is_active' => false]);

        if ($this->punyaRiwayat($user)) {
            throw new ApiException(
                'akun_punya_riwayat',
                "{$user->nama} sudah pernah tercatat di transaksi atau laporan, jadi tidak bisa dihapus. "
                    .'Nonaktifkan saja: akunnya tidak bisa login lagi, tapi riwayat penjualannya tetap utuh di laporan.',
                422,
            );
        }

        $nama = $user->nama;

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => "Akun {$nama} dihapus permanen."]);
    }

    /**
     * Apakah akun ini sudah menyentuh data yang dibaca laporan.
     *
     * Keempat kolom diperiksa semuanya, bukan `transaksi` saja: kasir bisa
     * belum pernah menutup pembayaran tapi sudah pernah MEMPROSES pembatalan,
     * dan laporan pembatalan tetap menyebut namanya.
     */
    private function punyaRiwayat(User $user): bool
    {
        return Transaksi::where('dibayar_oleh', $user->id)->exists()
            || Transaksi::where('user_id', $user->id)->exists()
            || Pembatalan::where('user_id', $user->id)->exists()
            || LaporanTransaksi::where('kasir_user_id', $user->id)->exists();
    }

    /**
     * Manager tidak boleh menurunkan role atau menonaktifkan akunnya sendiri
     * lewat halaman ini. Kalau dia manager terakhir, tidak akan ada lagi yang
     * bisa mengembalikannya, sementara pemulihannya menuntut akses database
     * langsung yang di lokasi toko tidak tersedia.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanBukanMenguncidiriSendiri(Request $request, User $user, array $data): void
    {
        if ($request->user()->id !== $user->id) {
            return;
        }

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw new ApiException(
                'tidak_bisa_nonaktifkan_diri_sendiri',
                'Akun sendiri tidak bisa dinonaktifkan. Minta manager lain yang melakukannya.',
                422,
            );
        }

        if (array_key_exists('role', $data) && $data['role'] !== $user->role) {
            throw new ApiException(
                'tidak_bisa_ubah_role_sendiri',
                'Role akun sendiri tidak bisa diubah. Minta manager lain yang melakukannya.',
                422,
            );
        }
    }

    /**
     * Harus selalu tersisa minimal satu manager aktif. Tanpa penjagaan ini,
     * menonaktifkan atau menurunkan manager terakhir akan mengunci seluruh
     * pengelolaan toko: menu, pengaturan loyalty, dan laporan semuanya
     * manager-only.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanMasihAdaManagerAktif(User $user, array $data): void
    {
        if ($user->role !== 'manager' || ! $user->is_active) {
            return;
        }

        $turunRole = array_key_exists('role', $data) && $data['role'] !== 'manager';
        $dinonaktifkan = array_key_exists('is_active', $data) && ! $data['is_active'];

        if (! $turunRole && ! $dinonaktifkan) {
            return;
        }

        $managerAktifLain = User::query()
            ->where('role', 'manager')
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $managerAktifLain) {
            throw new ApiException(
                'manager_terakhir',
                'Ini satu-satunya manager aktif yang tersisa. Angkat manager lain dulu sebelum mengubah akun ini.',
                422,
            );
        }
    }

    /**
     * Bentuk yang sama dengan `AuthController::profil()`, ditambah
     * `is_active`: halaman manajemen akun perlu membedakan kasir yang masih
     * kerja dari yang sudah dinonaktifkan. Password tidak pernah ikut.
     *
     * @return array<string, mixed>
     */
    private function profil(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_telepon' => $user->no_telepon,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ];
    }
}
