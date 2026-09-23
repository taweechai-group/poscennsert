<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    protected $fillable = [
        'event_id', 'code', 'from_station_id', 'to_station_id', 'status',
        'requested_by', 'approved_by', 'note', 'approved_at', 'delivered_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function fromStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }

    public function toStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'delivered' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'รออนุมัติ',
            'approved' => 'อนุมัติแล้ว',
            'delivered' => 'จ่ายแล้ว',
            'rejected' => 'ปฏิเสธ',
            default => $this->status,
        };
    }
}
