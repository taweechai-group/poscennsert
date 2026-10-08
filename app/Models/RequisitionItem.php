<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id', 'product_id', 'quantity_requested', 'quantity_delivered', 'pack_quantity',
    ];

    protected $casts = [
        'pack_quantity' => 'integer',
    ];

    /** หมายเหตุหน่วยเบิก เช่น "2 แพ็ก" (null ถ้าเบิกเป็นหน่วยย่อยตรงๆ) */
    public function packNote(): ?string
    {
        if (! $this->pack_quantity) return null;
        $unit = $this->product?->pack_unit ?: 'แพ็ก';
        return $this->pack_quantity.' '.$unit;
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
