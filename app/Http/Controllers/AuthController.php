<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UbahPasswordRequest;
use App\Http\Requests\UpdateProfilRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw new ApiException('kredensial_salah', 'Email atau password salah.', 422);
        }

        if (! $user->is_active) {
            throw new ApiException('akun_nonaktif', 'Akun ini sudah dinonaktifkan. Hubungi manager.', 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->profil($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profil($request->user())]);
    }

    public function updateProfil(UpdateProfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json(['user' => $this->profil($user)]);
    }

    public function ubahPassword(UbahPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['password_lama'], $user->password)) {
            throw new ApiException('password_lama_salah', 'Password lama tidak cocok.', 422);
        }

        $user->update(['password' => $data['password_baru']]); // di-hash oleh cast 'hashed'

        $tokenSekarang = $request->user()->currentAccessToken();

        $user->tokens()
            ->when(
                $tokenSekarang instanceof PersonalAccessToken,
                fn ($q) => $q->whereKeyNot($tokenSekarang->getKey()),
            )
            ->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Perangkat lain yang masih login sudah dikeluarkan.',
        ]);
    }

    /**
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
        ];
    }
}
