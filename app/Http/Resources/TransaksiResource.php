<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaksiResource extends JsonResource
{
    /**
     * Per revisi ERD: subtotal & diskon disimpan per item, jadi angka
     * level transaksi di response ini dihitung sebagai agregat item
     * (kecuali `total` yang tersimpan di kolom transaksi).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_pesanan' => $this->kode_pesanan,
            'status' => $this->status,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'nama' => $this->customer->nama,
                'no_wa' => $this->customer->no_wa,
            ]),
            'kasir' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'nama' => $this->user->nama,
            ]),
            'items' => DetailTransaksiResource::collection($this->whenLoaded('detailTransaksi')),
            'subtotal' => (int) $this->detailTransaksi->sum('subtotal'),
            'diskon_persen' => (int) ($this->detailTransaksi->max('diskon_persen') ?? 0),
            'diskon_nilai' => (int) $this->detailTransaksi->sum('diskon_nilai'),
            'total' => $this->total,
            'metode_bayar' => $this->metode_bayar,
            'kode_redeem' => $this->kode_redeem,
            'poin_ditukar' => $this->poin_ditukar,
            'point_earned' => $this->point_earned,
            'waktu_lunas' => $this->waktu_lunas?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
