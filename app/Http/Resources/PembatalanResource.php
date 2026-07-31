<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PembatalanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaksi_id' => $this->transaksi_id,
            'kode_pesanan' => $this->whenLoaded('transaksi', fn () => $this->transaksi->kode_pesanan),
            'alasan' => $this->alasan,
            'nilai_dibatalkan' => $this->nilai_dibatalkan,
            'poin_ditarik' => $this->poin_ditarik,
            'poin_dikembalikan' => $this->poin_dikembalikan,
            // Akun yang memproses pembatalan — bukan pembuat penjualan aslinya.
            'diproses_oleh' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'nama' => $this->user->nama,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'detail_transaksi_id' => $item->detail_transaksi_id,
                'nama' => $item->detailTransaksi?->menu?->nama,
                'ukuran' => $item->detailTransaksi?->menu?->ukuran,
                'is_reward' => (bool) $item->detailTransaksi?->is_reward,
                'qty' => $item->qty,
                'nilai_dibatalkan' => $item->nilai_dibatalkan,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
