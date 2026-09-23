<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\Event;
use App\Models\Sale;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    /** รายการเชียร์เบียร์ + ยอดค้าง */
    public function index()
    {
        $eventId = $this->eventId();
        $sellers = Seller::where('event_id', $eventId)->orderBy('code')->get()
            ->map(function ($s) {
                $s->credit_total = $s->totalCredit();
                $s->paid_total = $s->totalPaid();
                $s->outstanding = $s->credit_total - $s->paid_total;
                $s->sales_total = $s->sales()->where('status', 'completed')->sum('total');
                return $s;
            });

        return view('sellers.index', compact('sellers') + ['user' => Auth::user()]);
    }

    /** เพิ่มเชียร์เบียร์ — แอดมินเท่านั้น (POS รับชำระได้อย่างเดียว) */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'เฉพาะแอดมินเท่านั้นที่เพิ่มเชียร์เบียร์ได้');

        $data = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        Seller::create(array_merge($data, [
            'event_id' => $this->eventId(),
            'commission_rate' => $data['commission_rate'] ?? 0,
            'credit_limit' => $data['credit_limit'] ?? 0,
        ]));

        return back()->with('success', 'เพิ่มเชียร์เบียร์เรียบร้อย');
    }

    /** แก้ไขข้อมูล + ตั้งลิมิตเครดิต — แอดมินเท่านั้น */
    public function update(Seller $seller, Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'เฉพาะแอดมินเท่านั้นที่แก้ไขข้อมูลเชียร์เบียร์ได้');

        $data = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $seller->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'credit_limit' => $data['credit_limit'] ?? 0,
        ]);

        return back()->with('success', 'แก้ไขข้อมูลเชียร์เบียร์เรียบร้อย');
    }

    /** รายละเอียดเชียร์เบียร์คนหนึ่ง — ยอดขาย, เครดิต, การชำระ */
    public function show(Seller $seller)
    {
        $user = Auth::user();
        $seller->load(['payments.receiver']);
        $sales = Sale::where('seller_id', $seller->id)->with('items', 'station')->latest()->get();

        // บิลเครดิตที่ยังค้าง (ไว้เลือกชำระหลายบิลทีเดียว)
        // แคชเชียร์เห็น/เคลียร์ได้เฉพาะบิลของจุดตัวเอง — เชียร์เบียร์ไปเอาของได้หลายจุด
        // แต่บิลของจุดไหนต้องเคลียร์ที่จุดนั้น เงินจะได้ลงถูกจุด
        $openCredits = Credit::where('seller_id', $seller->id)
            ->whereNull('settled_at')
            ->with('sale.items', 'sale.station')
            ->when($user->isCashier(), fn ($q) => $q->whereHas('sale',
                fn ($s) => $s->where('station_id', $user->station_id)))
            ->latest()
            ->get();

        // แอดมินเคลียร์ข้ามจุดได้ แต่ต้องทีละจุด — จัดกลุ่มไว้ให้เลือก
        $creditsByStation = $openCredits->groupBy(fn ($c) => $c->sale?->station_id);

        $stat = [
            'sales_total' => $sales->where('status', 'completed')->sum('total'),
            'credit_total' => $seller->totalCredit(),
            'paid_total' => $seller->totalPaid(),
            'outstanding' => $seller->outstanding(),
            'commission' => $sales->where('status', 'completed')->sum('total') * $seller->commission_rate / 100,
        ];

        return view('sellers.show', compact('seller', 'sales', 'stat', 'openCredits', 'creditsByStation', 'user'));
    }

    /**
     * รับชำระเครดิต — ชำระได้หลายบิลทีเดียว
     * ส่ง credit_ids[] = บิลเครดิตที่เลือกชำระ (ยังไม่เคลียร์)
     *
     * รับชำระเป็นงานของ POS เท่านั้น เพราะเงินต้องเข้าลิ้นชักที่จุดนั้นจริง
     * แอดมินดูได้อย่างเดียว ไม่ให้รับชำระแทน
     */
    public function pay(Seller $seller, Request $request)
    {
        abort_unless(Auth::user()->isCashier(), 403,
            'รับชำระเครดิตได้เฉพาะแคชเชียร์ที่จุดขายเท่านั้น');
        abort_unless(Auth::user()->station_id, 403, 'บัญชีนี้ยังไม่ผูกกับจุดขาย');

        $data = $request->validate([
            'credit_ids' => 'required|array|min:1',
            'credit_ids.*' => 'integer',
            'cash_amount' => 'nullable|numeric|min:0',
            'transfer_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // ดึงเฉพาะบิลเครดิตของ seller นี้ที่ยังค้าง (กันเลือกข้ามคน/ซ้ำ)
        $credits = Credit::where('seller_id', $seller->id)
            ->whereNull('settled_at')
            ->whereIn('id', $data['credit_ids'])
            ->with('sale.station')
            ->get();

        if ($credits->isEmpty()) {
            return back()->with('error', 'ไม่พบบิลเครดิตที่เลือก (อาจถูกชำระไปแล้ว)');
        }

        // เชียร์เบียร์ไปเอาของได้หลายจุด แต่ห้ามเคลียร์บิลข้ามจุด
        // บิลของจุดไหน ต้องเคลียร์ที่จุดนั้น เงินจะได้ลงยอดถูกจุด
        $stationIds = $credits->map(fn ($c) => $c->sale?->station_id)->unique()->values();

        if ($stationIds->contains(null)) {
            return back()->with('error', 'มีบิลเครดิตที่ไม่ผูกกับจุดขาย ตรวจสอบข้อมูลก่อนรับชำระ');
        }

        if ($stationIds->count() > 1) {
            $names = $credits->map(fn ($c) => $c->sale->station->name ?? ('จุด #'.$c->sale->station_id))
                ->unique()->implode(', ');

            return back()->with('error',
                'เคลียร์บิลข้ามจุดไม่ได้ — บิลที่เลือกมาจาก '.$stationIds->count().' จุด ('.$names.') '.
                'กรุณาเลือกทีละจุด แล้วรับชำระแยกกัน');
        }

        $stationId = (int) $stationIds->first();

        // รับชำระได้เฉพาะบิลของจุดตัวเอง — เงินจะได้ลงลิ้นชักถูกจุด
        if ($stationId !== (int) $user->station_id) {
            $billStation = $credits->first()->sale->station->name ?? ('จุด #'.$stationId);

            return back()->with('error',
                'บิลนี้เป็นของ '.$billStation.' ต้องไปเคลียร์ที่จุดนั้น ไม่สามารถรับชำระข้ามจุดได้');
        }

        $amount = (float) $credits->sum('amount');
        $cash = (float) ($data['cash_amount'] ?? 0);
        $transfer = (float) ($data['transfer_amount'] ?? 0);

        // ถ้าไม่ระบุการแยก ให้ถือเป็นเงินสดทั้งหมด
        if ($cash <= 0 && $transfer <= 0) {
            $cash = $amount;
        }

        // สด + โอน ต้องเท่ายอดบิลที่เลือกพอดี
        if (round($cash + $transfer, 2) !== round($amount, 2)) {
            return back()->with('error',
                'ยอดเงินสด + เงินโอน ('.number_format($cash + $transfer, 2).') '.
                'ต้องเท่ายอดที่เลือก ('.number_format($amount, 2).' บาท)');
        }

        DB::transaction(function () use ($seller, $credits, $amount, $cash, $transfer, $data, $stationId) {
            $payment = CreditPayment::create([
                'event_id' => $seller->event_id,
                'seller_id' => $seller->id,
                'station_id' => $stationId,
                'amount' => $amount,
                'cash_amount' => $cash,
                'transfer_amount' => $transfer,
                'received_by' => Auth::id(),
                'note' => $data['note'] ?? ('ชำระ '.$credits->count().' บิล'),
            ]);

            // มาร์คทุกบิลที่เลือกว่าเคลียร์แล้ว
            Credit::whereIn('id', $credits->pluck('id'))->whereNull('settled_at')->update([
                'settled_at' => now(),
                'credit_payment_id' => $payment->id,
            ]);
        });

        $breakdown = [];
        if ($cash > 0) $breakdown[] = 'สด '.number_format($cash, 0);
        if ($transfer > 0) $breakdown[] = 'โอน '.number_format($transfer, 0);

        return back()->with('success',
            'รับชำระ '.$credits->count().' บิล รวม '.number_format($amount, 2).' บาท ('.implode(' + ', $breakdown).') เรียบร้อย');
    }

    private function eventId(): int
    {
        $user = Auth::user();
        if ($user->station) return $user->station->event_id;
        return Event::where('status', 'active')->value('id') ?? Event::value('id');
    }
}
