<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanRevenueUkuran extends Model
{
    protected $table = 'laporan_revenue_ukuran';

    protected $fillable = [
        'ukuran',
        'jumlah_terjual',
        'total_revenue',
        'jumlah_transaksi',
        'rata_rata_transaksi',
    ];

    protected $casts = [
        'jumlah_terjual' => 'integer',
        'total_revenue' => 'integer',
        'jumlah_transaksi' => 'integer',
        'rata_rata_transaksi' => 'integer',
    ];
}
