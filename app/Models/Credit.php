<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credit extends Model
{
    protected $fillable = ['event_id', 'seller_id', 'sale_id', 'amount', 'settled_at', 'credit_payment_id'];

    protected $casts = ['amount' => 'decimal:2', 'settled_at' => 'datetime'];

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
