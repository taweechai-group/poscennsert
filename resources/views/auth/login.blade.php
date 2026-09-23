@extends('layouts.app')
@section('title', 'เข้าสู่ระบบ')
@section('container-class', 'd-flex align-items-center justify-content-center min-vh-100 p-3')

@push('styles')
<style>
    .login-card { width: 100%; max-width: 400px; }
    .login-logo {
        width: 76px; height: 76px; border-radius: 1.3rem; background: var(--grad);
        display: grid; place-items: center; font-size: 2.4rem; color: #fff; margin: 0 auto;
        box-shadow: 0 10px 30px rgba(139,92,246,.55);
    }
    .pin-dots { display: flex; justify-content: center; gap: 14px; height: 24px; margin: 8px 0 22px; }
    .pin-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--stroke-2); transition: .15s; }
    .pin-dot.filled { background: var(--grad); border-color: transparent; transform: scale(1.1); }
    .keypad .key {
        aspect-ratio: 1.6; font-size: 1.6rem; font-weight: 600; border-radius: .9rem;
        background: rgba(255,255,255,.05); border: 1px solid var(--stroke-2); color: var(--txt);
        transition: .12s;
    }
    .keypad .key:hover { background: rgba(255,255,255,.1); }
    .keypad .key:active { transform: scale(.94); background: var(--grad); border-color: transparent; }
</style>
@endpush

@section('content')
<div class="card login-card p-4 p-md-5">
    <div class="text-center mb-4">
        <div class="login-logo mb-3"><i class="bi bi-cup-hot-fill"></i></div>
        <h3 class="fw-semibold mb-1">POS คอนเสิร์ต</h3>
        <div class="text-dim">ใส่ PIN เพื่อเข้าสู่ระบบ</div>
    </div>

    @error('pin')
        <div class="alert alert-danger py-2 text-center border-0">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('login') }}" id="pinForm">
        @csrf
        <input type="hidden" name="pin" id="pin">
        <div class="pin-dots" id="pinDots">
            @for($i=0;$i<4;$i++)<div class="pin-dot" data-i="{{ $i }}"></div>@endfor
        </div>

        <div class="row g-2 keypad">
            @foreach([1,2,3,4,5,6,7,8,9] as $n)
                <div class="col-4"><button type="button" class="btn key w-100" data-key="{{ $n }}">{{ $n }}</button></div>
            @endforeach
            <div class="col-4"><button type="button" class="btn key w-100 text-danger" id="clear"><i class="bi bi-x-lg"></i></button></div>
            <div class="col-4"><button type="button" class="btn key w-100" data-key="0">0</button></div>
            <div class="col-4"><button type="button" class="btn key w-100 text-warning" id="back"><i class="bi bi-backspace"></i></button></div>
        </div>

        <button type="submit" class="btn btn-grad w-100 mt-3 py-2 fs-5"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
    </form>

    <div class="mt-4 small text-dim">
        <details>
            <summary class="text-center" style="cursor:pointer">PIN ทดสอบ</summary>
            <div class="mt-2 d-flex justify-content-around text-center">
                <span>Admin<br><b class="text-light">9999</b></span>
                <span>คลัง<br><b class="text-light">8888</b></span>
                <span>แคชเชียร์<br><b class="text-light">1001–1004</b></span>
            </div>
        </details>
    </div>
</div>

@push('scripts')
<script>
    const pinInput = document.getElementById('pin');
    const dots = document.querySelectorAll('.pin-dot');
    function render() {
        const len = pinInput.value.length;
        dots.forEach((d, i) => d.classList.toggle('filled', i < len));
    }
    function push(k) { if (pinInput.value.length < 4) { pinInput.value += k; if (pinInput.value.length === 4) { document.getElementById('pinForm').submit(); return; } render(); } }
    document.querySelectorAll('.key[data-key]').forEach(b => b.addEventListener('click', () => push(b.dataset.key)));
    document.getElementById('clear').onclick = () => { pinInput.value = ''; render(); };
    document.getElementById('back').onclick = () => { pinInput.value = pinInput.value.slice(0, -1); render(); };
    document.addEventListener('keydown', e => {
        if (e.key >= '0' && e.key <= '9') push(e.key);
        else if (e.key === 'Backspace') { pinInput.value = pinInput.value.slice(0, -1); render(); }
        else if (e.key === 'Enter') document.getElementById('pinForm').submit();
    });
</script>
@endpush
@endsection
