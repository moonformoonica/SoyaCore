<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manager menyetel ulang password kasir yang lupa. Sengaja TIDAK meminta
 * password lama — manager memang tidak mengetahuinya, dan itulah gunanya jalur
 * ini. Untuk mengganti password sendiri, {@see UbahPasswordRequest} tetap
 * menuntut password lama.
 */
class ResetPasswordUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password_baru' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_baru.required' => 'Password baru tidak boleh kosong.',
            'password_baru.min' => 'Password baru minimal 8 karakter.',
        ];
    }
}
