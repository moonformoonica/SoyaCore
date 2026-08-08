<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $nama_toko
 * @property ?string $no_telepon
 * @property ?string $alamat
 * @property ?string $jam_buka
 * @property ?string $jam_tutup
 * @property ?string $qris_gambar Path di disk `public`, bukan URL.
 * @property ?int $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?User $updatedBy
 */
class PengaturanToko extends Model
{
    protected $table = 'pengaturan_toko';

    public const DEFAULT_NAMA_TOKO = 'GresSOY';

    public const DEFAULT_JAM_BUKA = '08:00';

    public const DEFAULT_JAM_TUTUP = '20:00';

    protected $fillable = [
        'nama_toko',
        'no_telepon',
        'alamat',
        'jam_buka',
        'jam_tutup',
        'qris_gambar',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? new self([
            'nama_toko' => self::DEFAULT_NAMA_TOKO,
            'jam_buka' => self::DEFAULT_JAM_BUKA,
            'jam_tutup' => self::DEFAULT_JAM_TUTUP,
        ]);
    }

    public static function jam(?string $nilai): ?string
    {
        return $nilai === null ? null : substr($nilai, 0, 5);
    }

    /**
     * URL penuh gambar QRIS, `null` kalau belum diunggah.
     *
     * Kolomnya menyimpan path relatif pada disk `public`; URL-nya dibentuk saat
     * dibaca supaya berpindah domain (staging → produksi) tidak perlu menulis
     * ulang baris pengaturan.
     */
    public function qrisUrl(): ?string
    {
        return $this->qris_gambar === null ? null : Storage::disk('public')->url($this->qris_gambar);
    }
}
