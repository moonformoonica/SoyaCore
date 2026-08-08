<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $nama
 * @property string $no_wa Sudah ternormalisasi lewat NomorWa::normalisasi().
 * @property ?string $email
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?Loyalty $loyalty
 * @property-read Collection<int, Transaksi> $transaksi
 */
class Customer extends Model
{
    protected $table = 'customer';

    protected $fillable = [
        'nama',
        'no_wa',
        'email',
    ];

    public function loyalty(): HasOne
    {
        return $this->hasOne(Loyalty::class, 'customer_id');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'customer_id');
    }
}
