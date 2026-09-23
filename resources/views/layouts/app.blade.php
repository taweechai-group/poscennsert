<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ระบบขายเครื่องดื่ม') · {{ config('app.name') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-0: #0b1120;
            --bg-1: #131c31;
            --bg-2: #1b2540;
            --stroke: rgba(255,255,255,.08);
            --stroke-2: rgba(255,255,255,.14);
            --txt: #e7ecf5;
            --txt-dim: #97a3bd;
            --accent: #6366f1;
            --accent-2: #8b5cf6;
            --grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #d946ef 100%);
            --grad-green: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --grad-amber: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        * { font-family: 'Kanit', sans-serif; }
        body {
            background:
                radial-gradient(1200px 600px at 80% -10%, rgba(139,92,246,.18), transparent 60%),
                radial-gradient(1000px 500px at 0% 0%, rgba(99,102,241,.16), transparent 55%),
                var(--bg-0);
            color: var(--txt);
            min-height: 100vh;
        }
        .text-dim { color: var(--txt-dim) !important; }

        /* ---------- Glass cards ---------- */
        .card, .glass {
            background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.015));
            border: 1px solid var(--stroke);
            border-radius: 1rem;
            backdrop-filter: blur(12px);
            color: var(--txt);
            box-shadow: 0 8px 30px rgba(0,0,0,.25);
        }
        .card-hover { transition: .18s cubic-bezier(.4,0,.2,1); }
        .card-hover:hover { transform: translateY(-3px); border-color: var(--stroke-2); box-shadow: 0 14px 40px rgba(0,0,0,.4); }

        /* ---------- Navbar ---------- */
        .app-nav {
            background: rgba(11,17,32,.75);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--stroke);
        }
        .app-nav .nav-link {
            color: var(--txt-dim); border-radius: .6rem; padding: .45rem .85rem !important;
            font-weight: 500; transition: .15s; margin: 0 1px;
        }
        .app-nav .nav-link:hover { color: #fff; background: rgba(255,255,255,.06); }
        .app-nav .nav-link.active { color: #fff; background: var(--grad); }
        .brand-badge {
            width: 38px; height: 38px; border-radius: .7rem; background: var(--grad);
            display: grid; place-items: center; font-size: 1.2rem; color: #fff;
            box-shadow: 0 4px 14px rgba(139,92,246,.5);
        }

        /* ---------- Buttons ---------- */
        .btn { border-radius: .65rem; font-weight: 500; }
        .btn-grad { background: var(--grad); border: none; color: #fff; }
        .btn-grad:hover { filter: brightness(1.1); color: #fff; }
        .btn-grad-green { background: var(--grad-green); border: none; color: #fff; }
        .btn-grad-green:hover { filter: brightness(1.1); color: #fff; }
        .btn-grad-amber { background: var(--grad-amber); border: none; color: #fff; }
        .btn-grad-amber:hover { filter: brightness(1.1); color: #fff; }

        /* ---------- Form controls (dark) ---------- */
        .form-control, .form-select {
            background: rgba(255,255,255,.05); border: 1px solid var(--stroke-2); color: var(--txt);
            border-radius: .6rem;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,.07); color: var(--txt);
            border-color: var(--accent); box-shadow: 0 0 0 .2rem rgba(99,102,241,.25);
        }
        .form-control::placeholder { color: #5f6b85; }
        .form-select option { background: var(--bg-1); color: var(--txt); }
        .form-label { color: var(--txt-dim); font-size: .85rem; font-weight: 500; }

        /* ---------- Tables ---------- */
        .table { color: var(--txt); --bs-table-bg: transparent; }
        .table > :not(caption) > * > * { border-color: var(--stroke); background: transparent; color: var(--txt); }
        .table thead th { color: var(--txt-dim); font-weight: 500; text-transform: none; border-bottom: 1px solid var(--stroke-2); }
        .table-hover > tbody > tr:hover > * { background: rgba(255,255,255,.04); }
        /* override Bootstrap light utilities สำหรับธีมมืด */
        .table-light, .table-light > th, .table-light > td { --bs-table-bg: rgba(255,255,255,.05) !important; --bs-table-color: var(--txt) !important; color: var(--txt) !important; }
        thead.table-light th { background: rgba(255,255,255,.04) !important; color: var(--txt-dim) !important; }
        .bg-light { background: rgba(255,255,255,.06) !important; }
        .text-dark { color: var(--txt) !important; }
        .badge.bg-light { background: rgba(255,255,255,.1) !important; color: var(--txt) !important; }
        .alert-info { background: rgba(59,130,246,.14); border: 1px solid rgba(59,130,246,.3); color: #bfdbfe; }
        .alert-danger { background: rgba(239,68,68,.14); border: 1px solid rgba(239,68,68,.35); color: #fecaca; }
        .btn-light, .btn-outline-light { color: var(--txt); }
        .btn-light { background: rgba(255,255,255,.08); border-color: var(--stroke-2); }
        .input-group-text { background: rgba(255,255,255,.05); border-color: var(--stroke-2); color: var(--txt-dim); }
        .form-control-color { background: rgba(255,255,255,.05); }
        details summary { color: var(--txt-dim); }
        .page-link { background: rgba(255,255,255,.05); border-color: var(--stroke-2); color: var(--txt); }
        .page-link:hover { background: rgba(255,255,255,.1); color: #fff; }
        .page-item.active .page-link { background: var(--accent); border-color: var(--accent); }
        .page-item.disabled .page-link { background: rgba(255,255,255,.02); color: #5f6b85; }

        /* ---------- Stat tiles ---------- */
        .stat-tile { padding: 1.1rem 1.25rem; }
        .stat-icon { width: 46px; height: 46px; border-radius: .8rem; display: grid; place-items: center; font-size: 1.4rem; }

        /* ---------- Modal (dark) ---------- */
        .modal-content { background: var(--bg-1); color: var(--txt); border: 1px solid var(--stroke-2); border-radius: 1rem; }
        .modal-header, .modal-footer { border-color: var(--stroke); }
        .btn-close { filter: invert(1) grayscale(1) brightness(1.6); }

        /* ---------- Misc ---------- */
        .badge-role { font-size: .7rem; font-weight: 500; }
        hr { border-color: var(--stroke); opacity: 1; }
        .dropdown-menu { background: var(--bg-2); border: 1px solid var(--stroke-2); }
        .dropdown-item { color: var(--txt); }
        .dropdown-item:hover { background: rgba(255,255,255,.07); color: #fff; }
        a { color: #a5b4fc; }

        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 20px; }
        ::-webkit-scrollbar-track { background: transparent; }

        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; color: #000; }
            .no-print { display: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
@auth
    @php $u = auth()->user(); @endphp
    <nav class="navbar navbar-expand-lg app-nav sticky-top no-print py-2">
        <div class="container-fluid px-lg-4">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold text-white" href="{{ route('home') }}">
                <span class="brand-badge"><i class="bi bi-cup-hot-fill"></i></span>
                <span class="d-none d-sm-inline">POS คอนเสิร์ต</span>
            </a>
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <i class="bi bi-list fs-3"></i>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav mx-auto">
                    @if($u->isCashier())
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('pos.index') ? 'active' : '' }}" href="{{ route('pos.index') }}"><i class="bi bi-cart3"></i> ขายสินค้า</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('pos.sales') ? 'active' : '' }}" href="{{ route('pos.sales') }}"><i class="bi bi-receipt"></i> บิลวันนี้</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}" href="{{ route('requisitions.index') }}"><i class="bi bi-box-seam"></i> เบิกสินค้า</a></li>
                    @endif
                    @if($u->isWarehouse())
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('warehouse.index') ? 'active' : '' }}" href="{{ route('warehouse.index') }}"><i class="bi bi-boxes"></i> สต๊อกกลาง</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}" href="{{ route('requisitions.index') }}"><i class="bi bi-inbox"></i> ใบเบิก</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('warehouse.movements') ? 'active' : '' }}" href="{{ route('warehouse.movements') }}"><i class="bi bi-arrow-left-right"></i> การเคลื่อนไหว</a></li>
                    @endif
                    @if($u->isAdmin())
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> ภาพรวม</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.products') ? 'active' : '' }}" href="{{ route('admin.products') }}"><i class="bi bi-box"></i> สินค้า</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.stations') ? 'active' : '' }}" href="{{ route('admin.stations') }}"><i class="bi bi-shop"></i> จุดขาย</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}"><i class="bi bi-people"></i> พนักงาน</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.report') ? 'active' : '' }}" href="{{ route('admin.report') }}"><i class="bi bi-graph-up"></i> สรุปยอด</a></li>
                    @endif
                    @if(!$u->isAdmin())
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('sellers.*') ? 'active' : '' }}" href="{{ route('sellers.index') }}"><i class="bi bi-person-badge"></i> เชียร์เบียร์</a></li>
                    @endif
                </ul>
                <div class="dropdown">
                    <button class="btn btn-sm d-flex align-items-center gap-2 text-white dropdown-toggle" style="background:rgba(255,255,255,.06)" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5"></i>
                        <span class="text-start lh-sm d-none d-md-block">
                            <span class="d-block">{{ $u->name }}</span>
                            <small class="text-dim">{{ $u->station->name ?? ucfirst($u->role) }}</small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-1 small text-dim">
                            <span class="badge" style="background:var(--grad)">{{ $u->role }}</span>
                            @if($u->station)<span class="badge bg-secondary">{{ $u->station->name }}</span>@endif
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
@endauth

    <main class="@yield('container-class', 'container-fluid px-lg-4 py-4')">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.CSRF = document.querySelector('meta[name="csrf-token"]').content;
        // SweetAlert2 ธีมมืด
        const swalDark = {
            background: '#131c31', color: '#e7ecf5',
            confirmButtonColor: '#6366f1', cancelButtonColor: '#374151',
        };
        window.Swal = Swal.mixin(swalDark);
        window.Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false,
            timer: 2200, timerProgressBar: true, ...swalDark,
        });
        @if(session('success'))
            Toast.fire({ icon: 'success', title: @json(session('success')) });
        @endif
        @if(session('error'))
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: @json(session('error')) });
        @endif
        window.baht = n => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    </script>
    @stack('scripts')
</body>
</html>
