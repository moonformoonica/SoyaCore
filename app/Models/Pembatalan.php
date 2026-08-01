<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumen pembatalan/koreksi satu pesanan. Bukan refund, tidak ada kas keluar.
 * Lihat docs/pembatalan-pesanan.md.
 */
class Pembatalan extends Model
{
    protected $table = 'pembatalan';

    protected $fillable = [
        'transaksi_id',
        'user_id',
        'alasan',
        'nilai_dibatalkan',
        'poin_ditarik',
        'poin_dikembalikan',
    ];

    protected $casts = [
        'nilai_dibatalkan' => 'integer',
        'poin_ditarik' => 'integer',
        'poin_dikembalikan' => 'integer',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    /** Akun kasir yang memproses pembatalan ini. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PembatalanItem::class, 'pembatalan_id');
    }
}
