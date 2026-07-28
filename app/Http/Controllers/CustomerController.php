<?php

namespace App\Http\Controllers;

use App\Http\Requests\CariCustomerRequest;
use App\Models\Customer;
use App\Support\NomorWa;
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

            $query->where(function ($grup) use ($noWa) {
                foreach (NomorWa::kandidatCari($noWa) as $kandidat) {
                    $grup->orWhere('no_wa', 'like', '%'.$this->escapeLike($kandidat).'%');
                }
            })
                
                ->orderByRaw('CASE WHEN no_wa = ? THEN 0 ELSE 1 END', [$normal]);
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

    private function escapeLike(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $nilai);
    }
}
