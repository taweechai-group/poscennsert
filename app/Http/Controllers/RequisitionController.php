<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Station;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function __construct(private StockService $stock) {}

    /** รายการใบเบิก — POS เห็นของตัวเอง, คลังเห็นทั้งหมด */
    public function index()
    {
        $user = Auth::user();
        $eventId = $this->eventId();

        $query = Requisition::where('event_id', $eventId)
            ->with('items.product', 'fromStation', 'toStation', 'requester')
            ->latest('id');

        if ($user->isCashier()) {
            $query->where('to_station_id', $user->station_id);
        }

        $requisitions = $query->paginate(30)->withQueryString();

        // สำหรับฟอร์มขอเบิก (ฝั่ง POS)
        $products = Product::where('event_id', $eventId)->where('is_active', true)->orderBy('sort_order')->get();

        return view('requisitions.index', compact('requisitions', 'products', 'user'));
    }

    /** POS ขอเบิกจากคลังกลาง */
    public function store(Request $request)
    {
        // ฟอร์มส่งมาทุกสินค้ารวมทั้งตัวที่กรอก 0 — คัดเฉพาะตัวที่ขอจริง (แพ็ก หรือ หน่วยย่อย > 0) ก่อน validate
        $request->merge([
            'items' => collect($request->input('items', []))
                ->filter(fn ($i) => (int) ($i['quantity'] ?? 0) > 0 || (int) ($i['pack_quantity'] ?? 0) > 0)
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'nullable|integer|min:0',
            'items.*.pack_quantity' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'กรุณาระบุจำนวนที่ต้องการเบิกอย่างน้อย 1 รายการ',
            'items.min' => 'กรุณาระบุจำนวนที่ต้องการเบิกอย่างน้อย 1 รายการ',
        ]);

        $user = Auth::user();
        abort_if(! $user->station, 403);
        $eventId = $user->station->event_id;
        $warehouse = Station::where('event_id', $eventId)->where('type', 'warehouse')->firstOrFail();

        // โหลด pack_size ของสินค้าที่ขอ ไว้คำนวณแพ็ก -> หน่วยย่อย (กันค่าปลอมจากฟอร์ม)
        $products = Product::where('event_id', $eventId)
            ->whereIn('id', collect($data['items'])->pluck('product_id'))
            ->get()->keyBy('id');

        DB::transaction(function () use ($data, $user, $eventId, $warehouse, $products) {
            $req = Requisition::create([
                'event_id' => $eventId,
                'code' => $this->nextCode($eventId),
                'from_station_id' => $warehouse->id,
                'to_station_id' => $user->station_id,
                'status' => 'pending',
                'requested_by' => $user->id,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']] ?? null;
                if (! $product) continue;

                $packSize = $product->hasPack() ? $product->pack_size : 0;
                $packQty = (int) ($item['pack_quantity'] ?? 0);
                $looseQty = (int) ($item['quantity'] ?? 0);

                // ยอดจริง (หน่วยย่อย) = แพ็ก*ขนาดแพ็ก + ที่กรอกเป็นหน่วยย่อยเพิ่ม
                $total = ($packSize > 0 ? $packQty * $packSize : 0) + $looseQty;
                if ($total < 1) continue;

                RequisitionItem::create([
                    'requisition_id' => $req->id,
                    'product_id' => $product->id,
                    'quantity_requested' => $total,
                    'pack_quantity' => ($packSize > 0 && $packQty > 0) ? $packQty : null,
                ]);
            }
        });

        return back()->with('success', 'ส่งคำขอเบิกไปยังคลังกลางแล้ว');
    }

    /** คลังกลางอนุมัติ + จ่ายสินค้า (โอนสต๊อก คลัง -> จุดขาย) */
    public function approve(Requisition $requisition, Request $request)
    {
        abort_unless(Auth::user()->isWarehouse() || Auth::user()->isAdmin(), 403);
        abort_unless($requisition->status === 'pending', 400, 'ใบเบิกนี้ถูกดำเนินการแล้ว');

        $user = Auth::user();
        $requisition->load('items.product');

        // จำนวนที่จ่ายจริง (ปรับได้ตามของที่มี) — key = requisition_item_id
        $delivered = $request->input('delivered', []);

        try {
            DB::transaction(function () use ($requisition, $user, $delivered) {
                foreach ($requisition->items as $item) {
                    $qty = (int) ($delivered[$item->id] ?? $item->quantity_requested);
                    if ($qty <= 0) { $item->update(['quantity_delivered' => 0]); continue; }

                    $whStock = $this->stock->balance($requisition->from_station_id, $item->product_id);
                    if ($qty > $whStock) {
                        throw new \RuntimeException("คลังกลางมี {$item->product->name} เหลือ {$whStock} ไม่พอจ่าย {$qty}");
                    }

                    // ออกจากคลัง
                    $this->stock->adjust(
                        $requisition->event_id, $requisition->from_station_id, $item->product_id,
                        -$qty, 'transfer_out', 'requisition', $requisition->id, $user->id,
                        "จ่ายตามใบเบิก {$requisition->code}",
                    );
                    // เข้าจุดขาย
                    $this->stock->adjust(
                        $requisition->event_id, $requisition->to_station_id, $item->product_id,
                        $qty, 'transfer_in', 'requisition', $requisition->id, $user->id,
                        "รับตามใบเบิก {$requisition->code}",
                    );

                    $item->update(['quantity_delivered' => $qty]);
                }

                $requisition->update([
                    'status' => 'delivered',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'delivered_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "อนุมัติและจ่ายสินค้าตามใบเบิก {$requisition->code} แล้ว");
    }

    public function reject(Requisition $requisition)
    {
        abort_unless(Auth::user()->isWarehouse() || Auth::user()->isAdmin(), 403);
        abort_unless($requisition->status === 'pending', 400);

        $requisition->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "ปฏิเสธใบเบิก {$requisition->code} แล้ว");
    }

    private function eventId(): int
    {
        $user = Auth::user();
        if ($user->station) return $user->station->event_id;
        return \App\Models\Event::where('status', 'active')->value('id') ?? \App\Models\Event::value('id');
    }

    private function nextCode(int $eventId): string
    {
        $count = Requisition::where('event_id', $eventId)->count() + 1;
        return 'REQ-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
