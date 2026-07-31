<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembatalanItem extends Model
{
    protected $table = 'pembatalan_item';

    protected $fillable = [
        'pembatalan_id',
        'detail_transaksi_id',
        'qty',
        'nilai_dibatalkan',
    ];

    protected $casts = [
        'qty' => 'integer',
        'nilai_dibatalkan' => 'integer',
    ];

    public function pembatalan(): BelongsTo
    {
        return $this->belongsTo(Pembatalan::class, 'pembatalan_id');
    }

    public function detailTransaksi(): BelongsTo
    {
        return $this->belongsTo(DetailTransaksi::class, 'detail_transaksi_id');
    }
}
