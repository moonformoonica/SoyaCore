<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property ?int $customer_id
 * @property ?int $user_id Kasir PEMBUAT pesanan, tidak pernah ditimpa saat bayar.
 * @property ?int $dibayar_oleh Kasir yang menyelesaikan pembayaran.
 * @property string $kode_pesanan
 * @property int $total
 * @property ?string $metode_bayar
 * @property string $status
 * @property string $sumber
 * @property int $point_earned
 * @property ?int $rupiah_per_poin
 * @property ?string $kode_redeem
 * @property ?int $poin_ditukar
 * @property ?int $maks_potongan
 * @property ?Carbon $waktu_lunas
 * @property ?Carbon $loyalty_applied_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?Customer $customer
 * @property-read ?User $user
 * @property-read ?User $dibayarOleh
 * @property-read Collection<int, DetailTransaksi> $detailTransaksi
 * @property-read Collection<int, Pembatalan> $pembatalan
 * @property-read Collection<int, PembatalanItem> $pembatalanItem
 */
class Transaksi extends Model
{
    protected $table = 'transaksi';

    /**
     * Label siap tampil per channel, supaya frontend tidak memetakan sendiri
     * `self_order` menjadi "SoyaScan", pemetaan yang tersebar di beberapa
     * halaman pasti berbeda-beda ejaannya.
     */
    public const LABEL_SUMBER = [
        'kasir' => 'Kasir',
        'self_order' => 'SoyaScan',
    ];

    protected $fillable = [
        'customer_id',
        'user_id',
        'dibayar_oleh',
        'sumber',
        'kode_pesanan',
        'total',
        'metode_bayar',
        'status',
        'point_earned',
        'rupiah_per_poin',
        'waktu_lunas',
        'loyalty_applied_at',
        'kode_redeem',
        'poin_ditukar',
        'maks_potongan',
    ];

    protected $casts = [
        'total' => 'integer',
        'point_earned' => 'integer',
        'rupiah_per_poin' => 'integer',
        'waktu_lunas' => 'datetime',
        'loyalty_applied_at' => 'datetime',
        'poin_ditukar' => 'integer',
        'maks_potongan' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Kasir PEMBUAT pesanan. Null untuk pesanan SoyaScan, memang tidak ada
     * kasir yang menyusunnya.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Kasir yang MENYELESAIKAN pembayaran. Null selama transaksi masih pending.
     * Ke akun inilah penjualan dihitung, di titik itulah transaksi benar-benar
     * terjadi.
     */
    public function dibayarOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibayar_oleh');
    }

    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }

    public function pembatalan(): HasMany
    {
        return $this->hasMany(Pembatalan::class, 'transaksi_id');
    }

    /**
     * Seluruh baris item yang pernah dibatalkan pada transaksi ini, lintas
     * dokumen pembatalan. Ada supaya qty yang dibatalkan bisa dijumlahkan lewat
     * `withSum()` dalam satu query, bukan dengan me-loop pembatalan per
     * transaksi di laporan.
     */
    public function pembatalanItem(): HasManyThrough
    {
        return $this->hasManyThrough(
            PembatalanItem::class,
            Pembatalan::class,
            'transaksi_id',
            'pembatalan_id',
        );
    }

    public function labelSumber(): string
    {
        return self::LABEL_SUMBER[$this->sumber] ?? (string) $this->sumber;
    }

    /**
     * Akun yang dipakai laporan & proyeksi: penyelesai pembayaran, jatuh ke
     * pembuat kalau transaksinya belum dibayar.
     */
    public function kasirTerhitung(): ?User
    {
        return $this->dibayarOleh ?? $this->user;
    }
}
