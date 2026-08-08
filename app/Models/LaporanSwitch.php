<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Snapshot rekomendasi switch ukuran hasil impor CSV. Sama seperti
 * {@see LaporanRfm}, angka yang dipakai dashboard dihitung ulang lewat
 * SwitchQuery.
 *
 * @property int $id
 * @property string $nama_pelanggan
 * @property ?string $rasa_favorit
 * @property ?string $ukuran_saat_ini
 * @property int $beli_reguler
 * @property int $beli_large
 * @property int $beli_botol
 * @property int $total_transaksi
 * @property float $qty_per_kunjungan
 * @property int $total_belanja
 * @property ?string $rekomendasi
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class LaporanSwitch extends Model
{
    protected $table = 'laporan_switch';

    protected $fillable = [
        'nama_pelanggan',
        'rasa_favorit',
        'ukuran_saat_ini',
        'beli_reguler',
        'beli_large',
        'beli_botol',
        'total_transaksi',
        'qty_per_kunjungan',
        'total_belanja',
        'rekomendasi',
    ];

    protected $casts = [
        'beli_reguler' => 'integer',
        'beli_large' => 'integer',
        'beli_botol' => 'integer',
        'total_transaksi' => 'integer',
        'qty_per_kunjungan' => 'float',
        'total_belanja' => 'integer',
    ];
}
