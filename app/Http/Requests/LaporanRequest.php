<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi params reporting. Superset dari semua param opsional endpoint
 * dashboard/export — tiap endpoint hanya membaca yang relevan.
 */
class LaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dicek middleware 'role:manager'
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'grain' => ['nullable', 'in:harian,mingguan,bulanan,tahunan'],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'by' => ['nullable', 'in:qty,revenue'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'segmen' => ['nullable', 'string', 'max:100'],
            'rekomendasi' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end.after_or_equal' => 'Tanggal end tidak boleh lebih awal dari start.',
            'grain.in' => 'Grain harus salah satu dari: harian, mingguan, bulanan, tahunan.',
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
}
