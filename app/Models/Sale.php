<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'event_id', 'station_id', 'bill_no', 'user_id', 'seller_id',
        'payment_type', 'cash_amount', 'transfer_amount',
        'total', 'paid', 'change', 'status',
        'void_reason', 'voided_by', 'voided_at',
    ];

    protected $casts = [
        'cash_amount' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'change' => 'decimal:2',
        'voided_at' => 'datetime',
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

    /** ผู้ยกเลิกบิล */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** บิลถูกยกเลิกแล้ว */
    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * แก้ไข/ยกเลิกบิลนี้ได้หรือไม่
     * บิลที่ยกเลิกแล้ว หรือบิลเครดิตที่เชียร์เบียร์ชำระคืนแล้ว ห้ามแตะ
     */
    public function isEditable(): bool
    {
        return ! $this->isVoid() && ! $this->isCreditSettled();
    }

    public function isCredit(): bool
    {
        return $this->payment_type === 'credit';
    }

    /** ใบเครดิตที่ผูกกับบิลนี้ (มีเฉพาะบิลเครดิต) */
    public function credit(): HasOne
    {
        return $this->hasOne(Credit::class);
    }

    /** บิลเครดิตที่เชียร์เบียร์นำเงินมาชำระคืนแล้ว */
    public function isCreditSettled(): bool
    {
        return $this->isCredit() && $this->credit?->settled_at !== null;
    }

    /** จ่ายผสม เงินสด + เงินโอน ในบิลเดียว */
    public function isSplit(): bool
    {
        return $this->payment_type === 'split';
    }

    public function paymentLabel(): string
    {
        return match ($this->payment_type) {
            'cash' => 'เงินสด',
            'transfer' => 'เงินโอน',
            'split' => 'สด+โอน',
            'credit' => 'เครดิต',
            default => $this->payment_type,
        };
    }

    public function paymentBadge(): string
    {
        return match ($this->payment_type) {
            'cash' => 'success',
            'transfer' => 'info',
            'split' => 'primary',
            'credit' => 'warning',
            default => 'secondary',
        };
    }
}
