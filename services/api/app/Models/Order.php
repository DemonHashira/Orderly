<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'sales_channel_id',
        'created_by',
        'reference',
        'total_amount',
        'current_status',
        'internal_notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): Builder|HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): Builder|HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function shipment(): HasOne|Builder
    {
        return $this->hasOne(Shipment::class);
    }

    public function return(): HasOne|Builder
    {
        return $this->hasOne(ReturnOrder::class);
    }
}
