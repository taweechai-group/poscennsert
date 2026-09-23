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
            <div class="card p-3 text-center"><div class="text-dim small">เงินสด</div><div class="fs-4 fw-bold text-success">฿{{ number_format($summary['cash'],0) }}</div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">เงินโอน</div><div class="fs-4 fw-bold text-info">฿{{ number_format($summary['transfer'],0) }}</div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">เครดิต</div><div class="fs-4 fw-bold text-warning">฿{{ number_format($summary['credit'],0) }}</div></div>
        </div>
        <div class="col-12 col-md">
            <div class="card p-3 text-center"><div class="text-dim small">รวมทั้งสิ้น</div><div class="fs-4 fw-bold" style="color:#a5b4fc">฿{{ number_format($summary['total'],0) }}</div></div>
        </div>
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
                        <tr>
                            <td class="fw-semibold">{{ $sale->bill_no }}</td>
                            <td>{{ $sale->created_at->format('H:i') }}</td>
                            <td><small>{{ $sale->items->map(fn($i) => $i->product_name.'×'.$i->quantity)->join(', ') }}</small></td>
                            <td class="text-end fw-bold">฿{{ number_format($sale->total,0) }}</td>
                            <td><span class="badge bg-{{ $sale->paymentBadge() }}">{{ $sale->paymentLabel() }}</span></td>
                            <td>{{ $sale->seller?->code ?? '-' }}</td>
                            <td><a href="{{ route('pos.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีบิลวันนี้</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
