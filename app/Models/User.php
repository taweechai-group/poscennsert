<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'pin', 'role', 'station_id', 'is_active',
    ];

    protected $hidden = ['password', 'pin', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isWarehouse(): bool
    {
        return $this->role === 'warehouse';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }
}
