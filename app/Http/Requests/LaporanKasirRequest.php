<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MemfilterRentangTanggal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rentang tanggal laporan perbandingan kasir. Aturan dan pesan errornya
 * dipakai bersama daftar transaksi lewat {@see MemfilterRentangTanggal},
 * dua halaman yang bersebelahan tidak boleh punya dua definisi "7 hari".
 */
class LaporanKasirRequest extends FormRequest
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
        return $this->aturanRentangTanggal();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->pesanRentangTanggal();
    }
}
