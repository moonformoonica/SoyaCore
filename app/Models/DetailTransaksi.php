<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailTransaksi extends Model
{
    protected $table = 'detail_transaksi';

    protected $fillable = [
        'transaksi_id',
        'menu_id',
        'qty',
        'harga_satuan',
        'subtotal',
        'is_reward',
    ];

    protected $casts = [
        'qty' => 'integer',
        'harga_satuan' => 'integer',
        'subtotal' => 'integer',
        'is_reward' => 'boolean',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }
}
