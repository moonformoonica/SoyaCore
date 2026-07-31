<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadQrisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role manager dijaga middleware di route
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Dibatasi 2 MB: ini gambar QRIS statis yang dicetak/ditampilkan di
            // layar, bukan arsip. Berkas 10 MB hanya memperlambat halaman
            // pembayaran SoyaScan tanpa menambah ketajaman.
            'qris' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qris.required' => 'Berkas gambar QRIS wajib diunggah.',
            'qris.image' => 'Berkas QRIS harus berupa gambar (JPG atau PNG).',
            'qris.mimes' => 'Format QRIS harus JPG, JPEG, atau PNG.',
            'qris.max' => 'Ukuran gambar QRIS maksimal 2 MB.',
        ];
    }
}
