<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'sku',
        'description',
        'sale_price',
        'is_active',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inventoryStock(): HasOne|Builder
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function orderItems(): Builder|HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryMovements(): Builder|HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function returnItems(): Builder|HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }
}
