<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\Event;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\Station;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /** ภาพรวม / Dashboard */
    public function dashboard()
    {
        $event = $this->currentEvent();
        $sales = Sale::where('event_id', $event->id)->where('status', 'completed');

        // เงินที่เชียร์เบียร์นำมาชำระคืน (ทั้งงานนี้)
        $settled = $this->creditSettlement($event->id);

        $stat = [
            'total' => (clone $sales)->sum('total'),
            // สด/โอน = ที่รับหน้าร้าน + ที่เก็บคืนจากเครดิต
            'cash' => (clone $sales)->sum('cash_amount') + $settled['cash'],
            'transfer' => (clone $sales)->sum('transfer_amount') + $settled['transfer'],
            // เครดิต = ยอดที่ยังค้างจริง เก็บคืนครบต้องเหลือ 0
            'credit' => (clone $sales)->where('payment_type', 'credit')->sum('total') - $settled['amount'],
            'credit_settled' => $settled['amount'],
            'bills' => (clone $sales)->count(),
        ];

        // ยอดขายแยกตามจุด
        $byStation = Sale::where('event_id', $event->id)
            ->where('status', 'completed')
            ->selectRaw('station_id, SUM(total) as total, COUNT(*) as bills')
            ->groupBy('station_id')
            ->with('station')
            ->get();

        // ยอดขายแยกตามสินค้า
        $byProduct = \App\Models\SaleItem::whereHas('sale', fn ($q) =>
                $q->where('event_id', $event->id)->where('status', 'completed'))
            ->selectRaw('product_name, SUM(quantity) as qty, SUM(subtotal) as total')
            ->groupBy('product_name')
            ->get();

        return view('admin.dashboard', compact('event', 'stat', 'byStation', 'byProduct'));
    }

    // ---------- สินค้า ----------
    public function products()
    {
        $event = $this->currentEvent();
        $products = Product::where('event_id', $event->id)->orderBy('sort_order')->get();

        return view('admin.products', compact('products', 'event'));
    }

    public function saveProduct(Request $request, ?Product $product = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'unit' => 'required|string|max:20',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'is_returnable' => 'nullable|boolean',        // คืนของได้ไหม (น้ำ/เบียร์ = ได้, น้ำแข็ง = ไม่ได้)
            'total_cost' => 'nullable|numeric|min:0',      // ทุนรวมที่ลงไป (สำหรับสินค้าคืนไม่ได้)
            'icon' => 'nullable|string|max:40',
            'color' => 'nullable|string|max:20',
            'image' => 'nullable|image|max:4096', // อัปโหลดรูป สูงสุด 4MB
        ]);

        // checkbox ไม่ติ๊ก = ไม่ส่งค่ามา → ถือว่าคืนของได้ (true)
        $data['is_returnable'] = $request->boolean('is_returnable');
        // ทุนรวมมีความหมายเฉพาะสินค้าคืนไม่ได้ ถ้าคืนได้บังคับเป็น 0 กันข้อมูลค้าง
        $data['total_cost'] = $data['is_returnable'] ? 0 : ($data['total_cost'] ?? 0);

        // อัปโหลดรูปสินค้า (ถ้ามี)
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']); // ไม่ให้เขียนทับรูปเดิมด้วยค่า null
        }

        if ($product && $product->exists) {
            // ลบรูปเก่าถ้าอัปโหลดรูปใหม่
            if (isset($data['image']) && $product->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
            }
            $product->update($data);
            $msg = 'แก้ไขสินค้าเรียบร้อย';
        } else {
            $event = $this->currentEvent();
            $p = Product::create(array_merge($data, [
                'event_id' => $event->id,
                'cost' => $data['cost'] ?? 0,
                'icon' => $data['icon'] ?? 'bi-box',
                'color' => $data['color'] ?? '#0d6efd',
                'sort_order' => Product::where('event_id', $event->id)->count(),
            ]));
            // สร้าง stock 0 ให้ทุกจุด
            foreach (Station::where('event_id', $event->id)->get() as $st) {
                Stock::firstOrCreate(['station_id' => $st->id, 'product_id' => $p->id], ['quantity' => 0]);
            }
            $msg = 'เพิ่มสินค้าเรียบร้อย';
        }

        return back()->with('success', $msg);
    }

    // ---------- จุดขาย ----------
    public function stations()
    {
        $event = $this->currentEvent();
        $stations = Station::where('event_id', $event->id)
            ->withCount('users')
            ->orderBy('type', 'desc')->orderBy('name')->get();

        return view('admin.stations', compact('stations', 'event'));
    }

    public function saveStation(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'required|in:pos,warehouse',
            'code' => 'nullable|string|max:20',
        ]);

        $event = $this->currentEvent();
        $station = Station::create(array_merge($data, ['event_id' => $event->id]));

        // สร้าง stock 0 ให้ทุกสินค้า
        foreach (Product::where('event_id', $event->id)->get() as $p) {
            Stock::firstOrCreate(['station_id' => $station->id, 'product_id' => $p->id], ['quantity' => 0]);
        }

        return back()->with('success', 'เพิ่มจุดขายเรียบร้อย');
    }

    // ---------- พนักงาน ----------
    public function users()
    {
        $event = $this->currentEvent();
        $users = User::orderBy('role')->orderBy('name')->get();
        $stations = Station::where('event_id', $event->id)->orderBy('name')->get();

        return view('admin.users', compact('users', 'stations', 'event'));
    }

    public function saveUser(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'role' => 'required|in:admin,cashier,warehouse',
            'pin' => 'required|string|min:4|max:6',
            'station_id' => 'nullable|exists:stations,id',
        ]);

        // กัน PIN ซ้ำ
        $dupe = User::where('is_active', true)->whereNotNull('pin')->get()
            ->first(fn ($u) => Hash::check($data['pin'], $u->pin));
        if ($dupe) {
            return back()->with('error', 'PIN นี้ถูกใช้แล้ว กรุณาเลือก PIN อื่น');
        }

        User::create([
            'name' => $data['name'],
            'role' => $data['role'],
            'pin' => $data['pin'],
            'station_id' => $data['role'] === 'cashier' ? $data['station_id'] : null,
            'is_active' => true,
        ]);

        return back()->with('success', 'เพิ่มพนักงานเรียบร้อย');
    }

    // ---------- สรุปยอด / ปิดงาน ----------
    public function report()
    {
        $event = $this->currentEvent();

        $sales = Sale::where('event_id', $event->id)->where('status', 'completed')->get();

        $settled = $this->creditSettlement($event->id);

        $summary = [
            'total' => $sales->sum('total'),
            'cash' => $sales->sum('cash_amount') + $settled['cash'],
            'transfer' => $sales->sum('transfer_amount') + $settled['transfer'],
            'credit' => $sales->where('payment_type', 'credit')->sum('total') - $settled['amount'],
            'credit_settled' => $settled['amount'],
            'bills' => $sales->count(),
        ];

        // คอมมิชชั่นเชียร์เบียร์
        $sellers = Seller::where('event_id', $event->id)->get()->map(function ($s) {
            $salesTotal = $s->sales()->where('status', 'completed')->sum('total');
            $s->sales_total = $salesTotal;
            $s->commission = $salesTotal * $s->commission_rate / 100;
            $s->outstanding = $s->outstanding();
            return $s;
        });

        // ยอดขายแยกตามจุดขาย แยกสด/โอน/เครดิต ไว้ให้แอดมินเช็คเงินแต่ละจุด
        $byStation = Sale::where('event_id', $event->id)
            ->where('status', 'completed')
            ->selectRaw('station_id')
            ->selectRaw('COUNT(*) as bills')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(cash_amount), 0) as cash')
            ->selectRaw('COALESCE(SUM(transfer_amount), 0) as transfer')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_type = 'credit' THEN total ELSE 0 END), 0) as credit")
            ->groupBy('station_id')
            ->with('station')
            ->get();

        // เครดิตที่เก็บคืนแล้วของแต่ละจุด เอามาหักออก เก็บครบต้องเหลือ 0
        $settledByStation = $this->creditSettlementByStation($event->id);

        $byStation = $byStation->map(function ($row) use ($settledByStation) {
            $paid = $settledByStation[(int) $row->station_id] ?? ['amount' => 0.0, 'cash' => 0.0, 'transfer' => 0.0];

            $row->credit_settled = $paid['amount'];
            // เครดิตคงค้าง = ที่ขายเป็นเครดิต - ที่เก็บคืนแล้ว
            $row->credit = (float) $row->credit - $paid['amount'];
            // สด/โอน = ที่รับหน้าร้าน + ที่เก็บคืนจากเครดิต (ให้ตรงกับเงินจริงในมือ)
            $row->cash = (float) $row->cash + $paid['cash'];
            $row->transfer = (float) $row->transfer + $paid['transfer'];

            return $row;
        })->sortByDesc('total')->values();

        // จุดไหนขายสินค้าอะไรได้กี่ชิ้น กี่บาท (ใช้ดูว่าแต่ละจุดขายอะไรดี)
        $stationProducts = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.event_id', $event->id)
            ->where('sales.status', 'completed')
            ->selectRaw('sales.station_id')
            ->selectRaw('sale_items.product_name')
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('SUM(sale_items.subtotal) as total')
            ->groupBy('sales.station_id', 'sale_items.product_name')
            ->orderByDesc('total')
            ->get()
            ->groupBy('station_id');

        // สต๊อกคงเหลือทุกจุด
        $stockLeft = Stock::whereHas('station', fn ($q) => $q->where('event_id', $event->id))
            ->with('station', 'product')
            ->get()
            ->groupBy(fn ($s) => $s->station->name);

        // ---------- กำไร แยกตามประเภทสินค้า ----------
        $profit = $this->profitReport($event->id);

        return view('admin.report', compact(
            'event', 'summary', 'sellers', 'stockLeft', 'byStation', 'stationProducts', 'profit'
        ));
    }

    /**
     * บิลขายรายใบของจุดขายหนึ่ง พร้อมกำไรต่อบิล (ทั้งงาน)
     *
     * กำไรต่อบิล = Σ(กำไรของรายการคืนได้) = subtotal − (ต้นทุน ณ ตอนขาย × จำนวน)
     * ** ไม่รวมทุนจมน้ำแข็ง (คืนไม่ได้) ** เพราะน้ำแข็งคิดทุนทั้งก้อน เฉลี่ยลงรายบิลไม่ได้
     * จึงเรียกว่า "กำไรขั้นต้น" และแยกยอดขายน้ำแข็งให้เห็นต่างหาก
     */
    public function stationSales(Station $station)
    {
        $event = $this->currentEvent();
        abort_unless($station->event_id === $event->id, 404);

        $sales = Sale::where('station_id', $station->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->with(['items', 'seller'])
            ->latest('id')
            ->get();

        // คำนวณกำไรขั้นต้นต่อบิล (เฉพาะรายการคืนได้ที่มีต้นทุนต่อหน่วย)
        $totalRevenue = 0.0;
        $totalGrossProfit = 0.0;
        foreach ($sales as $sale) {
            $revenue = 0.0;
            $cost = 0.0;
            foreach ($sale->items as $item) {
                $revenue += (float) $item->subtotal;
                // cost ถูก snapshot ไว้เฉพาะของคืนได้ (น้ำแข็ง = null → ไม่คิดต้นทุนต่อบิล)
                if ($item->cost !== null) {
                    $cost += (float) $item->cost * (int) $item->quantity;
                }
            }
            $sale->bill_cost = $cost;
            $sale->bill_profit = $revenue - $cost;

            $totalRevenue += $revenue;
            $totalGrossProfit += $sale->bill_profit;
        }

        $stat = [
            'bills' => $sales->count(),
            'revenue' => $totalRevenue,
            'cost' => $totalRevenue - $totalGrossProfit,
            'gross_profit' => $totalGrossProfit,
        ];

        return view('admin.station-sales', compact('event', 'station', 'sales', 'stat'));
    }

    /**
     * รายงานกำไรแยกตามประเภทสินค้า
     *
     * สินค้าคืนของได้ (น้ำ/เบียร์): ของเหลือคืนได้ ต้นทุนคิดเฉพาะที่ขายจริง
     *   กำไร = ยอดขาย − (cost/หน่วย × จำนวนที่ขาย)
     *
     * สินค้าคืนไม่ได้ (น้ำแข็ง): ของเหลือละลายทิ้ง ต้นทุนคิดทั้งก้อน
     *   กำไร = ยอดขาย − total_cost (ทุนรวมที่แอดมินกรอก)
     *
     * @return array{items: array, returnable_profit: float, nonreturnable_profit: float, total_profit: float, total_revenue: float, total_cost: float}
     */
    private function profitReport(int $eventId): array
    {
        // ยอดขายรวม (qty + subtotal + ต้นทุนขายจริง) ต่อสินค้า จากบิลที่ completed เท่านั้น
        //
        // cogs = ต้นทุนขาย = Σ(ต้นทุน/หน่วย ณ ตอนขาย × จำนวน) — ใช้ cost ที่ snapshot ไว้ใน sale_items
        // บิลเก่าก่อนมีฟีเจอร์นี้ (sale_items.cost = null) → fallback เป็น products.cost ปัจจุบัน
        // ค่านี้ใช้เฉพาะสินค้าคืนได้ ส่วนน้ำแข็ง/คืนไม่ได้คิดต้นทุนทั้งก้อนจาก total_cost จึงไม่พึ่ง cogs
        $sold = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.event_id', $eventId)
            ->where('sales.status', 'completed')
            ->selectRaw('sale_items.product_id')
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('SUM(sale_items.subtotal) as revenue')
            ->selectRaw('SUM(COALESCE(sale_items.cost, products.cost) * sale_items.quantity) as cogs')
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        $items = [];
        $returnableProfit = 0.0;
        $nonReturnableProfit = 0.0;
        $totalRevenue = 0.0;
        $totalCost = 0.0;

        // ไล่ทุกสินค้าในงาน (รวมที่ยังไม่ขาย เพื่อให้เห็นน้ำแข็งที่ลงทุนแต่ยังขายไม่ได้ = ติดลบ)
        $products = Product::where('event_id', $eventId)->orderBy('sort_order')->get();

        foreach ($products as $p) {
            $row = $sold->get($p->id);
            $qty = (int) ($row->qty ?? 0);
            $revenue = (float) ($row->revenue ?? 0);

            if ($p->is_returnable) {
                // คืนได้: ต้นทุน = ต้นทุน/หน่วย ณ ตอนขายจริง × จำนวน (snapshot ไว้ใน sale_items)
                // ไม่ใช้ cost ปัจจุบัน เพื่อไม่ให้บิลเก่าถูกคิดต้นทุนใหม่ย้อนหลังเมื่อรับของล็อตใหม่ราคาต่าง
                $cost = (float) ($row->cogs ?? 0);
                $returnableProfit += $revenue - $cost;
            } else {
                // คืนไม่ได้: ต้นทุน = ทุนรวมทั้งก้อน (ไม่ว่าจะขายหมดหรือไม่)
                $cost = (float) $p->total_cost;
                $nonReturnableProfit += $revenue - $cost;
            }

            // ข้ามสินค้าที่ไม่มีทั้งยอดขายและทุน (ไม่มีอะไรให้รายงาน)
            if ($qty === 0 && $cost == 0.0) {
                continue;
            }

            $totalRevenue += $revenue;
            $totalCost += $cost;

            $items[] = [
                'name' => $p->name,
                'unit' => $p->unit,
                'is_returnable' => $p->is_returnable,
                'qty' => $qty,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $revenue - $cost,
            ];
        }

        return [
            'items' => $items,
            'returnable_profit' => $returnableProfit,
            'nonreturnable_profit' => $nonReturnableProfit,
            'total_profit' => $returnableProfit + $nonReturnableProfit,
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
        ];
    }

    /**
     * เครดิตที่เก็บคืนแล้ว แยกตามจุดขายที่เปิดบิลนั้น
     * เครดิตผูกกับบิล (credits.sale_id) เลยเช็คได้ว่าเงินที่เก็บคืนมา เป็นของจุดไหน
     * การชำระ 1 ครั้งอาจปิดหลายบิลและแยกสด/โอน จึงเฉลี่ยสัดส่วนสด/โอนตามยอดของแต่ละบิล
     *
     * @return array<int, array{amount: float, cash: float, transfer: float}>  key = station_id
     */
    private function creditSettlementByStation(int $eventId): array
    {
        $rows = Credit::where('credits.event_id', $eventId)
            ->whereNotNull('credits.settled_at')
            ->join('sales', 'sales.id', '=', 'credits.sale_id')
            ->leftJoin('credit_payments', 'credit_payments.id', '=', 'credits.credit_payment_id')
            ->selectRaw('sales.station_id')
            ->selectRaw('credits.amount as amount')
            ->selectRaw('credit_payments.amount as paid_total')
            ->selectRaw('credit_payments.cash_amount as paid_cash')
            ->selectRaw('credit_payments.transfer_amount as paid_transfer')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $stationId = (int) $r->station_id;
            $amount = (float) $r->amount;
            $paidTotal = (float) $r->paid_total;

            // เฉลี่ยสด/โอนตามสัดส่วนยอดบิลนี้ในใบชำระ (ไม่มีใบชำระ = นับเป็นสด)
            if ($paidTotal > 0) {
                $ratio = $amount / $paidTotal;
                $cash = (float) $r->paid_cash * $ratio;
                $transfer = (float) $r->paid_transfer * $ratio;
            } else {
                $cash = $amount;
                $transfer = 0.0;
            }

            $out[$stationId] ??= ['amount' => 0.0, 'cash' => 0.0, 'transfer' => 0.0];
            $out[$stationId]['amount'] += $amount;
            $out[$stationId]['cash'] += $cash;
            $out[$stationId]['transfer'] += $transfer;
        }

        return $out;
    }

    /**
     * ยอดเครดิตที่เชียร์เบียร์นำเงินมาชำระคืนแล้วในงานนี้
     * แยกสด/โอน เพื่อให้ยอดปิดลิ้นชักตรงกับเงินจริงในมือ
     */
    private function creditSettlement(int $eventId): array
    {
        $row = CreditPayment::where('event_id', $eventId)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->selectRaw('COALESCE(SUM(cash_amount), 0) as cash')
            ->selectRaw('COALESCE(SUM(transfer_amount), 0) as transfer')
            ->first();

        return [
            'amount' => (float) $row->amount,
            'cash' => (float) $row->cash,
            'transfer' => (float) $row->transfer,
        ];
    }

    private function currentEvent(): Event
    {
        return Event::where('status', 'active')->first() ?? Event::firstOrFail();
    }
}
