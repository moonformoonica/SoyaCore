<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $table = 'menu';

    protected $fillable = [
        'kategori_id',
        'nama',
        'rasa',
        'harga',
        'ukuran',
        'is_active',
    ];

    protected $casts = [
        'harga' => 'integer',
        'is_active' => 'boolean',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'menu_id');
    }
}
