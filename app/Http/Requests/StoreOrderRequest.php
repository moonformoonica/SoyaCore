<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint publik (self-order, pelanggan belum login)
    }

    /**
     * Presence dasar divalidasi di sini (-> validasi_gagal); aturan dengan
     * kode error khusus kontrak v1 (items_kosong, qty_invalid,
     * menu_tidak_tersedia, nomor_wa_invalid) dicek di OrderService.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['required', 'string', 'max:25'],
            'nomor_meja' => ['required', 'string', 'max:20'],
            // Pilihan pelanggan di halaman Pesanan. Nilai tersimpan 'cash'|'qris'
            // (bukan 'tunai') — samakan dengan /bayar. nullable: klien lama yang
            // belum kirim field ini tetap jalan; kasir tetap mengonfirmasi
            // metode final saat Tandai Lunas.
            'metode_bayar' => ['nullable', 'in:cash,qris'],
            // sengaja TANPA 'required': items hilang/kosong ditangani
            // OrderService dengan kode error kontrak v1 'items_kosong'
            'items' => ['array'],
        ];
    }
}
