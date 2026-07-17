<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Support\NomorWa;
use Illuminate\Http\JsonResponse;

/**
 * Cek poin loyalty publik (SoyaScan) — tanpa auth.
 *
 * BREAKING CHANGE dari kontrak v1 lama: response sekarang
 * {nomor_wa, nama, poin} — field stempel/gratis_tersedia/menuju_gratis
 * sudah tidak ada (model loyalty berubah dari stempel ke poin, M3).
 */
class LoyaltyController extends Controller
{
    public function show(string $nomorWa): JsonResponse
    {
        $noWa = NomorWa::normalisasi($nomorWa);

        $customer = Customer::where('no_wa', $noWa)->first();

        if ($customer === null) {
            throw new ApiException(
                'pelanggan_tidak_ditemukan',
                "Pelanggan dengan nomor WhatsApp {$nomorWa} belum terdaftar.",
                404,
            );
        }

        return response()->json([
            'nomor_wa' => $customer->no_wa,
            'nama' => $customer->nama,
            'poin' => (int) ($customer->loyalty?->poin ?? 0),
        ]);
    }
}
