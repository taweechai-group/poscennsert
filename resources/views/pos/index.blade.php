@extends('layouts.app')
@section('title', 'ขายสินค้า')

@push('styles')
<style>
    /* การ์ดสินค้าใหญ่ */
    .pos-tile {
        position: relative; cursor: pointer; overflow: hidden;
        border-radius: 1.25rem; border: 2px solid var(--stroke);
        transition: .16s cubic-bezier(.4,0,.2,1); user-select: none;
        min-height: 240px; display: flex; flex-direction: column;
    }
    .pos-tile:hover { transform: translateY(-4px); box-shadow: 0 18px 45px rgba(0,0,0,.5); }
    .pos-tile:active { transform: scale(.97); }
    .pos-tile .img-wrap {
        flex: 1; display: grid; place-items: center; overflow: hidden;
        background: rgba(0,0,0,.2);
    }
    .pos-tile .img-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .pos-tile .img-wrap .icon-fallback { font-size: 5rem; opacity: .9; }
    .pos-tile .info {
        padding: .85rem 1rem; background: rgba(0,0,0,.35); backdrop-filter: blur(6px);
    }
    .pos-tile .price { font-size: 1.5rem; font-weight: 700; }
    .pos-tile .in-cart-badge {
        position: absolute; top: 12px; right: 12px; min-width: 34px; height: 34px;
        border-radius: 50%; background: var(--grad); color: #fff; font-weight: 700;
        display: none; place-items: center; box-shadow: 0 4px 12px rgba(139,92,246,.6);
        padding: 0 8px; font-size: 1.05rem;
    }
    .pos-tile.has-qty .in-cart-badge { display: grid; }
    .pos-tile.out { opacity: .45; pointer-events: none; filter: grayscale(.6); }
    .stock-pill { font-size: .78rem; font-weight: 500; }

    /* แผงตะกร้า */
    .cart-panel { position: sticky; top: 84px; }
    .cart-line { border-bottom: 1px solid var(--stroke); }
    .qty-btn { width: 36px; height: 36px; display: grid; place-items: center; border-radius: .55rem; }
    .pay-btn { padding: .9rem; font-size: 1.15rem; font-weight: 600; border-radius: .9rem; }
</style>
@endpush

@section('content')
<div class="row g-4">
    {{-- ===== สินค้า ===== --}}
    <div class="col-xl-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3-gap-fill" style="color:var(--accent-2)"></i> เลือกสินค้า</h4>
            <span class="badge px-3 py-2" style="background:rgba(255,255,255,.06)"><i class="bi bi-shop"></i> {{ $station->name }}</span>
        </div>

        <div class="row g-4">
            @foreach($products as $p)
                <div class="col-6 col-md-4">
                    <div class="pos-tile card-hover {{ $p->stock_qty <= 0 ? 'out' : '' }}"
                         id="tile-{{ $p->id }}"
                         data-id="{{ $p->id }}" data-name="{{ $p->name }}"
                         data-price="{{ $p->price }}" data-unit="{{ $p->unit }}"
                         data-stock="{{ $p->stock_qty }}"
                         onclick="addToCart(this)">
                        <span class="in-cart-badge" id="badge-{{ $p->id }}">0</span>
                        <div class="img-wrap">
                            @if($p->imageUrl())
                                <img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}">
                            @else
                                <i class="bi {{ $p->icon }} icon-fallback" style="color:{{ $p->color }}"></i>
                            @endif
                        </div>
                        <div class="info">
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <div class="fw-semibold fs-5 lh-1">{{ $p->name }}</div>
                                    <span class="stock-pill badge mt-1 {{ $p->stock_qty > 20 ? 'bg-success' : ($p->stock_qty > 0 ? 'bg-warning text-dark' : 'bg-danger') }}"
                                          id="stock-{{ $p->id }}">
                                        <i class="bi bi-box"></i> {{ number_format($p->stock_qty) }} {{ $p->unit }}
                                    </span>
                                </div>
                                <div class="price" style="color:{{ $p->color }}">฿{{ number_format($p->price,0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===== ตะกร้า ===== --}}
    <div class="col-xl-4">
        <div class="card cart-panel p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0"><i class="bi bi-cart3" style="color:#34d399"></i> ตะกร้า</h5>
                <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" id="clearBtn" style="display:none">
                    <i class="bi bi-trash"></i>
                </button>
            </div>

            <div id="cartItems" style="min-height:120px; max-height:44vh; overflow-y:auto;">
                <div class="text-center text-dim py-5" id="cartEmpty">
                    <i class="bi bi-cart-x" style="font-size:3rem;opacity:.4"></i>
                    <div class="mt-2">แตะสินค้าเพื่อเพิ่มลงตะกร้า</div>
                </div>
            </div>

            <div class="mt-3 p-3 rounded-3" style="background:rgba(255,255,255,.04)">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-dim">รวมทั้งสิ้น</span>
                    <span class="fw-bold" style="font-size:2rem;background:var(--grad);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">
                        ฿<span id="cartTotal">0</span>
                    </span>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-6">
                    <button class="btn btn-grad-green pay-btn w-100" onclick="checkout('cash')">
                        <i class="bi bi-cash-coin"></i> เงินสด
                    </button>
                </div>
                <div class="col-6">
                    <button class="btn pay-btn w-100" style="background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff" onclick="checkout('transfer')">
                        <i class="bi bi-bank"></i> เงินโอน
                    </button>
                </div>
                <div class="col-12">
                    <button class="btn pay-btn w-100" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff" onclick="checkout('split')">
                        <i class="bi bi-wallet2"></i> เงินสด + เงินโอน
                    </button>
                </div>
                <div class="col-12">
                    <button class="btn btn-grad-amber pay-btn w-100" onclick="checkout('credit')">
                        <i class="bi bi-person-badge"></i> ขายเครดิต (เชียร์เบียร์)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    // เตรียมข้อมูลเชียร์เบียร์เป็น array ให้ JS (available = -1 คือไม่จำกัดวงเงิน)
    $sellersData = $sellers->map(fn ($s) => [
        'id' => $s->id,
        'label' => $s->code.' · '.$s->name,
        'available' => $s->hasUnlimitedCredit() ? -1 : $s->availableCredit(),
        'limit' => (float) $s->credit_limit,
        'outstanding' => $s->outstanding(),
    ])->values();
@endphp
@push('scripts')
<script>
let cart = {};

// ข้อมูลเชียร์เบียร์ (สำหรับเลือกตอนกดขายเครดิต)
const SELLERS = {{ Illuminate\Support\Js::from($sellersData) }};

function addToCart(el) {
    const id = el.dataset.id;
    const stock = parseInt(el.dataset.stock);
    if (!cart[id]) {
        cart[id] = { id, name: el.dataset.name, price: parseFloat(el.dataset.price),
                     unit: el.dataset.unit, qty: 0, stock };
    }
    if (cart[id].qty >= stock) {
        Toast.fire({ icon: 'warning', title: 'สต๊อกไม่พอ (เหลือ ' + stock + ')' });
        return;
    }
    cart[id].qty++;
    renderCart();
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty > cart[id].stock) { cart[id].qty = cart[id].stock; Toast.fire({icon:'warning',title:'สต๊อกไม่พอ'}); }
    if (cart[id].qty <= 0) delete cart[id];
    renderCart();
}

function renderCart() {
    const box = document.getElementById('cartItems');
    const ids = Object.keys(cart);

    // อัปเดต badge บนการ์ด
    document.querySelectorAll('.pos-tile').forEach(t => {
        const c = cart[t.dataset.id];
        const badge = document.getElementById('badge-' + t.dataset.id);
        if (c) { t.classList.add('has-qty'); badge.textContent = c.qty; }
        else { t.classList.remove('has-qty'); }
    });

    document.getElementById('clearBtn').style.display = ids.length ? 'inline-block' : 'none';

    if (ids.length === 0) {
        box.innerHTML = '<div class="text-center text-dim py-5" id="cartEmpty"><i class="bi bi-cart-x" style="font-size:3rem;opacity:.4"></i><div class="mt-2">แตะสินค้าเพื่อเพิ่มลงตะกร้า</div></div>';
        document.getElementById('cartTotal').textContent = '0';
        return;
    }
    let total = 0, html = '';
    ids.forEach(id => {
        const c = cart[id];
        const sub = c.price * c.qty;
        total += sub;
        html += `<div class="d-flex align-items-center justify-content-between cart-line py-2">
            <div class="flex-grow-1 me-2">
                <div class="fw-semibold">${c.name}</div>
                <small class="text-dim">฿${baht(c.price)} × ${c.qty} ${c.unit}</small>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-outline-light btn-sm qty-btn" onclick="changeQty('${id}',-1)"><i class="bi bi-dash-lg"></i></button>
                <span class="fw-bold" style="width:30px;text-align:center">${c.qty}</span>
                <button class="btn btn-outline-light btn-sm qty-btn" onclick="changeQty('${id}',1)"><i class="bi bi-plus-lg"></i></button>
                <span class="fw-bold ms-2" style="width:72px;text-align:right;color:#a5b4fc">฿${baht(sub)}</span>
            </div>
        </div>`;
    });
    box.innerHTML = html;
    document.getElementById('cartTotal').textContent = baht(total);
}

function clearCart() { cart = {}; renderCart(); }
function cartTotal() { return Object.values(cart).reduce((s, c) => s + c.price * c.qty, 0); }

async function checkout(type) {
    const items = Object.values(cart).map(c => ({ product_id: c.id, quantity: c.qty }));
    if (items.length === 0) { Toast.fire({ icon: 'info', title: 'ตะกร้าว่าง' }); return; }

    const total = cartTotal();
    let paid = total;
    let sellerId = null;   // จะถูกกำหนดตอนเลือกเชียร์เบียร์ในกรณีเครดิต
    let cashAmount = null, transferAmount = null;  // ใช้เฉพาะจ่ายผสม

    if (type === 'cash') {
        const { value } = await Swal.fire({
            title: 'รับเงินสด', html: `ยอดรวม <b style="color:#34d399">฿${baht(total)}</b>`,
            input: 'number', inputLabel: 'จำนวนเงินที่รับ', inputValue: total,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-check-lg"></i> คิดเงิน', cancelButtonText: 'ยกเลิก',
            inputValidator: v => (!v || parseFloat(v) < total) ? 'เงินไม่พอ' : null,
        });
        if (value === undefined) return;
        paid = parseFloat(value);
    } else if (type === 'transfer') {
        const ok = await Swal.fire({
            icon: 'question', title: 'ชำระด้วยเงินโอน?',
            html: `ยอด <b style="color:#22d3ee">฿${baht(total)}</b><br><small class="text-dim">ยืนยันว่าได้รับเงินโอนแล้ว</small>`,
            showCancelButton: true, confirmButtonText: 'ยืนยันรับโอน', cancelButtonText: 'ยกเลิก',
        });
        if (!ok.isConfirmed) return;
        paid = total;
    } else if (type === 'split') {
        const result = await Swal.fire({
            title: 'เงินสด + เงินโอน',
            html: `<div class="mb-2">ยอดบิล <b style="color:#a5b4fc;font-size:1.4rem">฿${baht(total)}</b></div>`
                + `<div style="text-align:left;max-width:280px;margin:0 auto">`
                + `<label style="font-size:.9rem" for="swalCash">ยอดเงินสด (฿)</label>`
                + `<input id="swalCash" type="number" min="0" step="1" class="swal2-input" style="width:100%;margin:.25rem 0" placeholder="0">`
                + `<label style="font-size:.9rem" for="swalTransfer">ยอดเงินโอน (฿)</label>`
                + `<input id="swalTransfer" type="number" min="0" step="1" class="swal2-input" style="width:100%;margin:.25rem 0" placeholder="0">`
                + `<div id="swalSplitNote" style="font-size:.9rem;min-height:22px;color:#94a3b8">กรอกช่องไหนก็ได้ อีกช่องจะคำนวณให้อัตโนมัติ</div>`
                + `</div>`,
            didOpen: () => {
                const cashInp = document.getElementById('swalCash');
                const trInp = document.getElementById('swalTransfer');
                const note = document.getElementById('swalSplitNote');

                // กรอกช่องไหน อีกช่องเติมส่วนที่เหลือให้เอง
                function sync(src, dst) {
                    const v = parseFloat(src.value);
                    if (src.value === '' || isNaN(v)) { dst.value = ''; showNote(); return; }
                    dst.value = Math.max(0, Math.round((total - v) * 100) / 100);
                    showNote();
                }
                function showNote() {
                    const c = parseFloat(cashInp.value) || 0;
                    const t = parseFloat(trInp.value) || 0;
                    if (c <= 0 || t <= 0) {
                        note.textContent = 'กรอกช่องไหนก็ได้ อีกช่องจะคำนวณให้อัตโนมัติ';
                        note.style.color = '#94a3b8';
                    } else {
                        note.innerHTML = `สด ฿${baht(c)} + โอน ฿${baht(t)} = <b>฿${baht(c + t)}</b>`;
                        note.style.color = '#34d399';
                    }
                }
                cashInp.addEventListener('input', () => sync(cashInp, trInp));
                trInp.addEventListener('input', () => sync(trInp, cashInp));
                cashInp.focus();
            },
            showCancelButton: true, confirmButtonText: 'ยืนยันรับชำระ', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#6366f1',
            preConfirm: () => {
                const c = parseFloat(document.getElementById('swalCash').value) || 0;
                const t = parseFloat(document.getElementById('swalTransfer').value) || 0;
                if (c <= 0 || t <= 0) { Swal.showValidationMessage('ต้องระบุยอดทั้งเงินสดและเงินโอน'); return false; }
                if (Math.abs((c + t) - total) > 0.01) { Swal.showValidationMessage('สด + โอน ต้องเท่ากับยอดบิลพอดี'); return false; }
                return { cash: c, transfer: t };
            },
        });
        if (!result.isConfirmed) return;
        cashAmount = result.value.cash;
        transferAmount = result.value.transfer;
        paid = total;
    } else { // credit — เลือกเชียร์เบียร์ในป๊อปอัปนี้เลย
        if (SELLERS.length === 0) {
            Swal.fire({ icon: 'warning', title: 'ยังไม่มีเชียร์เบียร์', text: 'กรุณาเพิ่มเชียร์เบียร์ก่อน' });
            return;
        }
        // สร้างตัวเลือก dropdown (disable คนที่วงเงินไม่พอสำหรับบิลนี้)
        let options = '<option value="">— เลือกเชียร์เบียร์ —</option>';
        SELLERS.forEach(s => {
            const over = s.available >= 0 && total > s.available;
            const availTxt = s.available < 0 ? 'ไม่จำกัด' : '฿' + baht(s.available);
            options += `<option value="${s.id}" ${over ? 'disabled' : ''}>`
                     + `${s.label} — ใช้ได้ ${availTxt}${over ? ' (ไม่พอ)' : ''}</option>`;
        });

        const result = await Swal.fire({
            title: 'ขายเครดิต',
            html: `<div class="mb-2">ยอดบิล <b style="color:#fbbf24;font-size:1.4rem">฿${baht(total)}</b></div>`
                + `<select id="swalSeller" class="swal2-select" style="width:90%">${options}</select>`
                + `<div id="swalCreditNote" class="mt-2" style="font-size:.9rem;min-height:22px"></div>`,
            didOpen: () => {
                const sel = document.getElementById('swalSeller');
                const note = document.getElementById('swalCreditNote');
                sel.addEventListener('change', () => {
                    const s = SELLERS.find(x => x.id == sel.value);
                    if (!s) { note.innerHTML = ''; return; }
                    if (s.available < 0) note.innerHTML = '<span style="color:#22d3ee">เครดิตไม่จำกัด</span>';
                    else note.innerHTML = `<span class="text-dim">คงเหลือวงเงินหลังขาย </span><b style="color:#34d399">฿${baht(s.available - total)}</b>`;
                });
            },
            showCancelButton: true, confirmButtonText: 'ยืนยันขายเครดิต', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#f59e0b',
            preConfirm: () => {
                const v = document.getElementById('swalSeller').value;
                if (!v) { Swal.showValidationMessage('กรุณาเลือกเชียร์เบียร์'); return false; }
                const s = SELLERS.find(x => x.id == v);
                if (s.available >= 0 && total > s.available) { Swal.showValidationMessage('เกินวงเงินเครดิต'); return false; }
                return v;
            },
        });
        if (!result.isConfirmed) return;
        sellerId = result.value;
    }

    const res = await fetch('{{ route('pos.checkout') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            items, payment_type: type, seller_id: sellerId || null, paid,
            cash_amount: cashAmount, transfer_amount: transferAmount,
        }),
    });
    const data = await res.json();
    if (!data.ok) { Swal.fire({ icon: 'error', title: 'ขายไม่สำเร็จ', text: data.message }); return; }

    const change = paid - total;
    let detail = '';
    if (type === 'cash') detail = `<br>รับ ฿${baht(paid)}<br><b style="color:#34d399;font-size:1.3rem">ทอน ฿${baht(change)}</b>`;
    else if (type === 'transfer') detail = '<br><span style="color:#22d3ee">ชำระด้วยเงินโอน</span>';
    else if (type === 'split') detail = `<br><span style="color:#a5b4fc">เงินสด ฿${baht(cashAmount)} + เงินโอน ฿${baht(transferAmount)}</span>`;
    else detail = '<br><span style="color:#fbbf24">ลงเครดิตแล้ว</span>';

    await Swal.fire({
        icon: 'success', title: 'ขายสำเร็จ!',
        html: `บิล <b>${data.bill_no}</b><br>ยอด ฿${baht(total)}` + detail,
        showCancelButton: true, confirmButtonText: '<i class="bi bi-printer"></i> พิมพ์ใบเสร็จ', cancelButtonText: 'ขายต่อ',
    }).then(r => { if (r.isConfirmed) window.open(data.receipt_url, '_blank'); });

    clearCart();
    location.reload();
}
</script>
@endpush
@endsection
