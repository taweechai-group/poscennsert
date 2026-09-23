<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPayment extends Model
{
    protected $fillable = [
        'event_id', 'seller_id', 'amount', 'cash_amount', 'transfer_amount', 'received_by', 'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
