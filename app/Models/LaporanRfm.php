<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Snapshot RFM hasil impor CSV. Angka yang dipakai dashboard dan export
 * DIHITUNG ulang lewat RfmQuery, bukan dibaca dari sini, karena snapshot tidak
 * pernah berubah setelah impor. Tabel ini tinggal sebagai pembanding.
 *
 * @property int $id
 * @property string $nama_pelanggan
 * @property int $recency
 * @property int $frequency
 * @property int $total_pcs_dibeli
 * @property int $monetary
 * @property int $total_poin_loyalty
 * @property float $frequency_skor
 * @property int $r_score
 * @property int $f_score
 * @property int $m_score
 * @property int $rfm_total
 * @property string $segmen
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
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
