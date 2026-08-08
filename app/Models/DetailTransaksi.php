<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transaksi_id
 * @property int $menu_id
 * @property int $qty
 * @property int $harga_satuan
 * @property int $subtotal
 * @property bool $is_reward
 * @property string $sumber
 * @property ?string $platform
 * @property int $diskon_persen
 * @property int $diskon_nilai
 * @property ?string $level_sugar
 * @property ?string $level_ice
 * @property ?string $catatan
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Transaksi $transaksi
 * @property-read ?Menu $menu Null bila menunya sudah dihapus dari katalog.
 */
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
        'level_sugar',
        'level_ice',
        'sumber',
        'platform',
        'diskon_persen',
        'diskon_nilai',
        'catatan',
    ];

    protected $casts = [
        'qty' => 'integer',
        'harga_satuan' => 'integer',
        'subtotal' => 'integer',
        'is_reward' => 'boolean',
        'diskon_persen' => 'integer',
        'diskon_nilai' => 'integer',
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
