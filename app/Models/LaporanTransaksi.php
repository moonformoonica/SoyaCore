<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kolom tabel dideklarasikan sebagai PHPDoc karena Eloquent melayaninya lewat
 * `__get()`, bukan sebagai properti PHP sungguhan. Tanpa anotasi ini setiap
 * `$row->kode` ditandai analyzer sebagai properti tak dikenal (bertipe `mixed`),
 * dan yang lebih merepotkan: `$row->tanggal->format(...)` ikut tertandai karena
 * tipenya tidak diketahui, sehingga peringatan asli tenggelam di antara puluhan
 * peringatan palsu. Autocomplete nama kolom juga jadi jalan.
 *
 * @property int $id
 * @property string $kode
 * @property Carbon $tanggal
 * @property ?string $platform
 * @property ?string $nama_pelanggan
 * @property ?string $no_wa
 * @property string $nama_produk
 * @property ?string $rasa
 * @property ?string $ukuran
 * @property int $qty
 * @property int $harga_satuan
 * @property int $total
 * @property int $poin_loyalty
 * @property ?int $kasir_user_id
 * @property ?string $kasir_nama
 * @property ?string $catatan
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
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
