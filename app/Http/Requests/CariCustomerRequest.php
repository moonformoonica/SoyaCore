<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CariCustomerRequest extends FormRequest
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
            'no_wa' => ['required_without:nama', 'nullable', 'string', 'max:25'],
            'nama' => ['required_without:no_wa', 'nullable', 'string', 'min:2', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'no_wa.required_without' => 'Isi minimal salah satu: no_wa atau nama.',
            'nama.required_without' => 'Isi minimal salah satu: no_wa atau nama.',
            'nama.min' => 'Kata kunci nama minimal 2 karakter.',
        ];
    }

    public function noWaInput(): ?string
    {
        return $this->validated()['no_wa'] ?? null;
    }

    public function namaInput(): ?string
    {
        return $this->validated()['nama'] ?? null;
    }

    public function limitOr(int $default): int
    {
        return (int) ($this->validated()['limit'] ?? $default);
    }
}
