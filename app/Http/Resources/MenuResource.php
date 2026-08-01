<?php

namespace App\Http\Resources;

use App\Support\GolonganUkuran;
use App\Support\OpsiMinuman;
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
            // Dipakai halaman Edit Menu memisahkan kolom cup vs botol, dan
            // SoyaScan menentukan opsi peracikan. Diturunkan di backend supaya
            // frontend tidak menebak golongan dari string ukuran.
            'golongan_ukuran' => GolonganUkuran::dari($this->ukuran),
            'bisa_pilih_sugar' => OpsiMinuman::bisaPilihSugar($this->ukuran),
            'bisa_pilih_ice' => OpsiMinuman::bisaPilihIce($this->ukuran),
            // Pemanis menu ini, diturunkan dari `rasa` — beda per menu, jadi
            // tidak bisa diasumsikan selalu Gula Kelapa.
            'pemanis' => OpsiMinuman::keteranganPemanis($this->rasa),
            'harga' => $this->harga,
            'is_active' => $this->is_active,
        ];
    }
}
