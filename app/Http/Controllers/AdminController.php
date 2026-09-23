<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Product;
use App\Models\Sale;
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

        $stat = [
            'total' => (clone $sales)->sum('total'),
            'cash' => (clone $sales)->where('payment_type', 'cash')->sum('total'),
            'transfer' => (clone $sales)->where('payment_type', 'transfer')->sum('total'),
            'credit' => (clone $sales)->where('payment_type', 'credit')->sum('total'),
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
            'icon' => 'nullable|string|max:40',
            'color' => 'nullable|string|max:20',
            'image' => 'nullable|image|max:4096', // อัปโหลดรูป สูงสุด 4MB
        ]);

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

        $summary = [
            'total' => $sales->sum('total'),
            'cash' => $sales->where('payment_type', 'cash')->sum('total'),
            'transfer' => $sales->where('payment_type', 'transfer')->sum('total'),
            'credit' => $sales->where('payment_type', 'credit')->sum('total'),
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

        // สต๊อกคงเหลือทุกจุด
        $stockLeft = Stock::whereHas('station', fn ($q) => $q->where('event_id', $event->id))
            ->with('station', 'product')
            ->get()
            ->groupBy(fn ($s) => $s->station->name);

        return view('admin.report', compact('event', 'summary', 'sellers', 'stockLeft'));
    }

    private function currentEvent(): Event
    {
        return Event::where('status', 'active')->first() ?? Event::firstOrFail();
    }
}
