@extends('layouts.app')
@section('title', 'เบิกสินค้า')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-box-seam text-primary"></i> เบิก/โอนสินค้า</h5>
        @if($user->isCashier())
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reqModal">
                <i class="bi bi-plus-lg"></i> ขอเบิกจากคลัง
            </button>
        @endif
    </div>

    <div class="card">
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
</div>

{{-- Modal ขอเบิก (POS) --}}
@if($user->isCashier())
<div class="modal fade" id="reqModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('requisitions.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> ขอเบิกจากคลังกลาง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @foreach($products as $i => $p)
                    <div class="row align-items-center mb-2">
                        <div class="col"><i class="bi {{ $p->icon }}" style="color:{{ $p->color }}"></i> {{ $p->name }} <small class="text-muted">({{ $p->unit }})</small></div>
                        <div class="col-4">
                            <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $p->id }}">
                            <input type="number" name="items[{{ $i }}][quantity]" class="form-control" min="0" value="0">
                        </div>
                    </div>
                @endforeach
                <div class="mt-3">
                    <label class="form-label small">หมายเหตุ</label>
                    <input type="text" name="note" class="form-control" placeholder="เช่น ด่วน / เบียร์ใกล้หมด">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> ส่งคำขอ</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
async function approveReq(code, id, items) {
    let html = '<div style="text-align:left">';
    items.forEach(it => {
        html += `<div class="mb-2"><label class="form-label mb-0">${it.name} <small class="text-muted">(ขอ ${it.qty})</small></label>
                 <input type="number" class="form-control swal-qty" data-item="${it.id}" value="${it.qty}" min="0"></div>`;
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
