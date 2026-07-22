<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanRfm extends Model
{
    protected $table = 'laporan_rfm';

    protected $fillable = [
        'nama_pelanggan',
        'recency',
        'frequency',
        'total_pcs_dibeli',
        'monetary',
        'total_poin_loyalty',
        'frequency_skor',
        'r_score',
        'f_score',
        'm_score',
        'rfm_total',
        'segmen',
    ];

    protected $casts = [
        'recency' => 'integer',
        'frequency' => 'integer',
        'total_pcs_dibeli' => 'integer',
        'monetary' => 'integer',
        'total_poin_loyalty' => 'integer',
        'frequency_skor' => 'float',
        'r_score' => 'integer',
        'f_score' => 'integer',
        'm_score' => 'integer',
        'rfm_total' => 'integer',
    ];
}
