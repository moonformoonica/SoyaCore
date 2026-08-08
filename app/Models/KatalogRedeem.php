<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kode
 * @property int $poin
 * @property bool $is_active
 * @property ?int $maks_potongan
 * @property ?int $min_subtotal
 * @property bool $is_custom Reward buatan manager, bukan bawaan katalog.
 * @property ?string $label
 * @property ?string $tipe
 * @property ?int $persen
 * @property ?string $kategori
 * @property ?string $menu
 * @property ?string $ukuran
 * @property ?int $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?User $updatedBy
 */
class KatalogRedeem extends Model
{
    protected $table = 'katalog_redeem';

    public const POIN_MIN = 1;

    public const POIN_MAX = 100_000;

    protected $fillable = [
        'kode',
        'poin',
        'maks_potongan',
        'min_subtotal',
        'is_active',
        'updated_by',
        // Hanya terisi pada reward buatan manager (`is_custom`). Untuk kode
        // bawaan semuanya null, strukturnya tetap dibaca dari
        // LoyaltyRedemptionCatalog::defaults().
        'is_custom',
        'label',
        'tipe',
        'persen',
        'kategori',
        'menu',
        'ukuran',
    ];

    protected $casts = [
        'poin' => 'integer',
        'maks_potongan' => 'integer',
        'min_subtotal' => 'integer',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
        'persen' => 'integer',
        'ukuran' => 'array',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
