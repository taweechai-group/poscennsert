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
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">ยอดขายรวม</div><div class="fs-4 fw-bold" style="color:#a5b4fc">{{ number_format($summary['total'],0) }}</div></div></div>
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">เงินสด (รวมเก็บคืน)</div><div class="fs-4 fw-bold text-success">{{ number_format($summary['cash'],0) }}</div></div></div>
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">เงินโอน (รวมเก็บคืน)</div><div class="fs-4 fw-bold text-info">{{ number_format($summary['transfer'],0) }}</div></div></div>
            <div class="col-6 col-md"><div class="card p-3 text-center"><div class="small text-dim">เครดิตค้างชำระ</div><div class="fs-4 fw-bold {{ $summary['credit'] > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($summary['credit'],0) }}</div></div></div>
            <div class="col-12 col-md"><div class="card p-3 text-center"><div class="small text-dim">จำนวนบิล</div><div class="fs-4 fw-bold">{{ number_format($summary['bills']) }}</div></div></div>
        </div>

        {{-- กำไร --}}
        <h6 class="mb-2"><i class="bi bi-cash-coin text-success"></i> กำไร</h6>

        {{-- สมการกำไรแบบเห็นภาพ: ยอดขาย − ต้นทุน = กำไรสุทธิ --}}
        @php
            $margin = $profit['total_revenue'] > 0
                ? $profit['total_profit'] / $profit['total_revenue'] * 100 : 0;
        @endphp
        <div class="card mb-3">
            <div class="row g-0 text-center align-items-stretch">
                <div class="col">
                    <div class="p-3 h-100 d-flex flex-column justify-content-center">
                        <div class="small text-dim">ยอดขายรวม</div>
                        <div class="fs-4 fw-bold" style="color:#a5b4fc">{{ number_format($profit['total_revenue'],0) }}</div>
                    </div>
                </div>
                <div class="col-auto d-flex align-items-center"><span class="fs-3 text-muted px-1">−</span></div>
                <div class="col">
                    <div class="p-3 h-100 d-flex flex-column justify-content-center">
                        <div class="small text-dim">ต้นทุนรวม</div>
                        <div class="fs-4 fw-bold text-danger">{{ number_format($profit['total_cost'],0) }}</div>
                    </div>
                </div>
                <div class="col-auto d-flex align-items-center"><span class="fs-3 text-muted px-1">=</span></div>
                <div class="col" style="background:rgba(34,197,94,.10)">
                    <div class="p-3 h-100 d-flex flex-column justify-content-center">
                        <div class="small text-dim">กำไรสุทธิ</div>
                        <div class="fs-3 fw-bold {{ $profit['total_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($profit['total_profit'],0) }}</div>
                        <div class="small {{ $margin >= 0 ? 'text-success' : 'text-danger' }}">อัตรากำไร {{ number_format($margin,1) }}%</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- กำไรแยกตามประเภทสินค้า (คิดต้นทุนคนละแบบ) --}}
        <div class="row g-3 mb-3">
            <div class="col-6">
                <div class="card p-3 text-center h-100">
                    <div class="small text-dim">กำไรของคืนได้ <span class="text-muted">(น้ำ / เบียร์)</span></div>
                    <div class="fs-5 fw-bold {{ $profit['returnable_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($profit['returnable_profit'],0) }}</div>
                    <div class="small text-muted"><i class="bi bi-info-circle"></i> ต้นทุนคิดตามที่ขายจริง × ต้นทุน ณ ตอนขาย</div>
                </div>
            </div>
            <div class="col-6">
                <div class="card p-3 text-center h-100">
                    <div class="small text-dim">กำไรของคืนไม่ได้ <span class="text-muted">(น้ำแข็ง)</span></div>
                    <div class="fs-5 fw-bold {{ $profit['nonreturnable_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($profit['nonreturnable_profit'],0) }}</div>
                    <div class="small text-muted"><i class="bi bi-snow"></i> คิดทุนจมทั้งก้อน (ของเหลือละลายทิ้ง)</div>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="p-3 d-flex justify-content-between align-items-center" style="background:rgba(34,197,94,.08)">
                <span class="fw-semibold fs-5"><i class="bi bi-cash-stack text-success"></i> กำไรสุทธิ</span>
                <span class="fw-bold fs-3 {{ $profit['total_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($profit['total_profit'],0) }}</span>
            </div>
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>สินค้า</th>
                        <th class="text-end">ขายได้</th>
                        <th class="text-end">ยอดขาย</th>
                        <th class="text-end">ต้นทุน</th>
                        <th class="text-end">กำไร</th>
                        <th class="text-end">%</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profit['items'] as $it)
                        @php $m = $it['revenue'] > 0 ? $it['profit'] / $it['revenue'] * 100 : null; @endphp
                        <tr>
                            <td>
                                {{ $it['name'] }}
                                @unless($it['is_returnable'])
                                    <span class="badge bg-info-subtle text-info-emphasis" title="คิดต้นทุนทั้งก้อน (ของเหลือละลายทิ้ง)"><i class="bi bi-snow"></i> คืนไม่ได้</span>
                                @endunless
                            </td>
                            <td class="text-end">{{ number_format($it['qty']) }} {{ $it['unit'] }}</td>
                            <td class="text-end">{{ number_format($it['revenue'],0) }}</td>
                            <td class="text-end text-danger">{{ number_format($it['cost'],0) }}</td>
                            <td class="text-end fw-bold {{ $it['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($it['profit'],0) }}</td>
                            <td class="text-end small {{ ($m ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ $m === null ? '-' : number_format($m,0).'%' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td class="text-end" colspan="2">รวม</td>
                        <td class="text-end">{{ number_format($profit['total_revenue'],0) }}</td>
                        <td class="text-end text-danger">{{ number_format($profit['total_cost'],0) }}</td>
                        <td class="text-end {{ $profit['total_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($profit['total_profit'],0) }}</td>
                        <td class="text-end {{ $margin >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($margin,0) }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- บัตร VIP แลกเบียร์ฟรี — แยกออกจากกำไร/ขาดทุนด้านบน --}}
        @if($profit['vip']['bills'] > 0)
            <div class="card mb-4" style="border-color:rgba(148,163,184,.35)">
                <div class="p-3 d-flex justify-content-between align-items-center" style="background:rgba(148,163,184,.1)">
                    <span class="fw-semibold fs-5"><i class="bi bi-person-badge"></i> บัตร VIP (แลกฟรี)</span>
                    <span class="text-dim small"><i class="bi bi-info-circle"></i> แยกต่างหาก ไม่รวมในกำไร/ขาดทุน</span>
                </div>
                <div class="row g-3 p-3">
                    <div class="col-6">
                        <div class="text-center">
                            <div class="small text-dim">จำนวนใบที่ใช้</div>
                            <div class="fs-3 fw-bold">{{ number_format($profit['vip']['bills']) }} <small class="fs-6 text-dim">ใบ</small></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="small text-dim">เบียร์ที่แลกไป</div>
                            <div class="fs-3 fw-bold text-warning">{{ number_format($profit['vip']['bottles']) }} <small class="fs-6 text-dim">ขวด</small></div>
                        </div>
                    </div>
                </div>
                @if(count($profit['vip']['items']) > 0)
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>สินค้า</th><th class="text-end">แลกไป</th></tr>
                        </thead>
                        <tbody>
                            @foreach($profit['vip']['items'] as $v)
                                <tr>
                                    <td>{{ $v['name'] }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($v['qty']) }} {{ $v['unit'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

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
                            <td>
                                @if($st->station)
                                    <a href="{{ route('admin.report.station', $st->station) }}" class="text-decoration-none">
                                        {{ $st->station->name }} <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                @else - @endif
                            </td>
                            <td class="text-end">{{ number_format($st->bills) }}</td>
                            <td class="text-end text-success">{{ number_format($st->cash,0) }}</td>
                            <td class="text-end text-info">{{ number_format($st->transfer,0) }}</td>
                            <td class="text-end {{ $st->credit > 0 ? 'text-warning fw-bold' : 'text-success' }}">{{ number_format($st->credit,0) }}</td>
                            <td class="text-end fw-bold">{{ number_format($st->total,0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td class="text-end">รวมทุกจุด</td>
                        <td class="text-end">{{ number_format($byStation->sum('bills')) }}</td>
                        <td class="text-end text-success">{{ number_format($byStation->sum('cash'),0) }}</td>
                        <td class="text-end text-info">{{ number_format($byStation->sum('transfer'),0) }}</td>
                        <td class="text-end {{ $byStation->sum('credit') > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($byStation->sum('credit'),0) }}</td>
                        <td class="text-end">{{ number_format($byStation->sum('total'),0) }}</td>
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
                            <span class="fw-bold" style="color:#a5b4fc">{{ number_format($st->total,0) }}</span>
                        </div>
                        @forelse($items as $it)
                            <div class="d-flex justify-content-between border-bottom py-1">
                                <span>{{ $it->product_name }}
                                    <small class="text-muted">× {{ number_format($it->qty) }}</small>
                                </span>
                                <b>{{ number_format($it->total,0) }}</b>
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
                            <td class="text-end">{{ number_format($s->sales_total,0) }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($s->commission_rate,2),'0'),'.') }}%</td>
                            <td class="text-end fw-bold text-info">{{ number_format($s->commission,2) }}</td>
                            <td class="text-end {{ $s->outstanding > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ number_format($s->outstanding,0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">ไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td colspan="4" class="text-end">รวมคอมมิชชั่น</td>
                        <td class="text-end text-info">{{ number_format($sellers->sum('commission'),2) }}</td>
                        <td class="text-end text-danger">{{ number_format($sellers->sum('outstanding'),0) }}</td>
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
