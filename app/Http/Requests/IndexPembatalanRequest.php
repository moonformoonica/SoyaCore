<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MemfilterRentangTanggal;
use Illuminate\Foundation\Http\FormRequest;

class IndexPembatalanRequest extends FormRequest
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
            // Akun kasir yang MEMPROSES pembatalan. Inilah pertanyaan yang
            // membuat daftar ini berguna: pembatalan berlebih dari satu akun
            // adalah pola yang perlu terlihat.
            'user_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->pesanRentangTanggal();
    }

    public function perPage(): int
    {
        return min((int) ($this->validated()['per_page'] ?? 15), 200);
    }

    public function userId(): ?int
    {
        $nilai = $this->validated()['user_id'] ?? null;

        return $nilai === null ? null : (int) $nilai;
    }
}
