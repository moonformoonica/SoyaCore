<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loyalty extends Model
{
    protected $table = 'loyalty';

    /**
     * Bonus sekali seumur pelanggan saat baris customer pertama kali dibuat.
     * Gunanya membuat reward pertama (diskon_10, 100 poin) tinggal satu
     * kunjungan lagi — pelanggan baru yang melihat saldo 0 dan reward termurah
     * di 100 poin cenderung berhenti mengumpulkan. Biayanya Rp 2.500 sekali,
     * bukan kebocoran berulang seperti menurunkan harga voucher.
     */
    public const POIN_BONUS_DAFTAR = 50;

    /**
     * Poin hangus kalau pelanggan tidak bertransaksi selama ini. Dihitung dari
     * transaksi TERAKHIR, bukan tanggal perolehan tiap poin — pelanggan yang
     * masih aktif tidak pernah kehilangan poin.
     */
    public const BULAN_KEDALUWARSA = 12;

    protected $fillable = [
        'customer_id',
        'poin',
        'poin_kedaluwarsa_pada',
    ];

    protected $casts = [
        'poin' => 'integer',
        'poin_kedaluwarsa_pada' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Baris lama dengan `poin_kedaluwarsa_pada` null = belum berlaku
     * kedaluwarsa. Poin yang dikumpulkan pelanggan sebelum fitur ini ada tidak
     * boleh hangus diam-diam.
     */
    public function sudahKedaluwarsa(): bool
    {
        return $this->poin_kedaluwarsa_pada !== null
            && $this->poin_kedaluwarsa_pada->isPast();
    }

    /**
     * Saldo yang boleh dipakai — dibaca tanpa menyentuh database, untuk
     * endpoint list yang cuma menampilkan.
     */
    public function poinBerlaku(): int
    {
        return $this->sudahKedaluwarsa() ? 0 : (int) $this->poin;
    }

    /**
     * Menolkan saldo yang sudah lewat masa berlakunya. Dipanggil di titik yang
     * memang menyentuh saldo (redeem, earn, cek saldo) supaya poin hangus
     * benar-benar hilang dari pembukuan, bukan cuma disembunyikan di tampilan.
     */
    public function hanguskanBilaKedaluwarsa(): void
    {
        if (! $this->sudahKedaluwarsa()) {
            return;
        }

        $this->forceFill(['poin' => 0, 'poin_kedaluwarsa_pada' => null])->save();
    }

    /**
     * Membuka baris loyalty untuk sebuah customer. Bonus pendaftaran hanya
     * diberikan kalau baris customer-nya memang baru dibuat di request ini —
     * pelanggan lama yang belum punya baris loyalty tetap mulai dari 0.
     */
    public static function bukaUntuk(Customer $customer): self
    {
        $bonus = $customer->wasRecentlyCreated ? self::POIN_BONUS_DAFTAR : 0;

        return self::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'poin' => $bonus,
                'poin_kedaluwarsa_pada' => $bonus > 0 ? now()->addMonths(self::BULAN_KEDALUWARSA) : null,
            ],
        );
    }
}
