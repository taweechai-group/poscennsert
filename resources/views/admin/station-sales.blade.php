@extends('layouts.app')
@section('title', 'บิลขาย — '.$station->name)

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0"><i class="bi bi-receipt text-primary"></i> บิลขาย — {{ $station->name }}</h5>
            <small class="text-muted">{{ $event->name }} · ทั้งงาน</small>
        </div>
        <a href="{{ route('admin.report') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> กลับสรุปยอด
        </a>
    </div>

    {{-- สรุปของจุดนี้ --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div class="small text-dim">จำนวนบิล</div>
                <div class="fs-4 fw-bold">{{ number_format($stat['bills']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div class="small text-dim">ยอดขายรวม</div>
                <div class="fs-4 fw-bold" style="color:#a5b4fc">{{ number_format($stat['revenue'],0) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center">
                <div class="small text-dim">ต้นทุน <span class="text-muted">(ของคืนได้)</span></div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($stat['cost'],0) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 text-center" style="background:rgba(34,197,94,.08)">
                <div class="small text-dim">กำไรขั้นต้น</div>
                <div class="fs-4 fw-bold {{ $stat['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($stat['gross_profit'],0) }}</div>
            </div>
        </div>
    </div>
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle"></i> กำไรขั้นต้นคิดจากต้นทุน ณ ตอนขายของ <b>สินค้าคืนได้</b> (น้ำ/เบียร์) เท่านั้น
        — น้ำแข็ง/ของคืนไม่ได้เป็นทุนจมทั้งก้อน เฉลี่ยลงรายบิลไม่ได้ จึงไม่รวมในตัวเลขนี้ (ดูกำไรสุทธิรวมทุนจมที่หน้าสรุปยอด)
    </div>

    {{-- บิลรายใบ --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>บิล</th>
                        <th>เวลา</th>
                        <th>รายการ</th>
                        <th>ชำระ</th>
                        <th class="text-end">ยอดขาย</th>
                        <th class="text-end">ต้นทุน</th>
                        <th class="text-end">กำไรขั้นต้น</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td class="fw-semibold">{{ $sale->bill_no }}</td>
                            <td><small>{{ $sale->created_at->format('d/m H:i') }}</small></td>
                            <td>
                                <small class="text-muted">
                                    @foreach($sale->items as $it)
                                        {{ $it->product_name }} ×{{ $it->quantity }}@if(! $loop->last), @endif
                                    @endforeach
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-{{ $sale->paymentBadge() }}">{{ $sale->paymentLabel() }}</span>
                                @if($sale->seller)<small class="text-muted d-block">{{ $sale->seller->code }}</small>@endif
                            </td>
                            <td class="text-end">{{ number_format($sale->total,0) }}</td>
                            <td class="text-end text-danger">{{ number_format($sale->bill_cost,0) }}</td>
                            <td class="text-end fw-bold {{ $sale->bill_profit >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($sale->bill_profit,0) }}</td>
                            <td class="text-end">
                                <a href="{{ route('pos.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="ดูใบเสร็จ">
                                    <i class="bi bi-receipt"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">ยังไม่มีบิลขายที่จุดนี้</td></tr>
                    @endforelse
                </tbody>
                @if($sales->isNotEmpty())
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">รวม</td>
                            <td class="text-end">{{ number_format($stat['revenue'],0) }}</td>
                            <td class="text-end text-danger">{{ number_format($stat['cost'],0) }}</td>
                            <td class="text-end {{ $stat['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($stat['gross_profit'],0) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
