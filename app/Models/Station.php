<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    protected $fillable = ['event_id', 'name', 'type', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isWarehouse(): bool
    {
        return $this->type === 'warehouse';
    }

    /** ยอดคงเหลือของสินค้าหนึ่งชิ้นที่จุดนี้ */
    public function stockOf(int $productId): int
    {
        return (int) ($this->stocks()->where('product_id', $productId)->value('quantity') ?? 0);
    }
}
