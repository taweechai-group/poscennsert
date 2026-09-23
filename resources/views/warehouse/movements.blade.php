@extends('layouts.app')
@section('title', 'การเคลื่อนไหวสต๊อก')

@section('content')
<div class="container">
    <h5 class="mb-3"><i class="bi bi-arrow-left-right text-primary"></i> ประวัติการเคลื่อนไหวสต๊อก</h5>

    <form method="GET" class="card card-body py-2 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-lg-3">
                <label class="form-label small mb-1 text-muted">จุด</label>
                <select name="station_id" class="form-select form-select-sm">
                    <option value="">ทุกจุด</option>
                    @foreach($stations as $st)
                        <option value="{{ $st->id }}" @selected(($filters['station_id'] ?? null) == $st->id)>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label small mb-1 text-muted">สินค้า</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">ทุกสินค้า</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(($filters['product_id'] ?? null) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1 text-muted">ประเภท</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">ทุกประเภท</option>
                    @foreach(['in'=>'รับเข้า','out'=>'จ่ายออก','sale'=>'ขาย','transfer_in'=>'โอนเข้า','transfer_out'=>'โอนออก','adjust'=>'ปรับยอด'] as $val=>$label)
                        <option value="{{ $val }}" @selected(($filters['type'] ?? null) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1 text-muted">ต่อหน้า</label>
                <select name="per_page" class="form-select form-select-sm">
                    @foreach([25,50,100,200] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> กรอง</button>
                <a href="{{ route('warehouse.movements') }}" class="btn btn-sm btn-outline-secondary">ล้าง</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>เวลา</th><th>จุด</th><th>สินค้า</th><th>ประเภท</th><th class="text-end">จำนวน</th><th class="text-end">คงเหลือ</th><th>โดย</th><th>หมายเหตุ</th></tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                        @php
                            $typeMap = [
                                'in' => ['รับเข้า','success'], 'out' => ['จ่ายออก','danger'],
                                'sale' => ['ขาย','primary'], 'transfer_in' => ['โอนเข้า','info'],
                                'transfer_out' => ['โอนออก','warning'], 'adjust' => ['ปรับยอด','secondary'],
                            ];
                            [$label,$color] = $typeMap[$m->type] ?? [$m->type,'secondary'];
                        @endphp
                        <tr>
                            <td><small>{{ $m->created_at->format('d/m H:i') }}</small></td>
                            <td>{{ $m->station->name }}</td>
                            <td>{{ $m->product->name }}</td>
                            <td><span class="badge bg-{{ $color }}">{{ $label }}</span></td>
                            <td class="text-end fw-bold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $m->quantity > 0 ? '+' : '' }}{{ number_format($m->quantity) }}
                            </td>
                            <td class="text-end">{{ number_format($m->balance_after) }}</td>
                            <td><small>{{ $m->user->name ?? '-' }}</small></td>
                            <td><small class="text-muted">{{ $m->note }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">ยังไม่มีการเคลื่อนไหว</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($movements->total() > 0)
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <small class="text-muted">
                แสดง {{ number_format($movements->firstItem()) }}–{{ number_format($movements->lastItem()) }}
                จากทั้งหมด {{ number_format($movements->total()) }} รายการ
            </small>
            <div>{{ $movements->links() }}</div>
        </div>
    @endif
</div>
@endsection
