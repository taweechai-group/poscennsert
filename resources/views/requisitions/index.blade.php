@extends('layouts.app')
@section('title', 'เบิกสินค้า')

@push('styles')
<style>
    /* ---------- การ์ดใบเบิก (มือถือ) ---------- */
    .req-card {
        background: rgba(255,255,255,.04);
        border: 1px solid var(--stroke);
        border-radius: .9rem;
        padding: .85rem .9rem;
    }
    .req-card + .req-card { margin-top: .6rem; }
    .req-card .req-code { font-size: 1rem; font-weight: 600; letter-spacing: .3px; }
    .req-chip {
        display: inline-flex; align-items: center; gap: .3rem;
        background: rgba(255,255,255,.07);
        border: 1px solid var(--stroke-2);
        border-radius: .5rem;
        padding: .2rem .5rem;
        font-size: .82rem;
        line-height: 1.4;
    }
    .req-chip .qty { font-weight: 600; }
    .min-w-0 { min-width: 0; }

    /* ปุ่มบนมือถือ - สูงพอให้กดด้วยนิ้ว */
    @media (max-width: 991.98px) {
        .req-action .btn { min-height: 46px; font-size: .95rem; }
        .btn-new-req { min-height: 46px; }
    }

    /* ฟอร์มจำนวนในโมดัล - ปุ่ม +/- แทนการพิมพ์บนมือถือ */
    .qty-group .btn { width: 44px; min-height: 44px; font-size: 1.1rem; }
    .qty-group .form-control {
        text-align: center; font-size: 1.05rem; font-weight: 600;
        min-height: 44px;
        -moz-appearance: textfield;
    }
    .qty-group .form-control::-webkit-outer-spin-button,
    .qty-group .form-control::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .qty-row { border-bottom: 1px solid var(--stroke); padding: .6rem 0; }
    .qty-row:last-of-type { border-bottom: none; }

    /* โมดัลเต็มจอบนมือถือ */
    @media (max-width: 575.98px) {
        .modal-fullscreen-sm-down .modal-content { border-radius: 0; border: none; }
    }

    /* ช่องกรอกจำนวนใน SweetAlert (หน้าอนุมัติ) */
    .swal2-popup .swal-qty {
        min-height: 46px; text-align: center; font-size: 1.05rem; font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="container px-2 px-lg-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h5 class="mb-0 text-truncate"><i class="bi bi-box-seam text-primary"></i> เบิก/โอนสินค้า</h5>
        @if($user->isCashier())
            <button class="btn btn-primary btn-new-req flex-shrink-0" data-bs-toggle="modal" data-bs-target="#reqModal">
                <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">ขอเบิกจากคลัง</span><span class="d-sm-none">ขอเบิก</span>
            </button>
        @endif
    </div>

    {{-- ========== มือถือ/แท็บเล็ต: การ์ด ========== --}}
    <div class="d-lg-none">
        @forelse($requisitions as $req)
            <div class="req-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="min-w-0">
                        <div class="req-code text-truncate">{{ $req->code }}</div>
                        <small class="text-dim">
                            <i class="bi bi-shop"></i> {{ $req->toStation->name }}
                            &middot; {{ $req->created_at->format('d/m H:i') }}
                        </small>
                    </div>
                    <span class="badge bg-{{ $req->statusBadge() }} flex-shrink-0">{{ $req->statusLabel() }}</span>
                </div>

                <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach($req->items as $it)
                        <span class="req-chip">
                            {{ $it->product->name }}
                            <span class="qty">{{ $it->quantity_requested }}</span>
                            @if($it->quantity_delivered !== null && $it->quantity_delivered != $it->quantity_requested)
                                <span class="text-success">&rarr; {{ $it->quantity_delivered }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>

                @if($req->note)
                    <div class="mb-2"><small class="text-dim"><i class="bi bi-chat-left-text"></i> {{ $req->note }}</small></div>
                @endif

                @if($user->isWarehouse() || $user->isAdmin())
                    @if($req->status === 'pending')
                        <div class="d-flex gap-2 req-action">
                            <button class="btn btn-success flex-fill"
                                    onclick='approveReq(@json($req->code), {{ $req->id }}, @json($req->items->map(fn($i)=>["id"=>$i->id,"name"=>$i->product->name,"qty"=>$i->quantity_requested])))'>
                                <i class="bi bi-check-lg"></i> อนุมัติ + จ่าย
                            </button>
                            <form method="POST" action="{{ route('requisitions.reject', $req) }}"
                                  onsubmit="return confirm('ปฏิเสธใบเบิกนี้?')">
                                @csrf
                                <button class="btn btn-outline-danger px-3 h-100"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    @elseif($req->approver)
                        <small class="text-dim"><i class="bi bi-person-check"></i> {{ $req->approver->name }}</small>
                    @endif
                @endif
            </div>
        @empty
            <div class="card card-body text-center text-dim py-5">
                <div><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i> ยังไม่มีใบเบิก</div>
            </div>
        @endforelse
    </div>

    {{-- ========== เดสก์ท็อป: ตาราง ========== --}}
    <div class="card d-none d-lg-block">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่</th><th>จุดที่ขอ</th><th>รายการ</th>
                        <th>สถานะ</th><th>เวลา</th>
                        @if($user->isWarehouse() || $user->isAdmin())<th>จัดการ</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $req)
                        <tr>
                            <td class="fw-semibold">{{ $req->code }}</td>
                            <td>{{ $req->toStation->name }}</td>
                            <td>
                                @foreach($req->items as $it)
                                    <span class="badge bg-light text-dark border">
                                        {{ $it->product->name }}: {{ $it->quantity_requested }}
                                        @if($it->quantity_delivered !== null && $it->quantity_delivered != $it->quantity_requested)
                                            <span class="text-success">(จ่าย {{ $it->quantity_delivered }})</span>
                                        @endif
                                    </span>
                                @endforeach
                                @if($req->note)<div><small class="text-muted"><i class="bi bi-chat-left-text"></i> {{ $req->note }}</small></div>@endif
                            </td>
                            <td><span class="badge bg-{{ $req->statusBadge() }}">{{ $req->statusLabel() }}</span></td>
                            <td><small>{{ $req->created_at->format('d/m H:i') }}</small></td>
                            @if($user->isWarehouse() || $user->isAdmin())
                                <td>
                                    @if($req->status === 'pending')
                                        <button class="btn btn-sm btn-success" onclick='approveReq(@json($req->code), {{ $req->id }}, @json($req->items->map(fn($i)=>["id"=>$i->id,"name"=>$i->product->name,"qty"=>$i->quantity_requested])))'>
                                            <i class="bi bi-check-lg"></i> อนุมัติ+จ่าย
                                        </button>
                                        <form method="POST" action="{{ route('requisitions.reject', $req) }}" class="d-inline"
                                              onsubmit="return confirm('ปฏิเสธใบเบิกนี้?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    @else
                                        <span class="text-muted small">{{ $req->approver?->name }}</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีใบเบิก</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($requisitions->total() > 0)
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3">
            <small class="text-dim">
                แสดง {{ number_format($requisitions->firstItem()) }}&ndash;{{ number_format($requisitions->lastItem()) }}
                จากทั้งหมด {{ number_format($requisitions->total()) }} ใบ
            </small>
            <div>{{ $requisitions->links() }}</div>
        </div>
    @endif
</div>

{{-- Modal ขอเบิก (POS) --}}
@if($user->isCashier())
<div class="modal fade" id="reqModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form method="POST" action="{{ route('requisitions.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> ขอเบิกจากคลังกลาง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($errors->any())
                    <div class="alert alert-danger py-2 small mb-3">
                        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                    </div>
                @endif
                @foreach($products as $i => $p)
                    <div class="qty-row d-flex align-items-center gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <i class="bi {{ $p->icon }}" style="color:{{ $p->color }}"></i>
                            {{ $p->name }}
                            <small class="text-dim">({{ $p->unit }})</small>
                        </div>
                        <div class="input-group qty-group flex-nowrap w-auto flex-shrink-0">
                            <button type="button" class="btn btn-outline-secondary" data-step="-1">&minus;</button>
                            <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $p->id }}">
                            <input type="number" name="items[{{ $i }}][quantity]" class="form-control" min="0"
                                   inputmode="numeric" style="width:66px"
                                   value="{{ old('items.'.$i.'.quantity', 0) }}">
                            <button type="button" class="btn btn-outline-secondary" data-step="1">+</button>
                        </div>
                    </div>
                @endforeach
                <div class="mt-3">
                    <label class="form-label small">หมายเหตุ</label>
                    <input type="text" name="note" class="form-control" value="{{ old('note') }}" placeholder="เช่น ด่วน / เบียร์ใกล้หมด">
                </div>
            </div>
            <div class="modal-footer flex-nowrap">
                <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-send"></i> ส่งคำขอ</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
@if($errors->any() && $user->isCashier())
    // validate ไม่ผ่าน - เปิด modal กลับมาพร้อมข้อความ จะได้ไม่เงียบหาย
    new bootstrap.Modal(document.getElementById('reqModal')).show();
@endif

// ปุ่ม +/- ในฟอร์มขอเบิก (มือถือกดง่ายกว่าพิมพ์)
document.addEventListener('click', e => {
    const btn = e.target.closest('.qty-group [data-step]');
    if (!btn) return;
    const input = btn.parentElement.querySelector('input[type=number]');
    const next = (parseInt(input.value, 10) || 0) + parseInt(btn.dataset.step, 10);
    input.value = Math.max(0, next);
});

async function approveReq(code, id, items) {
    let html = '<div style="text-align:left">';
    items.forEach(it => {
        html += `<div class="mb-2"><label class="form-label mb-0">${it.name} <small class="text-muted">(ขอ ${it.qty})</small></label>
                 <input type="number" inputmode="numeric" class="form-control swal-qty" data-item="${it.id}" value="${it.qty}" min="0"></div>`;
    });
    html += '</div>';

    const result = await Swal.fire({
        title: 'อนุมัติใบเบิก ' + code,
        html, focusConfirm: false,
        showCancelButton: true, confirmButtonText: 'อนุมัติ + จ่ายสินค้า', cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#198754',
        preConfirm: () => {
            const del = {};
            document.querySelectorAll('.swal-qty').forEach(i => del[i.dataset.item] = i.value);
            return del;   // ค่านี้จะอยู่ใน result.value
        }
    });
    if (!result.isConfirmed) return;

    // สร้างฟอร์ม submit พร้อมจำนวนที่จ่ายจริง
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/requisitions/${id}/approve`;
    form.innerHTML = `<input type="hidden" name="_token" value="${window.CSRF}">`;
    Object.entries(result.value).forEach(([item, val]) => {
        form.innerHTML += `<input type="hidden" name="delivered[${item}]" value="${val}">`;
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
@endsection
