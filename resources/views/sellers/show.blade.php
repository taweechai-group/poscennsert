@extends('layouts.app')
@section('title', 'เชียร์เบียร์ '.$seller->code)

@section('content')
<div class="container">
    <a href="{{ route('sellers.index') }}" class="btn btn-sm btn-outline-light mb-3"><i class="bi bi-arrow-left"></i> กลับ</a>

    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="badge fs-6" style="background:var(--grad)">{{ $seller->code }}</span>
                <span class="fs-5 fw-semibold">{{ $seller->name }}</span>
                @if($seller->phone)<span class="text-dim ms-2"><i class="bi bi-telephone"></i> {{ $seller->phone }}</span>@endif
                <div class="small text-dim mt-1">
                    @if($seller->hasUnlimitedCredit())
                        <i class="bi bi-infinity"></i> เครดิตไม่จำกัด
                    @else
                        วงเงิน ฿{{ number_format($seller->credit_limit,0) }} · ใช้ได้อีก
                        <b class="{{ $seller->availableCredit() > 0 ? 'text-success' : 'text-danger' }}">฿{{ number_format($seller->availableCredit(),0) }}</b>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="small text-dim">ยอดขายรวม</div><div class="fs-4 fw-bold">฿{{ number_format($stat['sales_total'],0) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="small text-dim">เครดิตที่เกิด</div><div class="fs-4 fw-bold text-warning">฿{{ number_format($stat['credit_total'],0) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="small text-dim">ชำระแล้ว</div><div class="fs-4 fw-bold text-success">฿{{ number_format($stat['paid_total'],0) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="small text-dim">คงเหลือ</div><div class="fs-4 fw-bold {{ $stat['outstanding'] > 0 ? 'text-danger' : 'text-success' }}">฿{{ number_format($stat['outstanding'],0) }}</div></div></div>
    </div>

    <div class="alert alert-info d-flex justify-content-between">
        <span><i class="bi bi-percent"></i> คอมมิชชั่น ({{ rtrim(rtrim(number_format($seller->commission_rate,2),'0'),'.') }}%)</span>
        <b>฿{{ number_format($stat['commission'],2) }}</b>
    </div>

    {{-- ===== บิลเครดิตค้าง — เลือกชำระหลายบิลทีเดียว ===== --}}
    @if($openCredits->count() > 0)
    <div class="card p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-clipboard-check" style="color:#fbbf24"></i> บิลเครดิตค้าง ({{ $openCredits->count() }} บิล)</h6>
            @if($user->isCashier())
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="checkAll" onchange="toggleAll(this)">
                    <label class="form-check-label small" for="checkAll">เลือกทั้งหมด (จุดเดียวกัน)</label>
                </div>
            @endif
        </div>

        @if($user->isCashier())
            {{-- เชียร์เบียร์ไปเอาของได้หลายจุด แต่เคลียร์บิลข้ามจุดไม่ได้ --}}
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="bi bi-exclamation-triangle"></i>
                <b>เคลียร์บิลข้ามจุดไม่ได้</b> — เลือกได้ทีละจุดเท่านั้น บิลของจุดไหนต้องเคลียร์ที่จุดนั้น
                <br><span class="text-dim">แสดงเฉพาะบิลของ {{ $user->station->name ?? 'จุดของคุณ' }}</span>
            </div>
        @else
            {{-- แอดมินดูได้อย่างเดียว รับชำระเป็นงานของ POS ที่จุดขาย --}}
            <div class="alert alert-secondary py-2 px-3 small mb-3">
                <i class="bi bi-eye"></i>
                <b>มุมมองแอดมิน (ดูอย่างเดียว)</b> — รับชำระเครดิตทำที่จุดขายโดยแคชเชียร์เท่านั้น
                @if($creditsByStation->count() > 1)
                    <br><span class="text-dim">มีบิลค้างจาก {{ $creditsByStation->count() }} จุด</span>
                @endif
            </div>
        @endif

        {{-- แอดมินดูอย่างเดียว: ไม่ต้องมีฟอร์ม/ปุ่มรับชำระ --}}
        @if($user->isCashier())
        <form method="POST" action="{{ route('sellers.pay', $seller) }}" id="settleForm">
            @csrf
        @endif
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>@if($user->isCashier())<th style="width:44px"></th>@endif<th>บิล</th><th>จุดขาย</th><th>เวลา</th><th>รายการ</th><th class="text-end">ยอด</th></tr>
                    </thead>
                    <tbody>
                        @foreach($openCredits as $c)
                            <tr>
                                @if($user->isCashier())
                                <td><input class="form-check-input credit-check" type="checkbox" name="credit_ids[]" value="{{ $c->id }}" data-amount="{{ $c->amount }}" data-station="{{ $c->sale?->station_id ?? '' }}" data-station-name="{{ $c->sale?->station?->name ?? '-' }}" onchange="recalc(this)"></td>
                                @endif
                                <td class="fw-semibold">{{ $c->sale?->bill_no ?? '-' }}</td>
                                <td><span class="badge bg-secondary">{{ $c->sale?->station?->name ?? '-' }}</span></td>
                                <td><small>{{ $c->created_at->format('d/m H:i') }}</small></td>
                                <td><small class="text-dim">{{ $c->sale ? $c->sale->items->map(fn($i)=>$i->product_name.'×'.$i->quantity)->join(', ') : '-' }}</small></td>
                                <td class="text-end fw-bold text-warning">฿{{ number_format($c->amount,0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($user->isCashier())
            <input type="hidden" name="note" id="settleNote">
            <input type="hidden" name="cash_amount" id="settleCash">
            <input type="hidden" name="transfer_amount" id="settleTransfer">
            <div class="d-flex justify-content-between align-items-center mt-3 p-3 rounded-3" style="background:rgba(255,255,255,.04)">
                <div>เลือก <b id="selCount">0</b> บิล<span id="selStation" class="text-info"></span> · รวม <b class="text-success" style="font-size:1.4rem">฿<span id="selTotal">0</span></b></div>
                <button type="button" class="btn btn-grad-green" id="settleBtn" disabled onclick="confirmSettle()">
                    <i class="bi bi-cash-coin"></i> รับชำระที่เลือก
                </button>
            </div>
        </form>
            @else
            <div class="d-flex justify-content-between align-items-center mt-3 p-3 rounded-3" style="background:rgba(255,255,255,.04)">
                <div class="text-dim small"><i class="bi bi-info-circle"></i> ให้แคชเชียร์ที่จุดขายเป็นผู้รับชำระ</div>
                <div>ค้างทั้งหมด <b class="text-warning" style="font-size:1.4rem">฿{{ number_format($openCredits->sum('amount'),0) }}</b></div>
            </div>
            @endif
    </div>
    @else
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            @if($user->isCashier())
                ไม่มีบิลเครดิตค้างชำระที่ {{ $user->station->name ?? 'จุดของคุณ' }}
                <div class="small text-dim mt-1">บิลของจุดอื่นต้องไปเคลียร์ที่จุดนั้น</div>
            @else
                ไม่มีบิลเครดิตค้างชำระ
            @endif
        </div>
    @endif

    <h6 class="mt-4 mb-2">ประวัติการชำระเครดิต</h6>
    <div class="card mb-4">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>เวลา</th><th class="text-end">รวม</th><th class="text-end">เงินสด</th><th class="text-end">เงินโอน</th><th>รับโดย</th><th>หมายเหตุ</th></tr></thead>
            <tbody>
                @forelse($seller->payments->sortByDesc('created_at') as $p)
                    <tr>
                        <td>{{ $p->created_at->format('d/m H:i') }}</td>
                        <td class="text-end fw-bold text-success">฿{{ number_format($p->amount,0) }}</td>
                        <td class="text-end">{{ $p->cash_amount > 0 ? '฿'.number_format($p->cash_amount,0) : '-' }}</td>
                        <td class="text-end text-info">{{ $p->transfer_amount > 0 ? '฿'.number_format($p->transfer_amount,0) : '-' }}</td>
                        <td>{{ $p->receiver->name ?? '-' }}</td>
                        <td><small class="text-dim">{{ $p->note }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-dim py-3">ยังไม่มีการชำระ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h6 class="mb-2">บิลทั้งหมดของเชียร์เบียร์คนนี้</h6>
    <div class="card">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>บิล</th><th>เวลา</th><th>รายการ</th><th class="text-end">ยอด</th><th>ชำระ</th></tr></thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->bill_no }}</td>
                        <td>{{ $sale->created_at->format('d/m H:i') }}</td>
                        <td><small class="text-dim">{{ $sale->items->map(fn($i)=>$i->product_name.'×'.$i->quantity)->join(', ') }}</small></td>
                        <td class="text-end fw-bold">฿{{ number_format($sale->total,0) }}</td>
                        <td><span class="badge bg-{{ $sale->paymentBadge() }}">{{ $sale->paymentLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-dim py-3">ยังไม่มีบิล</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- สคริปต์รับชำระ — เฉพาะแคชเชียร์ (แอดมินดูอย่างเดียว) --}}
@if($user->isCashier())
@push('scripts')
<script>
// เชียร์เบียร์ไปเอาของได้หลายจุด แต่บิลของจุดไหนต้องเคลียร์ที่จุดนั้น
// เลือกบิลแรกแล้ว = ล็อกจุดนั้น บิลของจุดอื่นจะถูกปิดไว้จนกว่าจะเคลียร์ตัวเลือก
function toggleAll(el) {
    const boxes = [...document.querySelectorAll('.credit-check')];
    if (!el.checked) {
        boxes.forEach(c => { c.checked = false; c.disabled = false; });
    } else {
        // เลือกทั้งหมด = เฉพาะจุดที่ล็อกอยู่ ถ้ายังไม่ล็อก ใช้จุดของบิลแรก
        const station = lockedStation() || (boxes[0] && boxes[0].dataset.station);
        boxes.forEach(c => c.checked = (c.dataset.station === station));
    }
    recalc();
}

/** จุดที่ถูกล็อกจากบิลที่เลือกไว้แล้ว (null = ยังไม่เลือกอะไร) */
function lockedStation() {
    const first = document.querySelector('.credit-check:checked');
    return first ? first.dataset.station : null;
}

function recalc(changed) {
    const station = lockedStation();

    // กันติ๊กข้ามจุด — เตือนแล้วปลดติ๊กตัวที่เพิ่งเลือก
    if (changed && changed.checked && station && changed.dataset.station !== station) {
        changed.checked = false;
        const lockedName = document.querySelector('.credit-check:checked').dataset.stationName;
        Swal.fire({
            icon: 'warning',
            title: 'เคลียร์บิลข้ามจุดไม่ได้',
            html: `กำลังเคลียร์บิลของ <b>${lockedName}</b><br>`
                + `บิลของ <b>${changed.dataset.stationName}</b> ต้องรับชำระแยกกัน`,
            confirmButtonColor: '#10b981',
        });
    }

    // ปิดบิลของจุดอื่นไว้ ให้เห็นชัดว่าเลือกได้ทีละจุด
    document.querySelectorAll('.credit-check').forEach(c => {
        const other = station !== null && c.dataset.station !== station;
        c.disabled = other;
        c.closest('tr').style.opacity = other ? '.4' : '';
    });

    const checked = [...document.querySelectorAll('.credit-check:checked')];
    const total = checked.reduce((s, c) => s + parseFloat(c.dataset.amount), 0);
    document.getElementById('selCount').textContent = checked.length;
    document.getElementById('selTotal').textContent = baht(total);
    document.getElementById('settleBtn').disabled = checked.length === 0;

    const label = document.getElementById('selStation');
    if (label) label.textContent = checked.length ? ' · จุด ' + checked[0].dataset.stationName : '';
}
async function confirmSettle() {
    const checked = [...document.querySelectorAll('.credit-check:checked')];
    const total = checked.reduce((s, c) => s + parseFloat(c.dataset.amount), 0);

    const result = await Swal.fire({
        title: 'รับชำระเครดิต',
        html:
            `<div class="mb-2">ชำระ <b>${checked.length}</b> บิล · จุด <b>${checked[0].dataset.stationName}</b><br>รวม <b style="color:#34d399;font-size:1.4rem">฿${baht(total)}</b></div>`
          + `<div style="text-align:left">`
          + `  <label class="form-label mb-1" style="color:#97a3bd"><i class="bi bi-cash-coin"></i> เงินสด</label>`
          + `  <input id="swalCash" type="number" min="0" step="0.01" class="swal2-input mt-0" style="margin:0 0 10px;width:100%" value="${total}">`
          + `  <label class="form-label mb-1" style="color:#97a3bd"><i class="bi bi-bank"></i> เงินโอน</label>`
          + `  <input id="swalTransfer" type="number" min="0" step="0.01" class="swal2-input mt-0" style="margin:0 0 6px;width:100%" value="0">`
          + `  <input id="swalNote" class="swal2-input mt-0" style="margin:4px 0 0;width:100%" placeholder="หมายเหตุ (ถ้ามี)">`
          + `  <div id="swalSum" class="mt-2 small"></div>`
          + `</div>`,
        didOpen: () => {
            const cash = document.getElementById('swalCash');
            const tf = document.getElementById('swalTransfer');
            const sum = document.getElementById('swalSum');
            const upd = () => {
                const c = parseFloat(cash.value) || 0;
                const t = parseFloat(tf.value) || 0;
                const diff = +(c + t - total).toFixed(2);
                if (diff === 0) sum.innerHTML = '<span style="color:#34d399"><i class="bi bi-check-circle"></i> ยอดตรงพอดี</span>';
                else if (diff > 0) sum.innerHTML = `<span style="color:#fbbf24">เกินมา ฿${baht(diff)}</span>`;
                else sum.innerHTML = `<span style="color:#f87171">ขาดอีก ฿${baht(-diff)}</span>`;
            };
            // พิมพ์สด → เติมส่วนที่เหลือเป็นโอนอัตโนมัติ (สะดวก)
            cash.addEventListener('input', () => { tf.value = Math.max(0, +(total - (parseFloat(cash.value)||0)).toFixed(2)); upd(); });
            tf.addEventListener('input', upd);
            upd();
        },
        showCancelButton: true, confirmButtonText: 'รับชำระ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#10b981',
        preConfirm: () => {
            const c = parseFloat(document.getElementById('swalCash').value) || 0;
            const t = parseFloat(document.getElementById('swalTransfer').value) || 0;
            if (Math.abs((c + t) - total) > 0.01) {
                Swal.showValidationMessage('เงินสด + เงินโอน ต้องเท่ายอดที่เลือก');
                return false;
            }
            return { cash: c, transfer: t, note: document.getElementById('swalNote').value };
        },
    });
    if (!result.isConfirmed) return;
    document.getElementById('settleCash').value = result.value.cash;
    document.getElementById('settleTransfer').value = result.value.transfer;
    document.getElementById('settleNote').value = result.value.note || '';
    document.getElementById('settleForm').submit();
}
</script>
@endpush
@endif
@endsection
