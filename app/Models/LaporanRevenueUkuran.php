<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fixture revenue per ukuran dari CSV, dipakai test sebagai pembanding bahwa
 * perhitungan live mereproduksi angka aslinya. Ejaan `ukuran` di sini masih
 * ejaan CSV (`250 ml` pakai spasi), sedangkan API menyeragamkannya ke ejaan
 * katalog menu lewat GolonganUkuran::labelBaku().
 *
 * @property int $id
 * @property string $ukuran
 * @property int $jumlah_terjual
 * @property int $total_revenue
 * @property int $jumlah_transaksi
 * @property int $rata_rata_transaksi
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
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
