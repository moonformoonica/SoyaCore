<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
