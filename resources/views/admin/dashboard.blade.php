@extends('layouts.app')
@section('title', 'ภาพรวม')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0"><i class="bi bi-speedometer2 text-primary"></i> ภาพรวม</h5>
            <small class="text-muted">{{ $event->name }} — {{ $event->location }}</small>
        </div>
        <span class="badge bg-{{ $event->status === 'active' ? 'success' : 'secondary' }}">
            {{ $event->status === 'active' ? 'เปิดงาน' : 'ปิดงาน' }}
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl"><div class="card card-hover stat-tile"><div class="d-flex align-items-center gap-3"><div class="stat-icon" style="background:var(--grad)"><i class="bi bi-cash-stack text-white"></i></div><div><div class="small text-dim">ยอดขายรวม</div><div class="fs-4 fw-bold">฿{{ number_format($stat['total'],0) }}</div></div></div></div></div>
        <div class="col-6 col-md-4 col-xl"><div class="card card-hover stat-tile"><div class="d-flex align-items-center gap-3"><div class="stat-icon" style="background:var(--grad-green)"><i class="bi bi-cash-coin text-white"></i></div><div><div class="small text-dim">เงินสด</div><div class="fs-4 fw-bold">฿{{ number_format($stat['cash'],0) }}</div></div></div></div></div>
        <div class="col-6 col-md-4 col-xl"><div class="card card-hover stat-tile"><div class="d-flex align-items-center gap-3"><div class="stat-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2)"><i class="bi bi-bank text-white"></i></div><div><div class="small text-dim">เงินโอน</div><div class="fs-4 fw-bold">฿{{ number_format($stat['transfer'],0) }}</div></div></div></div></div>
        <div class="col-6 col-md-4 col-xl"><div class="card card-hover stat-tile"><div class="d-flex align-items-center gap-3"><div class="stat-icon" style="background:var(--grad-amber)"><i class="bi bi-person-badge text-white"></i></div><div><div class="small text-dim">เครดิต</div><div class="fs-4 fw-bold">฿{{ number_format($stat['credit'],0) }}</div></div></div></div></div>
        <div class="col-6 col-md-4 col-xl"><div class="card card-hover stat-tile"><div class="d-flex align-items-center gap-3"><div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6366f1)"><i class="bi bi-receipt text-white"></i></div><div><div class="small text-dim">จำนวนบิล</div><div class="fs-4 fw-bold">{{ number_format($stat['bills']) }}</div></div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card p-3">
                <h6 class="mb-3"><i class="bi bi-shop"></i> ยอดขายแยกตามจุด</h6>
                <table class="table table-sm mb-0">
                    <thead><tr><th>จุด</th><th class="text-end">บิล</th><th class="text-end">ยอดขาย</th></tr></thead>
                    <tbody>
                        @forelse($byStation as $row)
                            <tr><td>{{ $row->station->name ?? '-' }}</td><td class="text-end">{{ $row->bills }}</td><td class="text-end fw-bold">฿{{ number_format($row->total,0) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h6 class="mb-3"><i class="bi bi-box"></i> ยอดขายแยกตามสินค้า</h6>
                <table class="table table-sm mb-0">
                    <thead><tr><th>สินค้า</th><th class="text-end">จำนวน</th><th class="text-end">ยอดขาย</th></tr></thead>
                    <tbody>
                        @forelse($byProduct as $row)
                            <tr><td>{{ $row->product_name }}</td><td class="text-end">{{ number_format($row->qty) }}</td><td class="text-end fw-bold">฿{{ number_format($row->total,0) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
