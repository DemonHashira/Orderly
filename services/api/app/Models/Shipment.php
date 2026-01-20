<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'courier',
        'tracking_number',
        'shipped_at',
        'delivered_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
