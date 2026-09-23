<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function __construct(private StockService $stock) {}

    /** หน้าขายหลัก */
    public function index()
    {
        $station = Auth::user()->station;
        abort_if(! $station, 403, 'บัญชีนี้ยังไม่ได้ผูกกับจุดขาย');

        $eventId = $station->event_id;

        // สินค้า + ยอดคงเหลือที่จุดนี้
        $products = Product::where('event_id', $eventId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($p) use ($station) {
                $p->stock_qty = $station->stockOf($p->id);
                return $p;
            });

        $sellers = Seller::where('event_id', $eventId)->where('is_active', true)->orderBy('code')->get();

        return view('pos.index', compact('products', 'sellers', 'station'));
    }

    /** บันทึกการขาย — ตัดสต๊อก, ออกบิล, จัดการเครดิต */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_type' => 'required|in:cash,transfer,credit',
            'seller_id' => 'nullable|exists:sellers,id',
            'paid' => 'nullable|numeric|min:0',
        ]);

        $user = Auth::user();
        $station = $user->station;
        abort_if(! $station, 403);
        $eventId = $station->event_id;

        // เครดิตต้องเลือกเชียร์เบียร์
        if ($data['payment_type'] === 'credit' && empty($data['seller_id'])) {
            return response()->json(['ok' => false, 'message' => 'การขายแบบเครดิตต้องเลือกเชียร์เบียร์'], 422);
        }

        // ตรวจวงเงินเครดิตก่อนขาย (บล็อกถ้าเกินลิมิต)
        if ($data['payment_type'] === 'credit') {
            $seller = Seller::find($data['seller_id']);
            $billTotal = collect($data['items'])->sum(function ($item) {
                $p = Product::find($item['product_id']);
                return $p ? $p->price * $item['quantity'] : 0;
            });

            if (! $seller->hasUnlimitedCredit() && $billTotal > $seller->availableCredit()) {
                return response()->json([
                    'ok' => false,
                    'message' => "เกินวงเงินเครดิตของ {$seller->code} · {$seller->name}\n".
                        'วงเงิน '.number_format($seller->credit_limit, 0).' บาท · '.
                        'ค้างอยู่ '.number_format($seller->outstanding(), 0).' บาท · '.
                        'ใช้ได้อีก '.number_format($seller->availableCredit(), 0).' บาท',
                ], 422);
            }
        }

        try {
            $sale = DB::transaction(function () use ($data, $user, $station, $eventId) {
                $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
                    ->get()->keyBy('id');

                // ตรวจสต๊อกก่อน
                $total = 0;
                foreach ($data['items'] as $item) {
                    $p = $products[$item['product_id']];
                    $available = $station->stockOf($p->id);
                    if ($item['quantity'] > $available) {
                        throw new \RuntimeException("สินค้า {$p->name} คงเหลือ {$available} {$p->unit} ไม่พอ (สั่ง {$item['quantity']})");
                    }
                    $total += $p->price * $item['quantity'];
                }

                $isCredit = $data['payment_type'] === 'credit';
                $paid = $isCredit ? 0 : ($data['paid'] ?? $total);

                $sale = Sale::create([
                    'event_id' => $eventId,
                    'station_id' => $station->id,
                    'bill_no' => $this->nextBillNo($station->id),
                    'user_id' => $user->id,
                    'seller_id' => $data['seller_id'] ?? null,
                    'payment_type' => $data['payment_type'],
                    'total' => $total,
                    'paid' => $paid,
                    'change' => max(0, $paid - $total),
                    'status' => 'completed',
                ]);

                foreach ($data['items'] as $item) {
                    $p = $products[$item['product_id']];
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $p->id,
                        'product_name' => $p->name,
                        'price' => $p->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $p->price * $item['quantity'],
                    ]);

                    // ตัดสต๊อกจุดขาย
                    $this->stock->adjust(
                        $eventId, $station->id, $p->id, -$item['quantity'],
                        'sale', 'sale', $sale->id, $user->id, "บิล {$sale->bill_no}",
                    );
                }

                // เครดิตเชียร์เบียร์ — บันทึกยอดค้าง
                if ($isCredit) {
                    Credit::create([
                        'event_id' => $eventId,
                        'seller_id' => $data['seller_id'],
                        'sale_id' => $sale->id,
                        'amount' => $total,
                    ]);
                }

                return $sale;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'sale_id' => $sale->id,
            'bill_no' => $sale->bill_no,
            'receipt_url' => route('pos.receipt', $sale),
        ]);
    }

    /** ใบเสร็จสำหรับพิมพ์ */
    public function receipt(Sale $sale)
    {
        abort_unless($sale->station_id === Auth::user()->station_id || Auth::user()->isAdmin(), 403);
        $sale->load('items', 'station', 'seller', 'user');

        return view('pos.receipt', compact('sale'));
    }

    /** บิลของจุดนี้ (วันนี้) */
    public function sales()
    {
        $station = Auth::user()->station;
        $sales = Sale::where('station_id', $station->id)
            ->whereDate('created_at', today())
            ->with('items', 'seller')
            ->latest()
            ->get();

        $summary = [
            'count' => $sales->count(),
            'cash' => $sales->where('payment_type', 'cash')->sum('total'),
            'transfer' => $sales->where('payment_type', 'transfer')->sum('total'),
            'credit' => $sales->where('payment_type', 'credit')->sum('total'),
            'total' => $sales->sum('total'),
        ];

        return view('pos.sales', compact('sales', 'summary', 'station'));
    }

    private function nextBillNo(int $stationId): string
    {
        $count = Sale::where('station_id', $stationId)->count() + 1;
        return 'B'.$stationId.'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
