<?php

namespace App\Http\Requests;

use App\Services\LaporanQuery;
use Illuminate\Foundation\Http\FormRequest;

class LaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'grain' => ['nullable', 'in:harian,mingguan,bulanan,tahunan,hari_dalam_minggu'],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'by' => ['nullable', 'in:qty,revenue'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Arah pengurutan produk. `terendah` menjawab "menu apa yang
            // kurang diminati", dan urutan array yang dikembalikan SAMA dengan
            // urutan batang di chart, jadi arahnya harus benar di sini.
            'arah' => ['nullable', 'in:tertinggi,terendah'],
            // Ikutkan menu aktif yang belum pernah terjual (qty 0). Dikirim
            // sebagai string di query param, sama seperti
            // sembunyikan_tidak_diketahui.
            'sertakan_nol' => ['nullable', 'in:true,false,1,0'],
            'segmen' => ['nullable', 'string', 'max:100'],
            'rekomendasi' => ['nullable', 'string', 'max:100'],
            // Keputusan TAMPILAN: membuang bucket tak berlabel dari chart.
            // Dikirim sebagai string di query param, jadi 'true'/'false' ikut
            // diterima, bukan cuma 1/0 seperti aturan `boolean` bawaan.
            'sembunyikan_tidak_diketahui' => ['nullable', 'in:true,false,1,0'],
            // Menyaring SELURUH sheet export ke satu akun kasir. Divalidasi
            // `exists` karena namanya ikut masuk ke nama file, id asal-asalan
            // menghasilkan file bernama aneh yang lebih membingungkan
            // daripada error.
            'kasir_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end.after_or_equal' => 'Tanggal end tidak boleh lebih awal dari start.',
            'grain.in' => 'Grain harus salah satu dari: harian, mingguan, bulanan, tahunan, hari_dalam_minggu.',
            'arah.in' => 'Arah harus tertinggi atau terendah.',
            'sertakan_nol.in' => 'sertakan_nol harus true atau false.',
            'sembunyikan_tidak_diketahui.in' => 'sembunyikan_tidak_diketahui harus true atau false.',
            'kasir_user_id.exists' => 'Akun kasir yang diminta tidak ditemukan.',
        ];
    }

    public function grain(): string
    {
        return $this->validated()['grain'] ?? 'harian';
    }

    public function startInput(): ?string
    {
        return $this->validated()['start'] ?? null;
    }

    public function endInput(): ?string
    {
        return $this->validated()['end'] ?? null;
    }

    public function by(): string
    {
        return $this->validated()['by'] ?? 'qty';
    }

    public function limitOr(int $default): int
    {
        return (int) ($this->validated()['limit'] ?? $default);
    }

    public function arah(): string
    {
        return $this->validated()['arah'] ?? LaporanQuery::ARAH_TERTINGGI;
    }

    public function sertakanNol(): bool
    {
        return filter_var(
            $this->validated()['sertakan_nol'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function sembunyikanTidakDiketahui(): bool
    {
        return filter_var(
            $this->validated()['sembunyikan_tidak_diketahui'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function kasirUserId(): ?int
    {
        $nilai = $this->validated()['kasir_user_id'] ?? null;

        return $nilai === null ? null : (int) $nilai;
    }
}
