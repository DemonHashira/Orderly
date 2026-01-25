<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function orders(): Builder|HasMany
    {
        return $this->hasMany(Order::class);
    }
}
