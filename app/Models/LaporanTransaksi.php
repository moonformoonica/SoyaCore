<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanTransaksi extends Model
{
    protected $table = 'laporan_transaksi';

    protected $fillable = [
        'kode',
        'tanggal',
        'platform',
        'nama_pelanggan',
        'no_wa',
        'nama_produk',
        'rasa',
        'ukuran',
        'qty',
        'harga_satuan',
        'total',
        'poin_loyalty',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'qty' => 'integer',
        'harga_satuan' => 'integer',
        'total' => 'integer',
        'poin_loyalty' => 'integer',
    ];
}
