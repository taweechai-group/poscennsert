@extends('layouts.app')
@section('title', 'จัดการสินค้า')

@push('styles')
<style>
    .prod-card .thumb {
        height: 150px; border-radius: .9rem; overflow: hidden; background: rgba(0,0,0,.25);
        display: grid; place-items: center;
    }
    .prod-card .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .prod-card .thumb i { font-size: 3.5rem; }
    .img-drop {
        border: 2px dashed var(--stroke-2); border-radius: .9rem; padding: 1rem; text-align: center;
        cursor: pointer; transition: .15s;
    }
    .img-drop:hover { border-color: var(--accent); background: rgba(99,102,241,.08); }
    .img-drop img { max-height: 130px; border-radius: .6rem; }
</style>
@endpush

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-semibold"><i class="bi bi-box" style="color:var(--accent-2)"></i> จัดการสินค้า</h4>
        <button class="btn btn-grad" onclick="openProduct()"><i class="bi bi-plus-lg"></i> เพิ่มสินค้า</button>
    </div>

    <div class="row g-4">
        @foreach($products as $p)
            @php
                $pData = [
                    'id' => $p->id, 'name' => $p->name, 'unit' => $p->unit,
                    'price' => $p->price, 'cost' => $p->cost, 'icon' => $p->icon, 'color' => $p->color,
                ];
            @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card card-hover prod-card p-3 h-100">
                    <div class="thumb mb-3">
                        @if($p->imageUrl())
                            <img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}">
                        @else
                            <i class="bi {{ $p->icon }}" style="color:{{ $p->color }}"></i>
                        @endif
                    </div>
                    <div class="fw-semibold fs-5">{{ $p->name }}</div>
                    <div class="mb-1"><span class="fw-bold fs-4" style="color:{{ $p->color }}">฿{{ number_format($p->price,0) }}</span> <small class="text-dim">/ {{ $p->unit }}</small></div>
                    <small class="text-dim">ต้นทุน ฿{{ number_format($p->cost,0) }}</small>
                    <button class="btn btn-sm btn-outline-light mt-3"
                            onclick="openProduct({{ Js::from($pData) }}, {{ Js::from($p->imageUrl()) }})">
                        <i class="bi bi-pencil"></i> แก้ไข
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="productForm" class="modal-content" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">เพิ่มสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- อัปโหลดรูป --}}
                <label class="form-label">รูปสินค้า</label>
                <label class="img-drop d-block mb-3" for="pf_image">
                    <div id="pf_preview_box">
                        <i class="bi bi-image text-dim" style="font-size:2.5rem"></i>
                        <div class="text-dim small">คลิกเพื่อเลือกรูป (สูงสุด 4MB)</div>
                    </div>
                    <img id="pf_preview" class="d-none" alt="preview">
                </label>
                <input type="file" name="image" id="pf_image" accept="image/*" class="d-none" onchange="previewImg(this)">

                <div class="mb-2"><label class="form-label">ชื่อสินค้า</label><input name="name" id="pf_name" class="form-control" required></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">หน่วย</label><input name="unit" id="pf_unit" class="form-control" placeholder="กระป๋อง" required></div>
                    <div class="col-6 mb-2"><label class="form-label">ราคาขาย</label><input type="number" name="price" id="pf_price" class="form-control" step="0.01" required></div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">ต้นทุน</label><input type="number" name="cost" id="pf_cost" class="form-control" step="0.01" value="0"></div>
                    <div class="col-6 mb-2"><label class="form-label">สีประจำ</label><input type="color" name="color" id="pf_color" class="form-control form-control-color w-100" value="#6366f1"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label">ไอคอน (ใช้เมื่อไม่มีรูป)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary"><i class="bi" id="pf_icon_preview"></i></span>
                        <input name="icon" id="pf_icon" class="form-control" placeholder="bi-cup-straw" value="bi-box" oninput="document.getElementById('pf_icon_preview').className='bi '+this.value">
                    </div>
                    <small class="text-dim">เช่น bi-cup-straw, bi-droplet, bi-snow</small>
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
const modal = new bootstrap.Modal('#productModal');

function openProduct(p = null, imageUrl = null) {
    const form = document.getElementById('productForm');
    document.getElementById('pf_image').value = '';
    if (p) {
        form.action = '{{ url('admin/products') }}/' + p.id;
        document.getElementById('productModalTitle').textContent = 'แก้ไขสินค้า';
        pf_name.value = p.name; pf_unit.value = p.unit; pf_price.value = p.price;
        pf_cost.value = p.cost; pf_color.value = p.color; pf_icon.value = p.icon;
        showPreview(imageUrl);
    } else {
        form.action = '{{ url('admin/products') }}';
        document.getElementById('productModalTitle').textContent = 'เพิ่มสินค้า';
        form.reset(); pf_icon.value = 'bi-box'; pf_color.value = '#6366f1';
        showPreview(null);
    }
    document.getElementById('pf_icon_preview').className = 'bi ' + pf_icon.value;
    modal.show();
}

function showPreview(url) {
    const img = document.getElementById('pf_preview');
    const box = document.getElementById('pf_preview_box');
    if (url) { img.src = url; img.classList.remove('d-none'); box.classList.add('d-none'); }
    else { img.classList.add('d-none'); box.classList.remove('d-none'); }
}

function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => showPreview(e.target.result);
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
@endsection
