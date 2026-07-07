<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    protected $table = 'transaksi';

    protected $fillable = [
        'customer_id',
        'user_id',
        'kode_pesanan',
        'nomor_meja',
        'sumber',
        'platform',
        'subtotal',
        'diskon_persen',
        'diskon_nilai',
        'total',
        'metode_bayar',
        'status',
        'point_earned',
        'catatan',
        'waktu_lunas',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'diskon_persen' => 'integer',
        'diskon_nilai' => 'integer',
        'total' => 'integer',
        'point_earned' => 'integer',
        'waktu_lunas' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }
}
