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

        return view('warehouse.index', compact('warehouse', 'products', 'stations', 'matrix'));
    }

    /** รับสินค้าเข้าคลังกลาง (stock in) */
    public function receive(Request $request)
    {
        abort_unless(Auth::user()->isWarehouse() || Auth::user()->isAdmin(), 403);

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $eventId = $this->eventId();
        $warehouse = Station::where('event_id', $eventId)->where('type', 'warehouse')->firstOrFail();

        $this->stock->adjust(
            $eventId, $warehouse->id, $data['product_id'], $data['quantity'],
            'in', 'manual', null, Auth::id(), $data['note'] ?? 'รับสินค้าเข้าคลัง',
        );

        return back()->with('success', 'รับสินค้าเข้าคลังกลางเรียบร้อย');
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

    /** ประวัติการเคลื่อนไหวสต๊อก */
    public function movements(Request $request)
    {
        $eventId = $this->eventId();
        $movements = StockMovement::where('event_id', $eventId)
            ->with('station', 'product', 'user')
            ->latest()
            ->paginate(50);

        return view('warehouse.movements', compact('movements'));
    }

    private function eventId(): int
    {
        $user = Auth::user();
        if ($user->station) return $user->station->event_id;
        return Event::where('status', 'active')->value('id') ?? Event::value('id');
    }
}
