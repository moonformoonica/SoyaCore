<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePengaturanTokoRequest;
use App\Models\PengaturanToko;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan > Info Toko.
 *
 * Data di sini dipakai sebagai header nota dan laporan, jadi sengaja satu
 * sumber — bukan diketik ulang di tiap tempat yang butuh.
 *
 * Baca: kasir & manager (kasir yang mencetak notanya).
 * Tulis: manager saja — dijaga middleware 'role:manager' di routes.
 */
class PengaturanTokoController extends Controller
{
    /**
     * GET /api/pengaturan/toko
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload(PengaturanToko::current())]);
    }

    /**
     * PATCH /api/pengaturan/toko — manager only.
     */
    public function update(UpdatePengaturanTokoRequest $request): JsonResponse
    {
        // baris pertama dibuat dari nilai bawaan dulu, supaya PATCH parsial
        // (mis. hanya alamat) tidak menyimpan nama_toko kosong
        $toko = PengaturanToko::query()->orderBy('id')->first() ?? new PengaturanToko([
            'nama_toko' => PengaturanToko::DEFAULT_NAMA_TOKO,
            'jam_buka' => PengaturanToko::DEFAULT_JAM_BUKA,
            'jam_tutup' => PengaturanToko::DEFAULT_JAM_TUTUP,
        ]);

        $toko->fill($request->validated());
        $toko->updated_by = $request->user()->id;
        $toko->save();

        return response()->json(['data' => $this->payload($toko)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PengaturanToko $toko): array
    {
        return [
            'nama_toko' => $toko->nama_toko,
            'no_telepon' => $toko->no_telepon,
            'alamat' => $toko->alamat,
            'jam_buka' => PengaturanToko::jam($toko->jam_buka),
            'jam_tutup' => PengaturanToko::jam($toko->jam_tutup),
            'diperbarui_pada' => $toko->updated_at?->toIso8601String(),
            'diperbarui_oleh' => $toko->updated_by === null
                ? null
                : $toko->updatedBy?->nama,
        ];
    }
}
