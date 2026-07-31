<?php

namespace App\Http\Controllers;

use App\Http\Requests\CariCustomerRequest;
use App\Models\Customer;
use App\Support\NomorWa;
use App\Support\PolaCari;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    private const MIN_DIGIT_CARI = 3;

    public function cari(CariCustomerRequest $request): JsonResponse
    {
        $query = Customer::query()->with('loyalty');

        if (($noWa = $request->noWaInput()) !== null) {

            if (strlen(NomorWa::digit($noWa)) < self::MIN_DIGIT_CARI) {

                return response()->json(['data' => []]);
            }

            $normal = NomorWa::normalisasi($noWa);

            // kandidatCari() mengembalikan DUA bentuk: digit apa adanya (cocok
            // untuk potongan tengah/ekor, mis. 4 digit terakhir "8122") dan
            // hasil normalisasi ke 62 (cocok untuk nomor lengkap yang diketik
            // dalam ejaan lokal "0812…"). Keduanya di-OR karena dari potongan
            // digit saja mustahil ditebak mana yang dimaksud.
            $query->where(function ($grup) use ($noWa) {
                foreach (NomorWa::kandidatCari($noWa) as $kandidat) {
                    $grup->orWhere('no_wa', 'like', '%'.PolaCari::escape($kandidat).'%');
                }
            })

                ->orderByRaw('CASE WHEN no_wa = ? THEN 0 ELSE 1 END', [$normal]);
        }

        if (($nama = $request->namaInput()) !== null) {
            // LOWER() di kedua sisi: LIKE case-sensitive di PostgreSQL, jadi
            // tanpa ini "budi" tidak menemukan "Budi" di produksi walau lolos
            // test lokal (SQLite).
            $query->whereRaw('LOWER(nama) LIKE ?', [PolaCari::teks($nama)]);
        }

        $customers = $query->orderBy('nama')
            ->limit($request->limitOr(10))
            ->get();

        return response()->json([
            'data' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'nama' => $customer->nama,
                'no_wa' => $customer->no_wa,
                // Endpoint list read-only: poin kedaluwarsa cukup ditampilkan 0,
                // penolakannya sendiri tetap dilakukan saat redeem.
                'poin' => $customer->loyalty?->poinBerlaku() ?? 0,
            ])->all(),
        ]);
    }
}
