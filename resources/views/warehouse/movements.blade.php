@extends('layouts.app')
@section('title', 'การเคลื่อนไหวสต๊อก')

@section('content')
<div class="container">
    <h5 class="mb-3"><i class="bi bi-arrow-left-right text-primary"></i> ประวัติการเคลื่อนไหวสต๊อก</h5>

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
    <div class="mt-3">{{ $movements->links() }}</div>
</div>
@endsection
