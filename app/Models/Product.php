<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'event_id', 'name', 'sku', 'unit', 'pack_size', 'pack_unit', 'price', 'cost',
        'is_returnable', 'total_cost', 'is_vip_eligible',
        'image', 'icon', 'color', 'sort_order', 'is_active',
    ];

    /** สินค้าตัวนี้เบิกเป็นแพ็กได้ไหม (ตั้ง pack_size > 1) */
    public function hasPack(): bool
    {
        return (int) $this->pack_size > 1;
    }

    /** URL รูปสินค้า (host-relative เพื่อให้ใช้ได้ทั้ง Herd และ artisan serve) */
    public function imageUrl(): ?string
    {
        return $this->image ? '/storage/'.$this->image : null;
    }

    protected $casts = [
        'pack_size' => 'integer',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_returnable' => 'boolean',
        'is_vip_eligible' => 'boolean',
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
