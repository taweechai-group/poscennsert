<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = ['name', 'location', 'event_date', 'status'];

    protected $casts = ['event_date' => 'date'];

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function warehouse(): ?Station
    {
        return $this->stations()->where('type', 'warehouse')->first();
    }

    public function posStations(): HasMany
    {
        return $this->hasMany(Station::class)->where('type', 'pos');
    }
}
