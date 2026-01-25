<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    // Relationships
    public function users(): Builder|HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): Builder|HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function products(): Builder|HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): Builder|HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function inventoryStocks(): Builder|HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function inventoryMovements(): Builder|HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
