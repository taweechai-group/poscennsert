@extends('layouts.app')
@section('title', 'จัดการพนักงาน')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-people text-primary"></i> พนักงาน</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-person-plus"></i> เพิ่มพนักงาน</button>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th>ชื่อ</th><th>บทบาท</th><th>จุดประจำ</th><th>สถานะ</th></tr></thead>
            <tbody>
                @foreach($users as $u)
                    @php
                        $roleMap = ['admin'=>['ผู้ดูแล','danger'],'warehouse'=>['คลังกลาง','primary'],'cashier'=>['แคชเชียร์','success']];
                        [$rLabel,$rColor] = $roleMap[$u->role] ?? [$u->role,'secondary'];
                    @endphp
                    <tr>
                        <td><i class="bi bi-person-circle"></i> {{ $u->name }}</td>
                        <td><span class="badge bg-{{ $rColor }}">{{ $rLabel }}</span></td>
                        <td>{{ $u->station?->name ?? '-' }}</td>
                        <td>@if($u->is_active)<span class="badge bg-success">ใช้งาน</span>@else<span class="badge bg-secondary">ปิด</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.users.save') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เพิ่มพนักงาน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">ชื่อ</label><input name="name" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">บทบาท</label>
                    <select name="role" id="roleSelect" class="form-select" onchange="toggleStation()">
                        <option value="cashier">แคชเชียร์ (ประจำจุด)</option>
                        <option value="warehouse">คลังกลาง</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                    </select>
                </div>
                <div class="mb-2" id="stationBox">
                    <label class="form-label">จุดประจำ</label>
                    <select name="station_id" class="form-select">
                        <option value="">— เลือกจุด —</option>
                        @foreach($stations->where('type','pos') as $st)
                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2"><label class="form-label">PIN (4-6 หลัก)</label><input name="pin" class="form-control" maxlength="6" pattern="\d{4,6}" placeholder="เช่น 1005" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary">บันทึก</button></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleStation() {
    document.getElementById('stationBox').style.display =
        document.getElementById('roleSelect').value === 'cashier' ? 'block' : 'none';
}
toggleStation();
</script>
@endpush
@endsection
