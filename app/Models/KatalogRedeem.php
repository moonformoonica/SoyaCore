<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override satu item katalog redeem. Baris hanya ada untuk kode yang
 * PERNAH diedit manager — lihat catatan di migration-nya.
 *
 * Nilai bawaan tiap kode tetap di LoyaltyRedemptionCatalog::defaults();
 * kelas ini cuma menimpa `poin` dan `is_active`.
 */
class KatalogRedeem extends Model
{
    protected $table = 'katalog_redeem';

    /** Pagar salah ketik, sejalan dengan PengaturanLoyalty. */
    public const POIN_MIN = 1;

    public const POIN_MAX = 100_000;

    protected $fillable = [
        'kode',
        'poin',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'poin' => 'integer',
        'is_active' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
