<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'event_id', 'station_id', 'product_id', 'type', 'quantity',
        'balance_after', 'ref_type', 'ref_id', 'user_id', 'note',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
