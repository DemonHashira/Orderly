<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnOrder extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'reason',
        'returned_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
