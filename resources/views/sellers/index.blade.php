@extends('layouts.app')
@section('title', 'เชียร์เบียร์')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-person-badge text-primary"></i> เชียร์เบียร์ / เครดิต</h5>
        @if($user->isAdmin())
            <button class="btn btn-grad" onclick="openAddSeller()">
                <i class="bi bi-person-plus"></i> เพิ่มเชียร์เบียร์
            </button>
        @else
            {{-- POS รับชำระได้อย่างเดียว เพิ่ม/แก้ไขเป็นงานแอดมิน --}}
            <span class="badge bg-secondary"><i class="bi bi-lock"></i> เพิ่ม/แก้ไข: แอดมินเท่านั้น</span>
        @endif
    </div>

    <div class="row g-4">
        @forelse($sellers as $s)
            @php
                $limit = (float) $s->credit_limit;
                $available = $limit <= 0 ? null : max(0, $limit - $s->outstanding);
                $usedPct = $limit > 0 ? min(100, round($s->outstanding / $limit * 100)) : 0;
                $sData = ['id'=>$s->id,'code'=>$s->code,'name'=>$s->name,'phone'=>$s->phone,'commission_rate'=>$s->commission_rate,'credit_limit'=>$s->credit_limit];
            @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card card-hover p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge" style="background:var(--grad)">{{ $s->code }}</span>
                            <span class="fw-semibold fs-5">{{ $s->name }}</span>
                            @if($s->phone)<div><small class="text-dim"><i class="bi bi-telephone"></i> {{ $s->phone }}</small></div>@endif
                        </div>
                        @if($user->isAdmin())
                            <button class="btn btn-sm btn-outline-light" onclick="editSeller({{ \Illuminate\Support\Js::from($sData) }})"><i class="bi bi-pencil"></i></button>
                        @endif
                    </div>
                    <hr class="my-2">
                    <div class="row text-center g-1 mb-2">
                        <div class="col-4"><div class="small text-dim">ยอดขาย</div><div class="fw-bold">฿{{ number_format($s->sales_total,0) }}</div></div>
                        <div class="col-4"><div class="small text-dim">คอม</div><div class="fw-bold text-info">{{ rtrim(rtrim(number_format($s->commission_rate,2),'0'),'.') }}%</div></div>
                        <div class="col-4"><div class="small text-dim">คงค้าง</div><div class="fw-bold {{ $s->outstanding > 0 ? 'text-danger' : 'text-success' }}">฿{{ number_format($s->outstanding,0) }}</div></div>
                    </div>
                    {{-- แถบวงเงินเครดิต --}}
                    <div class="small">
                        @if($limit <= 0)
                            <span class="text-dim"><i class="bi bi-infinity"></i> เครดิตไม่จำกัด</span>
                        @else
                            <div class="d-flex justify-content-between text-dim">
                                <span>วงเงิน ฿{{ number_format($limit,0) }}</span>
                                <span>ใช้ได้อีก <b class="{{ $available > 0 ? 'text-success' : 'text-danger' }}">฿{{ number_format($available,0) }}</b></span>
                            </div>
                            <div class="progress mt-1" style="height:6px;background:rgba(255,255,255,.08)">
                                <div class="progress-bar" style="width:{{ $usedPct }}%;background:{{ $usedPct >= 100 ? '#ef4444' : 'var(--grad-amber)' }}"></div>
                            </div>
                        @endif
                    </div>
                    <a href="{{ route('sellers.show', $s) }}" class="btn btn-grad btn-sm mt-3">
                        <i class="bi bi-list-ul"></i> รายละเอียด / รับชำระ
                    </a>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card p-5 text-center text-dim">ยังไม่มีเชียร์เบียร์</div></div>
        @endforelse
    </div>
</div>

{{-- ฟอร์มเพิ่ม/แก้ไข — แสดงเฉพาะแอดมิน --}}
@if($user->isAdmin())
<div class="modal fade" id="sellerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="sellerForm" class="modal-content">
            @csrf
            <input type="hidden" name="_method" id="sf_method" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="sellerModalTitle">เพิ่มเชียร์เบียร์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-4 mb-2"><label class="form-label">รหัส</label><input name="code" id="sf_code" class="form-control" placeholder="C04" required></div>
                    <div class="col-8 mb-2"><label class="form-label">ชื่อ</label><input name="name" id="sf_name" class="form-control" required></div>
                </div>
                <div class="mb-2"><label class="form-label">เบอร์โทร</label><input name="phone" id="sf_phone" class="form-control"></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">คอมมิชชั่น (%)</label><input type="number" step="0.1" name="commission_rate" id="sf_comm" class="form-control" value="10"></div>
                    <div class="col-6 mb-2">
                        <label class="form-label">วงเงินเครดิต (บาท)</label>
                        <input type="number" step="1" min="0" name="credit_limit" id="sf_limit" class="form-control" value="0">
                        <small class="text-dim">0 = ไม่จำกัด</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-grad">บันทึก</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const sellerModal = new bootstrap.Modal('#sellerModal');
function openAddSeller() {
    const f = document.getElementById('sellerForm');
    f.action = '{{ route('sellers.store') }}';
    document.getElementById('sf_method').value = 'POST';
    document.getElementById('sellerModalTitle').textContent = 'เพิ่มเชียร์เบียร์';
    f.reset(); sf_comm.value = 10; sf_limit.value = 0;
    sellerModal.show();
}
function editSeller(s) {
    const f = document.getElementById('sellerForm');
    f.action = '{{ url('sellers') }}/' + s.id;
    document.getElementById('sf_method').value = 'PUT';
    document.getElementById('sellerModalTitle').textContent = 'แก้ไขเชียร์เบียร์';
    sf_code.value = s.code; sf_name.value = s.name; sf_phone.value = s.phone || '';
    sf_comm.value = s.commission_rate; sf_limit.value = s.credit_limit;
    sellerModal.show();
}
</script>
@endpush
@endif
@endsection
