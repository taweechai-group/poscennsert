@extends('layouts.app')
@section('title', 'สรุปยอด / ปิดงาน')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h5 class="mb-0"><i class="bi bi-graph-up text-primary"></i> สรุปยอด / ปิดงาน</h5>
            <small class="text-muted">{{ $event->name }}</small>
        </div>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
    </div>

    <div id="print-area">
        <div class="d-none d-print-block text-center mb-3">
            <h4>รายงานสรุปยอด — {{ $event->name }}</h4>
            <div>{{ now()->format('d/m/Y H:i') }}</div>
        </div>

        {{-- ยอดขายรวม --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">ยอดขายรวม</div><div class="fs-4 fw-bold" style="color:#a5b4fc">฿{{ number_format($summary['total'],0) }}</div></div></div>
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">เงินสด (รวมเก็บคืน)</div><div class="fs-4 fw-bold text-success">฿{{ number_format($summary['cash'],0) }}</div></div></div>
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">เงินโอน (รวมเก็บคืน)</div><div class="fs-4 fw-bold text-info">฿{{ number_format($summary['transfer'],0) }}</div></div></div>
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">เครดิตค้างชำระ</div><div class="fs-4 fw-bold {{ $summary['credit'] > 0 ? 'text-warning' : 'text-success' }}">฿{{ number_format($summary['credit'],0) }}</div></div></div>
            <div class="col-12 col-md"><div class="card p-3 text-center"><div class="small text-dim">จำนวนบิล</div><div class="fs-4 fw-bold">{{ number_format($summary['bills']) }}</div></div></div>
        </div>

        {{-- ยอดขายแยกตามจุดขาย --}}
        <h6 class="mb-2"><i class="bi bi-shop"></i> ยอดขายแยกตามจุดขาย</h6>
        <div class="card mb-4">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>จุดขาย</th>
                        <th class="text-end">บิล</th>
                        <th class="text-end">เงินสด<div class="fw-normal small text-muted">รวมเก็บคืน</div></th>
                        <th class="text-end">เงินโอน<div class="fw-normal small text-muted">รวมเก็บคืน</div></th>
                        <th class="text-end">เครดิตค้าง</th>
                        <th class="text-end">ยอดขายรวม</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byStation as $st)
                        <tr>
                            <td>{{ $st->station->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format($st->bills) }}</td>
                            <td class="text-end text-success">฿{{ number_format($st->cash,0) }}</td>
                            <td class="text-end text-info">฿{{ number_format($st->transfer,0) }}</td>
                            <td class="text-end {{ $st->credit > 0 ? 'text-warning fw-bold' : 'text-success' }}">฿{{ number_format($st->credit,0) }}</td>
                            <td class="text-end fw-bold">฿{{ number_format($st->total,0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td class="text-end">รวมทุกจุด</td>
                        <td class="text-end">{{ number_format($byStation->sum('bills')) }}</td>
                        <td class="text-end text-success">฿{{ number_format($byStation->sum('cash'),0) }}</td>
                        <td class="text-end text-info">฿{{ number_format($byStation->sum('transfer'),0) }}</td>
                        <td class="text-end {{ $byStation->sum('credit') > 0 ? 'text-warning' : 'text-success' }}">฿{{ number_format($byStation->sum('credit'),0) }}</td>
                        <td class="text-end">฿{{ number_format($byStation->sum('total'),0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- จุดไหนขายสินค้าอะไรได้เท่าไหร่ --}}
        <h6 class="mb-2"><i class="bi bi-box-seam"></i> สินค้าที่ขายได้ แยกตามจุดขาย</h6>
        <div class="row g-3 mb-4">
            @forelse($byStation as $st)
                @php $items = $stationProducts[$st->station_id] ?? collect(); @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold">{{ $st->station->name ?? '-' }}</span>
                            <span class="fw-bold" style="color:#a5b4fc">฿{{ number_format($st->total,0) }}</span>
                        </div>
                        @forelse($items as $it)
                            <div class="d-flex justify-content-between border-bottom py-1">
                                <span>{{ $it->product_name }}
                                    <small class="text-muted">× {{ number_format($it->qty) }}</small>
                                </span>
                                <b>฿{{ number_format($it->total,0) }}</b>
                            </div>
                        @empty
                            <div class="text-muted small py-2">ยังไม่มีการขาย</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="card p-3 text-center text-muted">ยังไม่มีข้อมูล</div></div>
            @endforelse
        </div>

        {{-- คอมมิชชั่นเชียร์เบียร์ --}}
        <h6 class="mb-2"><i class="bi bi-person-badge"></i> คอมมิชชั่นเชียร์เบียร์</h6>
        <div class="card mb-4">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>รหัส</th><th>ชื่อ</th><th class="text-end">ยอดขาย</th><th class="text-end">คอม %</th><th class="text-end">คอมมิชชั่น</th><th class="text-end">เครดิตค้าง</th></tr></thead>
                <tbody>
                    @forelse($sellers as $s)
                        <tr>
                            <td>{{ $s->code }}</td><td>{{ $s->name }}</td>
                            <td class="text-end">฿{{ number_format($s->sales_total,0) }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($s->commission_rate,2),'0'),'.') }}%</td>
                            <td class="text-end fw-bold text-info">฿{{ number_format($s->commission,2) }}</td>
                            <td class="text-end {{ $s->outstanding > 0 ? 'text-danger fw-bold' : 'text-muted' }}">฿{{ number_format($s->outstanding,0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">ไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td colspan="4" class="text-end">รวมคอมมิชชั่น</td>
                        <td class="text-end text-info">฿{{ number_format($sellers->sum('commission'),2) }}</td>
                        <td class="text-end text-danger">฿{{ number_format($sellers->sum('outstanding'),0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- สต๊อกคงเหลือทุกจุด --}}
        <h6 class="mb-2"><i class="bi bi-boxes"></i> สต๊อกคงเหลือทุกจุด</h6>
        <div class="row g-3">
            @foreach($stockLeft as $stationName => $stocks)
                <div class="col-md-4">
                    <div class="card p-3">
                        <div class="fw-semibold mb-2">{{ $stationName }}</div>
                        @foreach($stocks as $s)
                            <div class="d-flex justify-content-between border-bottom py-1">
                                <span><i class="bi {{ $s->product->icon }}" style="color:{{ $s->product->color }}"></i> {{ $s->product->name }}</span>
                                <b>{{ number_format($s->quantity) }} {{ $s->product->unit }}</b>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
