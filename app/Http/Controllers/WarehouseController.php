<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Product;
use App\Models\Station;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function __construct(private StockService $stock) {}

    /** หน้าคลังกลาง (มือถือ) — สต๊อกกลาง + ยอดคงเหลือทุกจุด */
    public function index()
    {
        $eventId = $this->eventId();
        $warehouse = Station::where('event_id', $eventId)->where('type', 'warehouse')->firstOrFail();
        $products = Product::where('event_id', $eventId)->orderBy('sort_order')->get();
        $stations = Station::where('event_id', $eventId)->orderBy('type', 'desc')->orderBy('name')->get();

        // ตาราง: product x station -> qty
        $matrix = [];
        $stocks = Stock::whereIn('station_id', $stations->pluck('id'))->get();
        foreach ($stocks as $s) {
            $matrix[$s->product_id][$s->station_id] = $s->quantity;
        }

        // มูลค่าสินค้าคงเหลือ — คิดเฉพาะสินค้าคืนได้ (น้ำแข็ง/คืนไม่ได้ = ทุนจม ไม่คิดมูลค่าคงเหลือ)
        // มูลค่า/สินค้า = (คงเหลือรวมทุกจุด) × cost เฉลี่ย
        $stockValue = [];   // product_id => มูลค่าคงเหลือ
        $totalStockValue = 0.0;
        foreach ($products as $p) {
            if (! $p->is_returnable) continue;
            $onHand = array_sum($matrix[$p->id] ?? []);
            $value = $onHand * (float) $p->cost;
            $stockValue[$p->id] = $value;
            $totalStockValue += $value;
        }

        return view('warehouse.index', compact(
            'warehouse', 'products', 'stations', 'matrix', 'stockValue', 'totalStockValue',
        ));
    }

    /** รับสินค้าเข้าคลังกลาง (stock in) + อัปเดตต้นทุน */
    public function receive(Request $request)
    {
        abort_unless(Auth::user()->isWarehouse() || Auth::user()->isAdmin(), 403);

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0', // ต้นทุน/หน่วย ของล็อตที่ซื้อเข้ามา
            'note' => 'nullable|string|max:255',
        ]);

        $eventId = $this->eventId();
        $warehouse = Station::where('event_id', $eventId)->where('type', 'warehouse')->firstOrFail();

        $product = Product::where('event_id', $eventId)->findOrFail($data['product_id']);
        $qty = (int) $data['quantity'];
        $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : null;

        DB::transaction(function () use ($eventId, $warehouse, $product, $qty, $unitCost, $data) {
            $this->stock->adjust(
                $eventId, $warehouse->id, $product->id, $qty,
                'in', 'manual', null, Auth::id(), $data['note'] ?? 'รับสินค้าเข้าคลัง',
                $unitCost,
            );

            // อัปเดตต้นทุนสินค้าจากล็อตที่รับเข้า (เฉพาะเมื่อกรอกต้นทุนมา)
            if ($unitCost !== null) {
                $this->applyReceivedCost($product, $qty, $unitCost, $eventId, $warehouse->id);
            }
        });

        return back()->with('success', 'รับสินค้าเข้าคลังกลางเรียบร้อย');
    }

    /**
     * อัปเดตต้นทุนสินค้าจากการรับเข้า
     *
     * สินค้าคืนของได้ (น้ำ/เบียร์): ต้นทุน/หน่วย = ต้นทุนเฉลี่ยถ่วงน้ำหนัก (moving average)
     *   cost ใหม่ = (จำนวนเดิม × cost เดิม + จำนวนรับเข้า × ต้นทุนล็อตใหม่) / (จำนวนเดิม + จำนวนรับเข้า)
     *   จำนวนเดิม = ยอดคงเหลือรวมทุกจุด "ก่อน" รับล็อตนี้
     *
     * สินค้าคืนไม่ได้ (น้ำแข็ง): ต้นทุนคิดทั้งก้อน → บวกทุนล็อตใหม่เข้า total_cost สะสม
     */
    private function applyReceivedCost(Product $product, int $qty, float $unitCost, int $eventId, int $warehouseId): void
    {
        $stationIds = Station::where('event_id', $eventId)->pluck('id');

        if ($product->is_returnable) {
            // ยอดคงเหลือรวมทุกจุด "ก่อน" รับล็อตนี้ (ขณะนี้ stock ถูกบวก qty ไปแล้ว จึงหักกลับ)
            $onHandAfter = (int) Stock::whereIn('station_id', $stationIds)
                ->where('product_id', $product->id)->sum('quantity');
            $prevQty = max(0, $onHandAfter - $qty);
            $prevCost = (float) $product->cost;

            $newTotalQty = $prevQty + $qty;
            $newCost = $newTotalQty > 0
                ? ($prevQty * $prevCost + $qty * $unitCost) / $newTotalQty
                : $unitCost;

            $product->update(['cost' => round($newCost, 2)]);
        } else {
            // คืนไม่ได้: ทุนจม → สะสมทุนที่ลงไปจริงทั้งหมด
            $product->update(['total_cost' => round((float) $product->total_cost + $qty * $unitCost, 2)]);
        }
    }

    /** ปรับสต๊อก (แก้ไขยอด) ของจุดใดก็ได้ */
    public function adjust(Request $request)
    {
        abort_unless(Auth::user()->isWarehouse() || Auth::user()->isAdmin(), 403);

        $data = $request->validate([
            'station_id' => 'required|exists:stations,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer', // ยอดใหม่ (ตั้งเป็น)
            'note' => 'nullable|string|max:255',
        ]);

        $eventId = $this->eventId();
        $current = $this->stock->balance($data['station_id'], $data['product_id']);
        $delta = $data['quantity'] - $current;

        if ($delta !== 0) {
            $this->stock->adjust(
                $eventId, $data['station_id'], $data['product_id'], $delta,
                'adjust', 'manual', null, Auth::id(),
                $data['note'] ?? "ปรับยอดจาก {$current} เป็น {$data['quantity']}",
            );
        }

        return back()->with('success', 'ปรับสต๊อกเรียบร้อย');
    }

    /** ประวัติการเคลื่อนไหวสต๊อก (แบ่งหน้า + กรองได้) */
    public function movements(Request $request)
    {
        $eventId = $this->eventId();

        $filters = $request->validate([
            'station_id' => 'nullable|integer|exists:stations,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'type' => 'nullable|string|in:in,out,sale,transfer_in,transfer_out,adjust',
            'per_page' => 'nullable|integer|in:25,50,100,200',
        ]);

        $perPage = (int) ($filters['per_page'] ?? 50);

        $movements = StockMovement::where('event_id', $eventId)
            ->with('station', 'product', 'user')
            ->when($filters['station_id'] ?? null, fn ($q, $v) => $q->where('station_id', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $stations = Station::where('event_id', $eventId)->orderBy('type', 'desc')->orderBy('name')->get();
        $products = Product::where('event_id', $eventId)->orderBy('sort_order')->get();

        return view('warehouse.movements', compact('movements', 'stations', 'products', 'filters', 'perPage'));
    }

    private function eventId(): int
    {
        $user = Auth::user();
        if ($user->station) return $user->station->event_id;
        return Event::where('status', 'active')->value('id') ?? Event::value('id');
    }
}
