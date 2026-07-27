<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Info toko — singleton (tabel hanya berisi 0 atau 1 baris), pola sama
 * dengan PengaturanLoyalty.
 */
class PengaturanToko extends Model
{
    protected $table = 'pengaturan_toko';

    public const DEFAULT_NAMA_TOKO = "Gres'Soy";

    public const DEFAULT_JAM_BUKA = '08:00';

    public const DEFAULT_JAM_TUTUP = '20:00';

    protected $fillable = [
        'nama_toko',
        'no_telepon',
        'alamat',
        'jam_buka',
        'jam_tutup',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Baris info toko aktif. Kalau belum ada, mengembalikan instance BELUM
     * TERSIMPAN berisi nilai bawaan — pemanggil boleh membacanya seperti
     * baris biasa tanpa perlu tahu tabelnya masih kosong.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? new self([
            'nama_toko' => self::DEFAULT_NAMA_TOKO,
            'jam_buka' => self::DEFAULT_JAM_BUKA,
            'jam_tutup' => self::DEFAULT_JAM_TUTUP,
        ]);
    }

    /**
     * Kolom `time` dibaca berbeda tergantung driver: Postgres mengembalikan
     * "08:00:00", SQLite mengembalikan apa yang ditulis ("08:00"). Dipangkas
     * ke H:i di satu tempat supaya response API tidak ikut berbeda antara
     * lingkungan test dan produksi.
     */
    public static function jam(?string $nilai): ?string
    {
        return $nilai === null ? null : substr($nilai, 0, 5);
    }
}
