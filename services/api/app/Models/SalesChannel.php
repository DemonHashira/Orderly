<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
