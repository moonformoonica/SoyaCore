<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailTransaksiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'nama' => $this->whenLoaded('menu', fn () => $this->menu->nama),
            'rasa' => $this->whenLoaded('menu', fn () => $this->menu->rasa),
            'ukuran' => $this->whenLoaded('menu', fn () => $this->menu->ukuran),
            'qty' => $this->qty,
            'harga_satuan' => $this->harga_satuan,
            'subtotal' => $this->subtotal,
            'is_reward' => $this->is_reward,
            'nomor_meja' => $this->nomor_meja,
            'sumber' => $this->sumber,
            'platform' => $this->platform,
            'diskon_persen' => $this->diskon_persen,
            'diskon_nilai' => $this->diskon_nilai,
            'catatan' => $this->catatan,
        ];
    }
}
