<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MemfilterRentangTanggal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter daftar transaksi manager (Blok A2 + A3 + C5).
 *
 * Seluruh filter opsional dan bisa digabung (AND). Dikumpulkan di FormRequest,
 * bukan dibaca mentah dari `$request->query()` di controller, supaya nilai yang
 * tidak dikenal ditolak 422 dengan pesan, bukan diam-diam menghasilkan daftar
 * kosong yang terlihat seperti "tidak ada transaksi hari ini".
 */
class IndexTransaksiRequest extends FormRequest
{
    use MemfilterRentangTanggal;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->aturanRentangTanggal(), [
            'urut' => ['nullable', 'in:terbaru,terlama'],

            'status' => ['nullable', 'in:pending,lunas,batal,batal_sebagian'],
            'sumber' => ['nullable', 'in:kasir,self_order'],
            'metode_bayar' => ['nullable', 'in:cash,qris'],
            // Dikirim sebagai string di query param, jadi 'true'/'false' ikut
            // diterima, bukan cuma 1/0 seperti aturan `boolean` bawaan.
            'ada_redeem' => ['nullable', 'in:true,false,1,0'],
            'cari' => ['nullable', 'string', 'max:100'],
            'total_min' => ['nullable', 'integer', 'min:0'],
            // `gte` baru dipasang kalau batas bawahnya ikut dikirim, sama
            // alasannya dengan `tanggal_selesai` di MemfilterRentangTanggal:
            // `gte:total_min` tanpa `total_min` menolak request yang sah.
            'total_max' => array_merge(
                ['nullable', 'integer', 'min:0'],
                $this->filled('total_min') ? ['gte:total_min'] : [],
            ),

            // Tidak divalidasi `exists:users,id`: akun kasir yang sudah dihapus
            // masih punya transaksi lama, dan manager berhak melihatnya.
            // Id yang tidak ada menghasilkan daftar kosong, bukan 422.
            'user_id' => ['nullable', 'integer'],
            'dibuat_oleh' => ['nullable', 'integer'],
            'dibayar_oleh' => ['nullable', 'integer'],

            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->pesanRentangTanggal(), [
            'urut.in' => 'urut harus terbaru atau terlama.',
            'status.in' => 'status harus salah satu dari: pending, lunas, batal, batal_sebagian.',
            'sumber.in' => 'sumber harus kasir atau self_order.',
            'metode_bayar.in' => 'metode_bayar harus cash atau qris.',
            'ada_redeem.in' => 'ada_redeem harus true atau false.',
            'total_max.gte' => 'total_max tidak boleh lebih kecil dari total_min.',
        ]);
    }

    public function urut(): string
    {
        return $this->validated()['urut'] ?? 'terbaru';
    }

    public function adaRedeem(): ?bool
    {
        $nilai = $this->validated()['ada_redeem'] ?? null;

        return $nilai === null ? null : filter_var($nilai, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Maksimum 200 baris per halaman, pagar yang sudah berlaku sebelum filter
     * ini ada, dipertahankan supaya satu request tidak bisa menarik seluruh
     * tabel.
     */
    public function perPage(): int
    {
        return min((int) ($this->validated()['per_page'] ?? 15), 200);
    }

    public function nilai(string $kunci): mixed
    {
        return $this->validated()[$kunci] ?? null;
    }
}
