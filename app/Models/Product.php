<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'event_id', 'name', 'sku', 'unit', 'price', 'cost',
        'image', 'icon', 'color', 'sort_order', 'is_active',
    ];

    /** URL รูปสินค้า (host-relative เพื่อให้ใช้ได้ทั้ง Herd และ artisan serve) */
    public function imageUrl(): ?string
    {
        return $this->image ? '/storage/'.$this->image : null;
    }

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
