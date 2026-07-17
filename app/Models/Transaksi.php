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
        'total',
        'metode_bayar',
        'status',
        'point_earned',
        'waktu_lunas',
        'loyalty_applied_at',
        'kode_redeem',
        'poin_ditukar',
    ];

    protected $casts = [
        'total' => 'integer',
        'point_earned' => 'integer',
        'waktu_lunas' => 'datetime',
        'loyalty_applied_at' => 'datetime',
        'poin_ditukar' => 'integer',
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
