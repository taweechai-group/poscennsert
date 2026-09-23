@extends('layouts.app')
@section('title', 'บิลวันนี้')

@section('content')
<div class="container">
    <h5 class="mb-3"><i class="bi bi-receipt text-primary"></i> บิลวันนี้ — {{ $station->name }}</h5>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">จำนวนบิล</div><div class="fs-4 fw-bold">{{ $summary['count'] }}</div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">เงินสด (รวมเก็บคืน)</div><div class="fs-4 fw-bold text-success">฿{{ number_format($summary['cash'],0) }}</div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">เงินโอน (รวมเก็บคืน)</div><div class="fs-4 fw-bold text-info">฿{{ number_format($summary['transfer'],0) }}</div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 text-center">
                <div class="text-dim small">เครดิตค้างชำระ</div>
                <div class="fs-4 fw-bold {{ $summary['credit'] > 0 ? 'text-warning' : 'text-success' }}">฿{{ number_format($summary['credit'],0) }}</div>
            </div>
        </div>
        <div class="col-12 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">รวมทั้งสิ้น</div><div class="fs-4 fw-bold" style="color:#a5b4fc">฿{{ number_format($summary['total'],0) }}</div></div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="text-dim small">
            แสดง {{ $sales->firstItem() ?? 0 }}–{{ $sales->lastItem() ?? 0 }} จาก {{ number_format($sales->total()) }} บิล
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="per_page" class="text-dim small mb-0">แสดงต่อหน้า</label>
            <select name="per_page" id="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach($perPageOptions as $opt)
                    <option value="{{ $opt }}" @selected($perPage === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>บิล</th><th>เวลา</th><th>รายการ</th><th class="text-end">ยอด</th>
                        <th>ชำระ</th><th>เชียร์เบียร์</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="{{ $sale->isVoid() ? 'table-secondary text-decoration-line-through opacity-75' : '' }}">
                            <td class="fw-semibold">
                                {{ $sale->bill_no }}
                                @if($sale->isVoid())
                                    <span class="badge bg-danger ms-1">ยกเลิก</span>
                                @endif
                            </td>
                            <td>{{ $sale->created_at->format('H:i') }}</td>
                            <td><small>{{ $sale->items->map(fn($i) => $i->product_name.'×'.$i->quantity)->join(', ') }}</small></td>
                            <td class="text-end fw-bold">฿{{ number_format($sale->total,0) }}</td>
                            <td>
                                <span class="badge bg-{{ $sale->paymentBadge() }}">{{ $sale->paymentLabel() }}</span>
                                @if($sale->isSplit())
                                    <div class="small text-dim">สด ฿{{ number_format($sale->cash_amount,0) }} · โอน ฿{{ number_format($sale->transfer_amount,0) }}</div>
                                @elseif($sale->isCredit())
                                    @if($sale->isCreditSettled())
                                        <div class="small text-success">เก็บคืนแล้ว</div>
                                    @elseif(! $sale->isVoid())
                                        <div class="small text-warning">ยังค้าง</div>
                                    @endif
                                @endif
                                @if($sale->isVoid() && $sale->void_reason)
                                    <div class="small text-danger">{{ $sale->void_reason }}</div>
                                @endif
                            </td>
                            <td>{{ $sale->seller?->code ?? '-' }}</td>
                            <td class="text-nowrap">
                                @unless($sale->isVoid())
                                    <a href="{{ route('pos.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="พิมพ์ใบเสร็จ"><i class="bi bi-printer"></i></a>
                                @endunless
                                @if($sale->isEditable())
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-edit-payment"
                                            title="แก้วิธีชำระเงิน"
                                            data-sale="{{ $sale->id }}"
                                            data-bill="{{ $sale->bill_no }}"
                                            data-total="{{ (float) $sale->total }}"
                                            data-payment="{{ $sale->payment_type }}"
                                            data-seller="{{ $sale->seller_id }}"
                                            data-cash="{{ (float) $sale->cash_amount }}"
                                            data-transfer="{{ (float) $sale->transfer_amount }}"
                                            data-url="{{ route('pos.sales.payment', $sale) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-void"
                                            title="ยกเลิกบิล"
                                            data-bill="{{ $sale->bill_no }}"
                                            data-total="{{ number_format($sale->total, 0) }}"
                                            data-url="{{ route('pos.sales.void', $sale) }}">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีบิลวันนี้</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($sales->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $sales->links() }}
        </div>
    @endif
</div>

{{-- แก้วิธีชำระเงินของบิลที่ออกไปแล้ว --}}
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <form method="POST" id="payForm" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square text-warning"></i> แก้วิธีชำระ — <span id="payBill"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary py-2 mb-3">
                    ยอดบิล <span class="fw-bold fs-5" id="payTotalText"></span> บาท
                </div>

                <label class="form-label">วิธีชำระ</label>
                <div class="btn-group w-100 mb-3" role="group">
                    @foreach(['cash' => 'เงินสด', 'transfer' => 'เงินโอน', 'split' => 'สด+โอน', 'credit' => 'เครดิต'] as $val => $label)
                        <input type="radio" class="btn-check pay-type" name="payment_type" id="pt-{{ $val }}" value="{{ $val }}" autocomplete="off" required>
                        <label class="btn btn-outline-primary" for="pt-{{ $val }}">{{ $label }}</label>
                    @endforeach
                </div>

                {{-- เงินสด + เงินโอน --}}
                <div id="splitFields" class="row g-2 mb-3 d-none">
                    <div class="col-6">
                        <label class="form-label mb-1">เงินสด</label>
                        <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" name="cash_amount" id="payCash">
                    </div>
                    <div class="col-6">
                        <label class="form-label mb-1">เงินโอน</label>
                        <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" name="transfer_amount" id="payTransfer">
                    </div>
                    <div class="col-12 small text-dim">กรอกยอดโอน ระบบจะเติมเงินสดส่วนที่เหลือให้ครบยอดบิลอัตโนมัติ</div>
                </div>

                {{-- เครดิต --}}
                <div id="sellerField" class="mb-2 d-none">
                    <label class="form-label mb-1">เชียร์เบียร์ (ผู้รับเครดิต)</label>
                    <select name="seller_id" id="paySeller" class="form-select">
                        <option value="">— เลือกเชียร์เบียร์ —</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}">{{ $seller->code }} · {{ $seller->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer flex-nowrap">
                <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">ปิด</button>
                <button type="submit" class="btn btn-warning flex-fill"><i class="bi bi-check-lg"></i> บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const payModal = new bootstrap.Modal(document.getElementById('payModal'));
const payForm  = document.getElementById('payForm');

// เปิด modal แก้วิธีชำระ พร้อมเติมค่าเดิมของบิล
document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-edit-payment');
    if (!btn) return;
    const d = btn.dataset;

    payForm.action = d.url;
    document.getElementById('payBill').textContent = d.bill;
    document.getElementById('payTotalText').textContent = Number(d.total).toLocaleString('th-TH');
    payForm.dataset.total = d.total;

    document.querySelector(`.pay-type[value="${d.payment}"]`).checked = true;
    document.getElementById('payCash').value     = d.payment === 'split' ? d.cash : '';
    document.getElementById('payTransfer').value = d.payment === 'split' ? d.transfer : '';
    document.getElementById('paySeller').value   = d.seller || '';

    syncPayFields();
    payModal.show();
});

// แสดง/ซ่อนช่องกรอกตามวิธีชำระที่เลือก
function syncPayFields() {
    const type = document.querySelector('.pay-type:checked')?.value;
    const split  = document.getElementById('splitFields');
    const seller = document.getElementById('sellerField');

    split.classList.toggle('d-none', type !== 'split');
    seller.classList.toggle('d-none', type !== 'credit');
    document.getElementById('paySeller').required = (type === 'credit');

    // ช่องยอดที่ซ่อนอยู่ไม่ต้องส่งค่าไป กันยอดเก่าค้าง
    // (เชียร์เบียร์ยังส่งเสมอ เพราะบิลสด/โอนก็ผูกเชียร์เบียร์ไว้คิดค่าคอมได้)
    split.querySelectorAll('input').forEach(i => i.disabled = (type !== 'split'));
}

document.querySelectorAll('.pay-type').forEach(r => r.addEventListener('change', syncPayFields));

// กรอกยอดโอน -> เติมเงินสดส่วนที่เหลือให้ครบยอดบิล
document.getElementById('payTransfer').addEventListener('input', () => {
    const total = Number(payForm.dataset.total || 0);
    const transfer = Math.min(Number(document.getElementById('payTransfer').value) || 0, total);
    document.getElementById('payCash').value = Math.max(0, +(total - transfer).toFixed(2));
});

// ยกเลิกบิล — ยืนยันพร้อมกรอกเหตุผล
document.addEventListener('click', async e => {
    const btn = e.target.closest('.btn-void');
    if (!btn) return;
    const d = btn.dataset;

    const result = await Swal.fire({
        icon: 'warning',
        title: 'ยกเลิกบิล ' + d.bill + '?',
        html: `ยอด <b>฿${d.total}</b> · สินค้าจะถูกคืนเข้าสต๊อกจุดขาย<br><small class="text-muted">ยอดขายวันนี้จะไม่นับบิลนี้</small>`,
        input: 'text',
        inputPlaceholder: 'เหตุผล (ไม่บังคับ)',
        inputAttributes: { maxlength: 200 },
        showCancelButton: true,
        confirmButtonText: 'ยืนยันยกเลิกบิล',
        cancelButtonText: 'ไม่ยกเลิก',
        confirmButtonColor: '#dc3545',
    });
    if (!result.isConfirmed) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = d.url;
    form.innerHTML = `<input type="hidden" name="_token" value="${window.CSRF}">`;
    const reason = document.createElement('input');
    reason.type = 'hidden';
    reason.name = 'reason';
    reason.value = result.value || '';
    form.appendChild(reason);
    document.body.appendChild(form);
    form.submit();
});
</script>
@endpush
