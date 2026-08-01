<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Support\NomorWa;
use Illuminate\Http\JsonResponse;

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

        $loyalty = $customer->loyalty;

        // Saldo yang sudah lewat masa berlakunya dinolkan di sini juga, bukan
        // cuma disembunyikan, pelanggan dan pembukuan harus melihat angka yang
        // sama dengan yang berlaku saat redeem.
        $loyalty?->hanguskanBilaKedaluwarsa();

        return response()->json([
            'nomor_wa' => $customer->no_wa,
            'nama' => $customer->nama,
            'poin' => (int) ($loyalty?->poin ?? 0),
            // Dikirim supaya UI kasir bisa mengingatkan pelanggan sebelum
            // poinnya hangus. Null = belum berlaku kedaluwarsa.
            'poin_kedaluwarsa_pada' => $loyalty?->poin_kedaluwarsa_pada?->toIso8601String(),
        ]);
    }
}
