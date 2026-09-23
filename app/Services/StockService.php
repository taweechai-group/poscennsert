<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;

/**
 * ศูนย์กลางการปรับสต๊อกทั้งหมด — ทุกการเปลี่ยนแปลงสต๊อกต้องผ่านที่นี่
 * เพื่อให้ยอดคงเหลือ (stocks) และ Audit log (stock_movements) ตรงกันเสมอ
 */
class StockService
{
    /**
     * ปรับสต๊อก (delta บวก = เข้า, ลบ = ออก) แล้วบันทึก movement
     */
    public function adjust(
        int $eventId,
        int $stationId,
        int $productId,
        int $delta,
        string $type,
        ?string $refType = null,
        ?int $refId = null,
        ?int $userId = null,
        ?string $note = null,
    ): int {
        $stock = Stock::firstOrCreate(
            ['station_id' => $stationId, 'product_id' => $productId],
            ['quantity' => 0],
        );

        $stock->quantity += $delta;
        $stock->save();

        StockMovement::create([
            'event_id' => $eventId,
            'station_id' => $stationId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $delta,
            'balance_after' => $stock->quantity,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'user_id' => $userId,
            'note' => $note,
        ]);

        return $stock->quantity;
    }

    /** ยอดคงเหลือปัจจุบัน */
    public function balance(int $stationId, int $productId): int
    {
        return (int) (Stock::where('station_id', $stationId)
            ->where('product_id', $productId)
            ->value('quantity') ?? 0);
    }
}
