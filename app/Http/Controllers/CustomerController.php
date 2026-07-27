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
 *
 * Pencocokan nomor bersifat PARSIAL — dipakai kolom "Cek Poin Pelanggan"
 * untuk memunculkan nomor yang sudah terdaftar sebagai saran sejak kasir
 * baru mengetik sebagian, tanpa perlu hafal nomor lengkapnya.
 */
class CustomerController extends Controller
{
    /**
     * Minimal digit sebelum nomor dipakai mencari. Tanpa ini, mengetik "8"
     * akan mencocokkan hampir semua nomor Indonesia (semua tersimpan sebagai
     * 628xxx) — endpoint ini berubah jadi dump daftar pelanggan.
     */
    private const MIN_DIGIT_CARI = 3;

    public function cari(CariCustomerRequest $request): JsonResponse
    {
        $query = Customer::query()->with('loyalty');

        if (($noWa = $request->noWaInput()) !== null) {
            // Dinormalisasi dulu supaya "0812...", "+62 812...", dan "812..."
            // menemukan customer yang sama seperti saat transaksi dibuat.
            // Normalisasi tetap benar untuk input separuh: "0812" jadi "62812",
            // yang cocok sebagai awalan "6281234567890".
            // Diukur dari digit yang DIKETIK, bukan hasil normalisasi —
            // normalisasi menambahkan awalan "62", jadi satu ketikan "8"
            // akan terbaca 3 karakter dan lolos batas minimal.
            if (strlen(NomorWa::digit($noWa)) < self::MIN_DIGIT_CARI) {
                // Sengaja 200 + data kosong, bukan 422: kolom ini dipakai
                // sebagai saran-saat-mengetik, dan memunculkan error tiap
                // ketikan pertama akan mengganggu kasir.
                return response()->json(['data' => []]);
            }

            $normal = NomorWa::normalisasi($noWa);

            // Parsial supaya nomor terdaftar muncul sebagai saran sejak kasir
            // baru mengetik sebagian. Nomor lengkap tetap ketemu — LIKE
            // mencakup kecocokan persis.
            //
            // Dicocokkan ke SEMUA kandidat bentuk (lihat NomorWa::kandidatCari):
            // memakai hasil normalisasi saja membuat pencarian cuma jalan dari
            // depan, karena potongan yang diawali 0/8 keburu ditempeli "62" —
            // mengetik 4 digit terakhir "8122" tidak akan pernah ketemu.
            // Dibungkus closure supaya grup OR ini tidak bocor dan ber-OR
            // dengan filter `nama` di bawah.
            $query->where(function ($grup) use ($noWa) {
                foreach (NomorWa::kandidatCari($noWa) as $kandidat) {
                    $grup->orWhere('no_wa', 'like', '%'.$this->escapeLike($kandidat).'%');
                }
            })
                // yang persis sama naik ke atas, sisanya menyusul
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

    /**
     * Netralkan wildcard LIKE dari input user — tanpa ini kata kunci "%"
     * akan cocok ke semua customer.
     */
    private function escapeLike(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $nilai);
    }
}
