<?php

namespace App\Http\Resources;

use App\Models\User;
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
            // Label siap tampil ikut dikirim supaya halaman transaksi tidak
            // memetakan sendiri 'self_order' menjadi "SoyaScan", pemetaan yang
            // tersebar di beberapa halaman pasti berbeda ejaannya.
            'sumber_label' => $this->labelSumber(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'nama' => $this->customer->nama,
                'no_wa' => $this->customer->no_wa,
            ]),
            // Kasir yang MENYUSUN pesanan. Null untuk pesanan SoyaScan.
            'kasir_pembuat' => $this->whenLoaded('user', fn () => $this->ringkasUser($this->user)),
            // Kasir yang MENYELESAIKAN pembayaran, ke akun inilah penjualan
            // dihitung. Null selama transaksi masih pending.
            'kasir_penyelesai' => $this->whenLoaded('dibayarOleh', fn () => $this->ringkasUser($this->dibayarOleh)),
            // Key lama, dipertahankan supaya frontend yang sudah jalan tidak
            // rusak: penyelesai bila ada, jatuh ke pembuat bila belum dibayar.
            'kasir' => $this->whenLoaded('user', fn () => $this->ringkasUser($this->dibayarOleh ?? $this->user)),
            'items' => DetailTransaksiResource::collection($this->whenLoaded('detailTransaksi')),
            'subtotal' => (int) $this->detailTransaksi->sum('subtotal'),
            'diskon_persen' => (int) ($this->detailTransaksi->max('diskon_persen') ?? 0),
            'diskon_nilai' => (int) $this->detailTransaksi->sum('diskon_nilai'),
            'total' => $this->total,
            'metode_bayar' => $this->metode_bayar,
            'kode_redeem' => $this->kode_redeem,
            'poin_ditukar' => $this->poin_ditukar,
            'point_earned' => $this->point_earned,
            'rupiah_per_poin' => $this->rupiah_per_poin,
            'waktu_lunas' => $this->waktu_lunas?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, nama: string}|null
     */
    private function ringkasUser(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'nama' => $user->nama];
    }
}
