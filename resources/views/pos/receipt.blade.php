<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จ {{ $sale->bill_no }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f1f3f7; padding: 20px; }
        .receipt {
            width: 300px; margin: 0 auto; background: #fff; padding: 16px;
            font-size: 13px; color: #000;
        }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .txt-right { text-align: right; }
        h3 { font-size: 16px; }
        .total { font-size: 16px; font-weight: 600; }
        .actions { text-align: center; margin-top: 16px; }
        .btn { padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-family: 'Kanit'; margin: 0 4px; }
        .btn-print { background: #0d6efd; color: #fff; }
        .btn-close { background: #6c757d; color: #fff; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .receipt { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="receipt" id="print-area">
        <div class="center">
            <h3>POS คอนเสิร์ต</h3>
            <div>{{ $sale->station->name }}</div>
            <div class="line"></div>
        </div>
        <div class="row"><span>เลขที่บิล</span><span>{{ $sale->bill_no }}</span></div>
        <div class="row"><span>วันที่</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>พนักงาน</span><span>{{ $sale->user->name ?? '-' }}</span></div>
        @if($sale->seller)
            <div class="row"><span>เชียร์เบียร์</span><span>{{ $sale->seller->code }} {{ $sale->seller->name }}</span></div>
        @endif
        <div class="line"></div>

        <table>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name }}<br><small>{{ number_format($item->price,0) }} × {{ $item->quantity }}</small></td>
                    <td class="txt-right">{{ number_format($item->subtotal, 0) }}</td>
                </tr>
            @endforeach
        </table>

        <div class="line"></div>
        <div class="row total"><span>รวมทั้งสิ้น</span><span>฿{{ number_format($sale->total, 0) }}</span></div>

        @if($sale->isCredit())
            <div class="center" style="margin-top:6px; color:#b8860b; font-weight:600;">** ขายแบบเครดิต **</div>
        @elseif($sale->payment_type === 'transfer')
            <div class="row"><span>ชำระโดย</span><span>เงินโอน</span></div>
            <div class="row"><span>ยอดชำระ</span><span>฿{{ number_format($sale->paid, 0) }}</span></div>
        @else
            <div class="row"><span>รับเงินสด</span><span>฿{{ number_format($sale->paid, 0) }}</span></div>
            <div class="row"><span>เงินทอน</span><span>฿{{ number_format($sale->change, 0) }}</span></div>
        @endif

        <div class="line"></div>
        <div class="center"><small>ขอบคุณที่ใช้บริการ 🍺</small></div>
    </div>

    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">🖨️ พิมพ์</button>
        <button class="btn btn-close" onclick="window.close()">ปิด</button>
    </div>

    <script>
        // เปิดขึ้นมาแล้วสั่งพิมพ์เลย (สำหรับเครื่องปรินท์สลิป)
        // window.onload = () => window.print();
    </script>
</body>
</html>
