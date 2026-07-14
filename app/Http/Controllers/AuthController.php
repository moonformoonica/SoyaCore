<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    /**
     * @return array<string, mixed>
     */
    private function profil(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
