<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedeemPoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth via Sanctum di route group
    }

    /**
     * Keanggotaan kode di katalog divalidasi LoyaltyService supaya bisa
     * mengembalikan kode error spesifik (kode_redeem_invalid).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'kode_redeem' => ['required', 'string', 'max:50'],
        ];
    }
}
