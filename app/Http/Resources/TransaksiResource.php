<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaksiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_pesanan' => $this->kode_pesanan,
            'status' => $this->status,
            'sumber' => $this->sumber,
            'nomor_meja' => $this->nomor_meja,
            'platform' => $this->platform,
            'catatan' => $this->catatan,
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
            'subtotal' => $this->subtotal,
            'diskon_persen' => $this->diskon_persen,
            'diskon_nilai' => $this->diskon_nilai,
            'total' => $this->total,
            'metode_bayar' => $this->metode_bayar,
            'point_earned' => $this->point_earned,
            'waktu_lunas' => $this->waktu_lunas?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
