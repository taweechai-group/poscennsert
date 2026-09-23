@extends('layouts.app')
@section('title', 'จัดการจุดขาย')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-shop text-primary"></i> จุดขาย / คลัง</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stationModal"><i class="bi bi-plus-lg"></i> เพิ่มจุด</button>
    </div>

    <div class="row g-3">
        @foreach($stations as $st)
            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex align-items-center">
                        <div class="fs-1 me-3 {{ $st->isWarehouse() ? 'text-primary' : 'text-secondary' }}">
                            <i class="bi {{ $st->isWarehouse() ? 'bi-house-fill' : 'bi-shop' }}"></i>
                        </div>
                        <div>
                            <div class="fw-semibold fs-5">{{ $st->name }}</div>
                            <span class="badge {{ $st->isWarehouse() ? 'bg-primary' : 'bg-secondary' }}">{{ $st->isWarehouse() ? 'คลังกลาง' : 'จุดขาย' }}</span>
                            <div><small class="text-muted"><i class="bi bi-people"></i> {{ $st->users_count }} คน</small></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="stationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.stations.save') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เพิ่มจุดขาย/คลัง</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">ชื่อ</label><input name="name" class="form-control" placeholder="POS-05" required></div>
                <div class="mb-2"><label class="form-label">ประเภท</label>
                    <select name="type" class="form-select"><option value="pos">จุดขาย</option><option value="warehouse">คลังกลาง</option></select>
                </div>
                <div class="mb-2"><label class="form-label">รหัส</label><input name="code" class="form-control" placeholder="P05"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary">บันทึก</button></div>
        </form>
    </div>
</div>
@endsection
