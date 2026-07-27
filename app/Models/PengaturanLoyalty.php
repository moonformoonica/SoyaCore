<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengaturan loyalty — singleton (tabel hanya berisi 0 atau 1 baris).
 *
 * Semantik `rupiah_per_poin`: PEMBAGI, bukan pengali.
 *   poin = intdiv(total_dibayar, rupiah_per_poin)
 * Jadi angka lebih BESAR = poin lebih SULIT didapat.
 * Contoh: rate 10.000 -> belanja Rp 50.000 dapat 5 poin (bukan 50).
 */
class PengaturanLoyalty extends Model
{
    protected $table = 'pengaturan_loyalty';

    /**
     * Nilai bawaan sejak M3 — dipakai selama manager belum pernah menyetel
     * rate. Satu-satunya tempat angka ini ditulis.
     */
    public const RUPIAH_PER_POIN_DEFAULT = 1000;

    /**
     * Batas aman input manager. Bukan aturan bisnis, tapi pagar supaya salah
     * ketik tidak langsung merusak ekonomi poin (rate 1 = tiap Rp 1 dapat
     * 1 poin) atau mematikan earning diam-diam.
     */
    public const RUPIAH_PER_POIN_MIN = 100;

    public const RUPIAH_PER_POIN_MAX = 1_000_000;

    protected $fillable = [
        'rupiah_per_poin',
        'updated_by',
    ];

    protected $casts = [
        'rupiah_per_poin' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Baris pengaturan aktif. Kalau belum ada, mengembalikan instance
     * BELUM TERSIMPAN berisi default — pemanggil boleh membacanya seperti
     * baris biasa tanpa perlu tahu tabelnya masih kosong.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? new self(['rupiah_per_poin' => self::RUPIAH_PER_POIN_DEFAULT]);
    }

    /**
     * Rate yang dipakai perhitungan poin. Dipisah dari current() supaya
     * pemanggil di jalur transaksi (LoyaltyService) tidak ikut membawa
     * model + relasi yang tidak dibutuhkan.
     *
     * Nilai < 1 dianggap tidak valid dan jatuh ke default. Validasi request
     * sudah menjaga batas bawah, tapi rate ini dipakai sebagai PEMBAGI di
     * jalur Tandai Lunas — kalau sampai 0 lolos (mis. diubah langsung di DB),
     * yang terjadi bukan poin salah hitung tapi pembayaran gagal total.
     */
    public static function rupiahPerPoin(): int
    {
        $rate = (int) static::query()->orderBy('id')->value('rupiah_per_poin');

        return $rate >= 1 ? $rate : self::RUPIAH_PER_POIN_DEFAULT;
    }
}
