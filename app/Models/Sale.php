<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'event_id', 'station_id', 'bill_no', 'user_id', 'seller_id',
        'payment_type', 'total', 'paid', 'change', 'status',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'change' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function isCredit(): bool
    {
        return $this->payment_type === 'credit';
    }

    public function paymentLabel(): string
    {
        return match ($this->payment_type) {
            'cash' => 'เงินสด',
            'transfer' => 'เงินโอน',
            'credit' => 'เครดิต',
            default => $this->payment_type,
        };
    }

    public function paymentBadge(): string
    {
        return match ($this->payment_type) {
            'cash' => 'success',
            'transfer' => 'info',
            'credit' => 'warning',
            default => 'secondary',
        };
    }
}
