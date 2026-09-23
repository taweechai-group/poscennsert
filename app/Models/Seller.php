<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    protected $fillable = [
        'event_id', 'code', 'name', 'phone', 'commission_rate', 'credit_limit', 'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    /** ยอดเครดิตที่เกิดทั้งหมด */
    public function totalCredit(): float
    {
        return (float) $this->credits()->sum('amount');
    }

    /** ยอดที่ชำระคืนแล้ว */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** ยอดคงค้าง */
    public function outstanding(): float
    {
        return $this->totalCredit() - $this->totalPaid();
    }

    /** ไม่จำกัดวงเงินเครดิต? (limit = 0) */
    public function hasUnlimitedCredit(): bool
    {
        return (float) $this->credit_limit <= 0;
    }

    /** วงเงินเครดิตที่ยังใช้ได้ (คงค้างเทียบกับลิมิต) */
    public function availableCredit(): float
    {
        if ($this->hasUnlimitedCredit()) {
            return PHP_FLOAT_MAX;
        }

        return max(0, (float) $this->credit_limit - $this->outstanding());
    }
}
