@extends('layouts.app')
@section('title', 'คลังกลาง')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-boxes text-primary"></i> สต๊อกกลาง</h5>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receiveModal">
            <i class="bi bi-box-arrow-in-down"></i> รับสินค้าเข้า
        </button>
    </div>

    {{-- สต๊อกคลังกลาง (การ์ดใหญ่) --}}
    <div class="row g-4 mb-4">
        @foreach($products as $p)
            @php $whQty = $matrix[$p->id][$warehouse->id] ?? 0; @endphp
            <div class="col-6 col-md-4">
                <div class="card card-hover p-3 text-center" style="border-top:4px solid {{ $p->color }}">
                    <div class="mx-auto mb-2" style="width:72px;height:72px;border-radius:1rem;overflow:hidden;display:grid;place-items:center;background:rgba(0,0,0,.25)">
                        @if($p->imageUrl())
                            <img src="{{ $p->imageUrl() }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $p->name }}">
                        @else
                            <i class="bi {{ $p->icon }}" style="font-size:2.5rem;color:{{ $p->color }}"></i>
                        @endif
                    </div>
                    <div class="fw-semibold">{{ $p->name }}</div>
                    <div class="fs-2 fw-bold" style="color:{{ $p->color }}">{{ number_format($whQty) }}</div>
                    <small class="text-dim">{{ $p->unit }}</small>
                </div>
            </div>
        @endforeach
    </div>

    {{-- สรุปมูลค่าสินค้าคงเหลือ (เฉพาะของคืนได้) --}}
    <div class="card card-body mb-3 d-flex flex-row align-items-center justify-content-between flex-wrap gap-2"
         style="border-left:4px solid #16a34a">
        <div>
            <div class="text-dim small"><i class="bi bi-cash-stack text-success"></i> มูลค่าสินค้าคงเหลือ (ทุกจุดรวมกัน)</div>
            <div class="fs-3 fw-bold text-success">฿{{ number_format($totalStockValue, 0) }}</div>
        </div>
        <small class="text-muted" style="max-width:260px">
            <i class="bi bi-info-circle"></i> คิดเฉพาะสินค้าคืนได้ (คงเหลือ × ต้นทุนเฉลี่ย) — น้ำแข็ง/ของคืนไม่ได้เป็นทุนจม จึงไม่นับมูลค่าคงเหลือ
        </small>
    </div>

    {{-- ตารางสต๊อกทุกจุด --}}
    <h6 class="mb-2"><i class="bi bi-grid text-secondary"></i> ยอดคงเหลือทุกจุด</h6>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">จุด</th>
                        @foreach($products as $p)<th>{{ $p->name }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($stations as $st)
                        <tr>
                            <td class="text-start fw-semibold">
                                @if($st->isWarehouse())<i class="bi bi-house-fill text-primary"></i>@else<i class="bi bi-shop text-secondary"></i>@endif
                                {{ $st->name }}
                            </td>
                            @foreach($products as $p)
                                @php $q = $matrix[$p->id][$st->id] ?? 0; @endphp
                                <td>
                                    <span class="badge {{ $q > 20 ? 'bg-success' : ($q > 0 ? 'bg-warning text-dark' : 'bg-danger') }}"
                                          style="cursor:pointer"
                                          onclick="adjustStock({{ $st->id }}, {{ $p->id }}, @json($st->name), @json($p->name), {{ $q }})">
                                        {{ number_format($q) }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    {{-- จำนวนคงเหลือรวมทุกจุด ต่อสินค้า --}}
                    <tr>
                        <td class="text-start fw-semibold"><i class="bi bi-sigma text-secondary"></i> คงเหลือรวม</td>
                        @foreach($products as $p)
                            @php $sum = array_sum($matrix[$p->id] ?? []); @endphp
                            <td class="fw-bold">{{ number_format($sum) }}</td>
                        @endforeach
                    </tr>
                    {{-- มูลค่าคงเหลือ ต่อสินค้า (คืนไม่ได้ = ทุนจม แสดง —) --}}
                    <tr>
                        <td class="text-start fw-semibold"><i class="bi bi-cash text-success"></i> มูลค่าคงเหลือ</td>
                        @foreach($products as $p)
                            <td class="small {{ $p->is_returnable ? 'text-success' : 'text-muted' }}">
                                @if($p->is_returnable)
                                    ฿{{ number_format($stockValue[$p->id] ?? 0, 0) }}
                                @else
                                    <span title="ทุนจม ไม่คิดมูลค่าคงเหลือ">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <small class="text-muted"><i class="bi bi-info-circle"></i> แตะที่ตัวเลขเพื่อปรับสต๊อก</small>
</div>

{{-- Modal รับสินค้าเข้า --}}
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('warehouse.receive') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> รับสินค้าเข้าคลังกลาง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">สินค้า</label>
                <select name="product_id" id="rc_product" class="form-select mb-3" required
                        onchange="rcProductChanged(this)">
                    @foreach($products as $p)
                        <option value="{{ $p->id }}"
                                data-cost="{{ (float) $p->cost }}"
                                data-returnable="{{ $p->is_returnable ? 1 : 0 }}">
                            {{ $p->name }} ({{ $p->unit }})
                        </option>
                    @endforeach
                </select>
                <div class="row g-2 mb-1">
                    <div class="col-6">
                        <label class="form-label">จำนวน</label>
                        <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">ต้นทุน/หน่วย (บาท)</label>
                        <input type="number" name="unit_cost" id="rc_unit_cost" class="form-control"
                               min="0" step="0.01" placeholder="เช่น 60">
                    </div>
                </div>
                <small class="text-muted d-block mb-3" id="rc_cost_hint"></small>
                <label class="form-label">หมายเหตุ</label>
                <input type="text" name="note" class="form-control" placeholder="เช่น รับจาก supplier">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> รับเข้า</button>
            </div>
        </form>
    </div>
</div>

{{-- form ซ่อนสำหรับปรับสต๊อก --}}
<form method="POST" action="{{ route('warehouse.adjust') }}" id="adjustForm" class="d-none">
    @csrf
    <input type="hidden" name="station_id" id="adj_station">
    <input type="hidden" name="product_id" id="adj_product">
    <input type="hidden" name="quantity" id="adj_qty">
</form>

@push('scripts')
<script>
// แสดงต้นทุนปัจจุบันของสินค้าที่เลือก + บอกว่าจะคิดต้นทุนแบบไหน
function rcProductChanged(sel) {
    const opt = sel.options[sel.selectedIndex];
    const cost = parseFloat(opt.dataset.cost || 0);
    const returnable = opt.dataset.returnable === '1';
    const hint = document.getElementById('rc_cost_hint');
    if (returnable) {
        hint.innerHTML = `<i class="bi bi-info-circle"></i> ต้นทุนเฉลี่ยปัจจุบัน ฿${cost.toLocaleString()} — กรอกต้นทุนล็อตใหม่เพื่อคำนวณเฉลี่ยถ่วงน้ำหนักให้อัตโนมัติ (เว้นว่าง = ไม่แก้ต้นทุน)`;
    } else {
        hint.innerHTML = `<i class="bi bi-info-circle"></i> สินค้าคืนไม่ได้ (ทุนจม) — ต้นทุนล็อตนี้จะถูกบวกเข้าทุนรวมสะสม`;
    }
    document.getElementById('rc_unit_cost').placeholder = cost ? cost.toString() : 'เช่น 60';
}
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('rc_product');
    if (sel) rcProductChanged(sel);
});

async function adjustStock(stationId, productId, stationName, productName, current) {
    const { value } = await Swal.fire({
        title: 'ปรับสต๊อก',
        html: `${stationName} — <b>${productName}</b>`,
        input: 'number', inputLabel: 'ยอดคงเหลือใหม่', inputValue: current,
        showCancelButton: true, confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก',
    });
    if (value === undefined) return;
    document.getElementById('adj_station').value = stationId;
    document.getElementById('adj_product').value = productId;
    document.getElementById('adj_qty').value = value;
    document.getElementById('adjustForm').submit();
}
</script>
@endpush
@endsection
