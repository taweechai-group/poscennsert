@extends('layouts.app')
@section('title', 'เข้าสู่ระบบ')
@section('container-class', '')

@push('styles')
<style>
    body { background: var(--bg-0); }

    /* การ์ดใบเดียวกลางจอ พอดี 1 หน้า ไม่ต้องสกรอลล์ */
    .login-shell {
        min-height: 100vh;
        display: grid; place-items: center;
        padding: 1.5rem;
        background:
            radial-gradient(900px 500px at 15% 0%, rgba(99,102,241,.14), transparent 60%),
            radial-gradient(900px 500px at 85% 100%, rgba(139,92,246,.14), transparent 60%),
            var(--bg-0);
    }
    .login-card {
        width: 100%; max-width: 860px;
        /* ล็อกความสูงไม่ให้เกินจอ การ์ดจึงพอดี 1 หน้าเสมอ */
        max-height: calc(100vh - 3rem);
        display: grid; grid-template-columns: 1fr 340px;
        overflow: hidden;
        border-radius: 1.25rem;
        background: linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.015));
        border: 1px solid var(--stroke);
        box-shadow: 0 24px 70px rgba(0,0,0,.55);
        backdrop-filter: blur(12px);
    }

    /* ฝั่งรูป: bg.jpg เป็นจัตุรัส 1254×1254 ย่อให้พอดีช่อง เห็นครบทั้งภาพ */
    .login-visual {
        position: relative;
        display: grid; place-items: center;
        padding: 1.25rem;
        overflow: hidden;
        background: var(--bg-1);
    }
    .login-visual::before {
        content: ''; position: absolute; inset: -40px;
        background: url('{{ asset('images/bg.jpg') }}') center / cover no-repeat;
        filter: blur(34px) brightness(.4) saturate(1.1);
    }
    .login-visual img {
        position: relative; z-index: 1;
        display: block;
        max-width: 100%; max-height: 100%;
        width: auto; height: auto;
        border-radius: .85rem;
        box-shadow: 0 14px 40px rgba(0,0,0,.55);
    }
    .login-visual-text {
        position: absolute; left: 0; right: 0; bottom: 0; z-index: 2;
        padding: 1.5rem;
        background: linear-gradient(transparent, rgba(11,17,32,.9));
        text-shadow: 0 2px 10px rgba(0,0,0,.8);
    }

    /* ฝั่งฟอร์ม */
    .login-form {
        padding: 1.75rem 1.5rem;
        display: flex; flex-direction: column; justify-content: center;
        overflow-y: auto;
        background: rgba(11,17,32,.55);
        border-left: 1px solid var(--stroke);
    }
    .login-logo {
        width: 58px; height: 58px; border-radius: 1rem; background: var(--grad);
        display: grid; place-items: center; font-size: 1.8rem; color: #fff; margin: 0 auto;
        box-shadow: 0 8px 24px rgba(139,92,246,.5);
    }
    .pin-dots { display: flex; justify-content: center; gap: 13px; height: 20px; margin: 6px 0 16px; }
    .pin-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--stroke-2); transition: .15s; }
    .pin-dot.filled { background: var(--grad); border-color: transparent; transform: scale(1.1); }
    .keypad .key {
        aspect-ratio: 1.9; font-size: 1.3rem; font-weight: 600; border-radius: .75rem;
        background: rgba(255,255,255,.05); border: 1px solid var(--stroke-2); color: var(--txt);
        transition: .12s;
    }
    .keypad .key:hover { background: rgba(255,255,255,.1); }
    .keypad .key:active { transform: scale(.94); background: var(--grad); border-color: transparent; }

    /* จอเล็ก: เรียงบน-ล่าง ยังพอดีหน้าจอ */
    @media (max-width: 767.98px) {
        .login-shell { padding: 0; align-items: stretch; }
        .login-card {
            grid-template-columns: 1fr; max-width: 420px;
            border-radius: 0; border: none; box-shadow: none;
            min-height: 100vh; max-height: none;
        }
        .login-visual { max-height: 32vh; padding: .75rem; }
        .login-form { border-left: none; border-top: 1px solid var(--stroke); }
    }
</style>
@endpush

@section('content')
<div class="login-shell">
    <div class="login-card">
        <div class="login-visual">
            <img src="{{ asset('images/bg.jpg') }}" alt="">
            <div class="login-visual-text">
                <h4 class="fw-semibold mb-0">{{ $currentEvent->name ?? 'POS คอนเสิร์ต' }}</h4>
                @if(!empty($currentEvent?->location))
                    <div class="small text-light opacity-75"><i class="bi bi-geo-alt"></i> {{ $currentEvent->location }}</div>
                @endif
            </div>
        </div>

        <div class="login-form">
            <div class="text-center mb-3">
                <div class="login-logo mb-2"><i class="bi bi-cup-hot-fill"></i></div>
                <div class="text-dim small">ใส่ PIN เพื่อเข้าสู่ระบบ</div>
            </div>

            @error('pin')
                <div class="alert alert-danger py-2 text-center border-0 small">{{ $message }}</div>
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

                <button type="submit" class="btn btn-grad w-100 mt-3 py-2"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
            </form>

            <div class="mt-3 small text-dim">
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
    let submitting = false;
    function submitPin() {
        if (submitting) return;
        submitting = true;
        document.getElementById('pinForm').submit();
    }
    function push(k) {
        if (submitting || pinInput.value.length >= 4) return;
        pinInput.value += k;
        render();
        if (pinInput.value.length === 4) setTimeout(submitPin, 180);
    }
    document.querySelectorAll('.key[data-key]').forEach(b => b.addEventListener('click', () => push(b.dataset.key)));
    document.getElementById('clear').onclick = () => { if (submitting) return; pinInput.value = ''; render(); };
    document.getElementById('back').onclick = () => { if (submitting) return; pinInput.value = pinInput.value.slice(0, -1); render(); };
    document.addEventListener('keydown', e => {
        if (e.key >= '0' && e.key <= '9') push(e.key);
        else if (e.key === 'Backspace') { if (submitting) return; pinInput.value = pinInput.value.slice(0, -1); render(); }
        else if (e.key === 'Enter') submitPin();
    });
</script>
@endpush
@endsection
