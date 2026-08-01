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
        'kasir_user_id',
        'kasir_nama',
        'catatan',
    ];

    /**
     * Prefix `kode` untuk baris hasil proyeksi transaksi POS SoyaCore.
     * Baris impor CSV historis berawalan `TR-`, jadi keduanya hidup
     * berdampingan di satu tabel tanpa saling menimpa, dan `laporan:proyeksi-ulang`
     * bisa menulis ulang miliknya sendiri tanpa menyentuh data lama.
     */
    public const PREFIX_POS = 'TRX-';

    protected $casts = [
        'tanggal' => 'date',
        'qty' => 'integer',
        'harga_satuan' => 'integer',
        'total' => 'integer',
        'poin_loyalty' => 'integer',
        'kasir_user_id' => 'integer',
    ];
}
