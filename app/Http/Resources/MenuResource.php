<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kategori_id' => $this->kategori_id,
            'kategori' => $this->whenLoaded('kategori', fn () => $this->kategori->nama),
            'nama' => $this->nama,
            'rasa' => $this->rasa,
            'ukuran' => $this->ukuran,
            'harga' => $this->harga,
            'is_active' => $this->is_active,
        ];
    }
}
