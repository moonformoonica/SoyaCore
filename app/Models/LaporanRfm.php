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
        'monetary',
        'r_score',
        'f_score',
        'm_score',
        'rfm_total',
        'segmen',
    ];

    protected $casts = [
        'recency' => 'integer',
        'frequency' => 'integer',
        'monetary' => 'integer',
        'r_score' => 'integer',
        'f_score' => 'integer',
        'm_score' => 'integer',
        'rfm_total' => 'integer',
    ];
}
