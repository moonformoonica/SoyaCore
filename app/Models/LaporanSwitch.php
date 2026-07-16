<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
