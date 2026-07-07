<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loyalty extends Model
{
    protected $table = 'loyalty';

    protected $fillable = [
        'customer_id',
        'stempel',
        'total_gratis',
    ];

    protected $casts = [
        'stempel' => 'integer',
        'total_gratis' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
