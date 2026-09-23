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
            'payment_type' => 'required|in:cash,transfer,split,credit',
            'seller_id' => 'nullable|exists:sellers,id',
            'paid' => 'nullable|numeric|min:0',
            'cash_amount' => 'nullable|numeric|min:0',
            'transfer_amount' => 'nullable|numeric|min:0',
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

        // จ่ายผสม — ต้องกรอกทั้งสองช่อง และแต่ละช่องต้องมากกว่า 0
        if ($data['payment_type'] === 'split') {
            $cash = (float) ($data['cash_amount'] ?? 0);
            $transfer = (float) ($data['transfer_amount'] ?? 0);

            if ($cash <= 0 || $transfer <= 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'การชำระแบบเงินสด+เงินโอน ต้องระบุยอดทั้งสองช่องให้มากกว่า 0',
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

                // แยกยอดเงินสด/เงินโอน เพื่อให้ยอดปิดลิ้นชักตรง
                [$cashAmount, $transferAmount] = $this->splitAmounts($data, $total);

                $sale = Sale::create([
                    'event_id' => $eventId,
                    'station_id' => $station->id,
                    'bill_no' => $this->nextBillNo($station->id),
                    'user_id' => $user->id,
                    'seller_id' => $data['seller_id'] ?? null,
                    'payment_type' => $data['payment_type'],
                    'cash_amount' => $cashAmount,
                    'transfer_amount' => $transferAmount,
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

    /** ยกเลิกบิล — คืนสต๊อก, ล้างเครดิต, ไม่นับในยอดขาย */
    public function void(Request $request, Sale $sale)
    {
        $this->authorizeSale($sale);

        $data = $request->validate([
            'reason' => 'nullable|string|max:200',
        ]);

        if ($sale->isVoid()) {
            return back()->with('error', "บิล {$sale->bill_no} ถูกยกเลิกไปแล้ว");
        }

        if ($sale->isCreditSettled()) {
            return back()->with('error', "บิล {$sale->bill_no} เป็นเครดิตที่เก็บเงินคืนแล้ว ยกเลิกไม่ได้");
        }

        $user = Auth::user();

        DB::transaction(function () use ($sale, $user, $data) {
            $sale->load('items');

            // คืนสต๊อกเข้าจุดขายเดิม
            foreach ($sale->items as $item) {
                $this->stock->adjust(
                    $sale->event_id, $sale->station_id, $item->product_id, $item->quantity,
                    'void', 'sale', $sale->id, $user->id, "ยกเลิกบิล {$sale->bill_no}",
                );
            }

            // บิลเครดิตที่ยังค้าง — ลบยอดค้างทิ้ง
            $sale->credit()->whereNull('settled_at')->delete();

            $sale->update([
                'status' => 'void',
                'cash_amount' => 0,
                'transfer_amount' => 0,
                'paid' => 0,
                'change' => 0,
                'void_reason' => $data['reason'] ?? null,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);
        });

        return back()->with('success', "ยกเลิกบิล {$sale->bill_no} แล้ว — คืนสต๊อกเรียบร้อย");
    }

    /** เปลี่ยนวิธีชำระเงินของบิลที่ออกไปแล้ว (สด / โอน / สด+โอน / เครดิต) */
    public function updatePayment(Request $request, Sale $sale)
    {
        $this->authorizeSale($sale);

        $data = $request->validate([
            'payment_type' => 'required|in:cash,transfer,split,credit',
            'seller_id' => 'nullable|exists:sellers,id',
            'cash_amount' => 'nullable|numeric|min:0',
            'transfer_amount' => 'nullable|numeric|min:0',
        ]);

        if ($sale->isVoid()) {
            return back()->with('error', "บิล {$sale->bill_no} ถูกยกเลิกแล้ว แก้ไขไม่ได้");
        }

        if ($sale->isCreditSettled()) {
            return back()->with('error', "บิล {$sale->bill_no} เป็นเครดิตที่เก็บเงินคืนแล้ว แก้ไขไม่ได้");
        }

        $total = (float) $sale->total;
        $sellerId = $data['seller_id'] ?? $sale->seller_id;

        if ($data['payment_type'] === 'credit' && ! $sellerId) {
            return back()->with('error', 'การขายแบบเครดิตต้องเลือกเชียร์เบียร์');
        }

        // จ่ายผสม — ต้องมีทั้งสองช่องและรวมกันไม่เกินยอดบิล
        if ($data['payment_type'] === 'split') {
            $cash = (float) ($data['cash_amount'] ?? 0);
            $transfer = (float) ($data['transfer_amount'] ?? 0);

            if ($cash <= 0 || $transfer <= 0) {
                return back()->with('error', 'การชำระแบบเงินสด+เงินโอน ต้องระบุยอดทั้งสองช่องให้มากกว่า 0');
            }

            if ($transfer > $total) {
                return back()->with('error', 'ยอดเงินโอนมากกว่ายอดบิล');
            }
        }

        DB::transaction(function () use ($sale, $data, $total, $sellerId) {
            [$cashAmount, $transferAmount] = $this->splitAmounts($data, $total);
            $isCredit = $data['payment_type'] === 'credit';

            // ล้างเครดิตเดิมที่ยังค้างเสมอ แล้วค่อยสร้างใหม่ถ้ายังเป็นเครดิต
            $sale->credit()->whereNull('settled_at')->delete();

            if ($isCredit) {
                Credit::create([
                    'event_id' => $sale->event_id,
                    'seller_id' => $sellerId,
                    'sale_id' => $sale->id,
                    'amount' => $total,
                ]);
            }

            $sale->update([
                'payment_type' => $data['payment_type'],
                'seller_id' => $sellerId,
                'cash_amount' => $cashAmount,
                'transfer_amount' => $transferAmount,
                'paid' => $isCredit ? 0 : $total,
                'change' => 0,
            ]);
        });

        $sale->refresh();

        return back()->with('success', "แก้บิล {$sale->bill_no} เป็น {$sale->paymentLabel()} แล้ว");
    }

    /** บิลต้องเป็นของจุดขายตัวเอง (หรือเป็นแอดมิน) */
    private function authorizeSale(Sale $sale): void
    {
        abort_unless($sale->station_id === Auth::user()->station_id || Auth::user()->isAdmin(), 403);
    }

    /** ใบเสร็จสำหรับพิมพ์ */
    public function receipt(Sale $sale)
    {
        $this->authorizeSale($sale);

        // บิลที่ยกเลิกแล้วห้ามพิมพ์ กันใบเสร็จหลุดไปถึงมือลูกค้า
        abort_if($sale->isVoid(), 404, "บิล {$sale->bill_no} ถูกยกเลิกแล้ว พิมพ์ใบเสร็จไม่ได้");

        // credit: ท่อนฉีกท้ายใบเสร็จต้องรู้ว่าเคลียร์เงินไปแล้วหรือยัง
        $sale->load('items', 'station', 'seller', 'user', 'credit');

        return view('pos.receipt', compact('sale'));
    }

    /** บิลของจุดนี้ (วันนี้) */
    public function sales(Request $request)
    {
        $station = Auth::user()->station;

        $perPageOptions = [20, 50, 100, 200];
        $perPage = (int) $request->input('per_page', 20);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 20;
        }

        $query = fn () => Sale::where('station_id', $station->id)
            ->whereDate('created_at', today());

        $sales = $query()
            ->with('items', 'seller', 'credit', 'voider')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        // สรุปยอดนับเฉพาะบิลที่ยังใช้งาน — บิลที่ยกเลิกไม่เข้ายอดขาย
        $totals = $query()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(cash_amount), 0) as cash')
            ->selectRaw('COALESCE(SUM(transfer_amount), 0) as transfer')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_type = 'credit' THEN total END), 0) as credit")
            ->first();

        // เงินที่เชียร์เบียร์นำมาชำระคืน (เฉพาะบิลเครดิตที่เกิดที่จุดนี้ วันนี้)
        // credits/credit_payments ไม่มี station_id จึงอ้างอิงผ่าน sales.station_id
        $settled = Credit::query()
            ->join('sales', 'sales.id', '=', 'credits.sale_id')
            ->join('credit_payments', 'credit_payments.id', '=', 'credits.credit_payment_id')
            ->where('sales.station_id', $station->id)
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', today())
            ->whereNotNull('credits.settled_at')
            // แยกสด/โอนของ "ใบชำระ" ตามสัดส่วนยอดบิลที่บิลนี้คิดเป็นของใบชำระนั้น
            ->selectRaw('COALESCE(SUM(credits.amount), 0) as amount')
            ->selectRaw('COALESCE(SUM(credits.amount * credit_payments.cash_amount / NULLIF(credit_payments.amount, 0)), 0) as cash')
            ->selectRaw('COALESCE(SUM(credits.amount * credit_payments.transfer_amount / NULLIF(credit_payments.amount, 0)), 0) as transfer')
            ->first();

        $summary = [
            'count' => (int) $totals->count,
            // สด/โอน = ที่รับหน้าร้าน + ที่เก็บคืนจากเครดิต (ยอดปิดลิ้นชักจริง)
            'cash' => (float) $totals->cash + (float) $settled->cash,
            'transfer' => (float) $totals->transfer + (float) $settled->transfer,
            // เครดิต = ยอดที่ "ยังค้าง" จริง เคลียร์แล้วต้องเหลือ 0
            'credit' => (float) $totals->credit - (float) $settled->amount,
            'credit_settled' => (float) $settled->amount,
            'total' => (float) $totals->total,
        ];

        $sellers = Seller::where('event_id', $station->event_id)
            ->where('is_active', true)->orderBy('code')->get();

        return view('pos.sales', compact('sales', 'summary', 'station', 'perPage', 'perPageOptions', 'sellers'));
    }

    /**
     * แยกยอดชำระเป็น เงินสด / เงินโอน ตามวิธีชำระ
     *
     * @return array{0: float, 1: float}
     */
    private function splitAmounts(array $data, float $total): array
    {
        return match ($data['payment_type']) {
            'cash' => [$total, 0.0],
            'transfer' => [0.0, $total],
            'split' => $this->normalizedSplit(
                (float) ($data['cash_amount'] ?? 0),
                (float) ($data['transfer_amount'] ?? 0),
                $total,
            ),
            default => [0.0, 0.0], // เครดิต — ยังไม่ได้รับเงิน
        };
    }

    /**
     * บังคับให้ สด + โอน = ยอดบิลพอดี
     * โอนคือยอดที่ยืนยันแล้ว จึงยึดตามที่กรอก แล้วให้เงินสดรับส่วนที่เหลือ
     * (เงินสดที่ลูกค้ายื่นเกินถือเป็นเงินทอน ไม่นับเป็นรายรับ)
     *
     * @return array{0: float, 1: float}
     */
    private function normalizedSplit(float $cash, float $transfer, float $total): array
    {
        $transfer = min($transfer, $total);

        return [round($total - $transfer, 2), round($transfer, 2)];
    }

    private function nextBillNo(int $stationId): string
    {
        $count = Sale::where('station_id', $stationId)->count() + 1;
        return 'B'.$stationId.'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
