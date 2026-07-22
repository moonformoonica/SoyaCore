<?php

namespace App\Http\Controllers;

use App\Http\Requests\CariCustomerRequest;
use App\Models\Customer;
use App\Support\NomorWa;
use Illuminate\Http\JsonResponse;

/**
 * Pencarian customer untuk halaman Pesanan (kasir & manager).
 *
 * READ-ONLY: tidak membuat, mengubah, atau menghapus data apa pun —
 * dipakai kasir untuk auto-detect pelanggan lama vs baru sebelum
 * transaksi disusun.
 *
 * Beda dengan GET /api/loyalty/{nomorWa} (publik, SoyaScan): endpoint ini
 * butuh auth, mendukung pencarian per nama, dan mengembalikan 200 + data
 * kosong kalau tidak ketemu (bukan 404) supaya "belum terdaftar" jadi
 * state normal saat kasir masih mengetik, bukan error.
 */
class CustomerController extends Controller
{
    public function cari(CariCustomerRequest $request): JsonResponse
    {
        $query = Customer::query()->with('loyalty');

        if (($noWa = $request->noWaInput()) !== null) {
            // Dinormalisasi dulu supaya "0812...", "+62 812...", dan "812..."
            // menemukan customer yang sama seperti saat transaksi dibuat.
            $query->where('no_wa', NomorWa::normalisasi($noWa));
        }

        if (($nama = $request->namaInput()) !== null) {
            $query->where('nama', 'like', '%'.$this->escapeLike($nama).'%');
        }

        $customers = $query->orderBy('nama')
            ->limit($request->limitOr(10))
            ->get();

        return response()->json([
            'data' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'nama' => $customer->nama,
                'no_wa' => $customer->no_wa,
                'poin' => (int) ($customer->loyalty?->poin ?? 0),
            ])->all(),
        ]);
    }

    /**
     * Netralkan wildcard LIKE dari input user — tanpa ini kata kunci "%"
     * akan cocok ke semua customer.
     */
    private function escapeLike(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $nilai);
    }
}
