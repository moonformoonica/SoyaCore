<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loyalty extends Model
{
    protected $table = 'loyalty';

    protected $fillable = [
        'customer_id',
        'poin', // saldo poin aktual (1 poin per Rp 1.000, model M3)
    ];

    protected $casts = [
        'poin' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
