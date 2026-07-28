<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePengaturanTokoRequest;
use App\Models\PengaturanToko;
use Illuminate\Http\JsonResponse;

class PengaturanTokoController extends Controller
{

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload(PengaturanToko::current())]);
    }

    public function update(UpdatePengaturanTokoRequest $request): JsonResponse
    {
        
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
