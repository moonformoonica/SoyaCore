<?php

namespace App\Http\Requests;

use App\Support\QrMenu;
use Illuminate\Foundation\Http\FormRequest;

class QrMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role manager dijaga middleware di route
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:svg,png'],
            // Dibatasi supaya satu request tidak bisa memaksa server menggambar
            // bitmap raksasa. 2048 px sudah lebih dari cukup untuk dicetak
            // seukuran tent card meja.
            'ukuran' => ['nullable', 'integer', 'min:64', 'max:'.QrMenu::UKURAN_MAKS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'format harus svg atau png.',
            'ukuran.max' => 'ukuran maksimal '.QrMenu::UKURAN_MAKS.' px.',
        ];
    }

    /**
     * Default SVG: QR ini akan dicetak, dan SVG tetap tajam di ukuran apa pun.
     *
     * TIDAK dinamai `format()` — nama itu sudah dipakai
     * `Illuminate\Http\Request::format($default = 'html')` untuk content
     * negotiation, dan menimpanya dengan signature berbeda adalah fatal error
     * saat class-nya di-load.
     */
    public function formatGambar(): string
    {
        return $this->validated()['format'] ?? 'svg';
    }

    public function ukuran(): int
    {
        return (int) ($this->validated()['ukuran'] ?? QrMenu::UKURAN_DEFAULT);
    }
}
