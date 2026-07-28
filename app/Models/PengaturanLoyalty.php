<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanLoyalty extends Model
{
    protected $table = 'pengaturan_loyalty';

    public const RUPIAH_PER_POIN_DEFAULT = 1000;

    public const RUPIAH_PER_POIN_MIN = 100;

    public const RUPIAH_PER_POIN_MAX = 1_000_000;

    protected $fillable = [
        'rupiah_per_poin',
        'updated_by',
    ];

    protected $casts = [
        'rupiah_per_poin' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? new self(['rupiah_per_poin' => self::RUPIAH_PER_POIN_DEFAULT]);
    }

    public static function rupiahPerPoin(): int
    {
        $rate = (int) static::query()->orderBy('id')->value('rupiah_per_poin');

        return $rate >= 1 ? $rate : self::RUPIAH_PER_POIN_DEFAULT;
    }
}
