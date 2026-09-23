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
        .copy-label {
            text-align: center; font-weight: 600; font-size: 13px;
            border: 1px solid #000; padding: 3px 0; margin-bottom: 6px;
        }
        /* รายการสินค้า เน้นชื่อและจำนวนให้ใหญ่ อ่านง่ายตอนจ่ายของ */
        .stub-table td { padding: 7px 0; border-bottom: 1px dotted #999; }
        .stub-table .qty {
            font-size: 30px; font-weight: 600; line-height: 1;
            text-align: center; width: 68px; white-space: nowrap;
        }
        .stub-table .name { font-size: 19px; font-weight: 600; line-height: 1.25; }
        .stub-table .name small { font-size: 11px; font-weight: 400; color: #333; display: block; }
        .stub-table .amount {
            text-align: right; font-size: 15px; font-weight: 500;
            white-space: nowrap; vertical-align: middle;
        }
        .stub-total {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 15px; font-weight: 600; margin-top: 8px;
        }
        .stub-total .qty-sum { font-size: 26px; }
        .sign { margin-top: 10px; font-size: 12px; }
        .sign-line { border-bottom: 1px dotted #000; height: 22px; margin-bottom: 2px; }
        /* ===== ใบที่ 2: หลักฐานเชียร์เบียร์รับของ (เฉพาะบิลเครดิต) =====
           พิมพ์เป็นแผ่นใหม่ ให้เครื่องตัดกระดาษเองระหว่างใบ */
        .tear-title {
            text-align: center; font-weight: 600; font-size: 14px;
            border: 2px solid #000; padding: 4px 0; margin-bottom: 8px;
        }
        .seller-name { font-size: 18px; font-weight: 600; line-height: 1.3; }
        .tear-note { font-size: 11px; color: #333; margin-top: 8px; line-height: 1.4; }
        /* บนจอ: คั่นให้เห็นว่าเป็นคนละแผ่น */
        .sheet-gap { height: 18px; }
        .actions { text-align: center; margin-top: 16px; }
        .btn { padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-family: 'Kanit'; margin: 0 4px; }
        .btn-print { background: #0d6efd; color: #fff; }
        .btn-close { background: #6c757d; color: #fff; }
        @media print {
            /* ตัดขอบกระดาษออก ไม่งั้นสลิปจะมีช่องว่างก่อนตัด */
            @page { margin: 0; }
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .receipt { width: 100%; }
            .sheet-gap { display: none; }
            /* สั่งขึ้นแผ่นใหม่ — เครื่องพิมพ์สลิปจะตัดกระดาษตรงนี้เอง */
            .page-break { break-before: page; page-break-before: always; }
            /* กันไม่ให้แต่ละใบถูกหั่นกลางใบ */
            .receipt { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="receipt" id="print-area">
        <div class="copy-label">ใบยืนยันการจ่ายสินค้า</div>

        <div class="center">
            <h3>{{ $currentEvent->name ?? 'POS คอนเสิร์ต' }}</h3>
            @if(!empty($currentEvent?->location))
                <div>{{ $currentEvent->location }}</div>
            @endif
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

        {{-- ชื่อสินค้าและจำนวนตัวใหญ่ ไว้เช็คตอนจ่ายของให้ลูกค้า --}}
        <table class="stub-table">
            @foreach($sale->items as $item)
                <tr>
                    <td class="qty">{{ number_format($item->quantity, 0) }}</td>
                    <td class="name">
                        {{ $item->product_name }}
                        <small>{{ number_format($item->price, 0) }} × {{ $item->quantity }}</small>
                    </td>
                    <td class="amount">{{ number_format($item->subtotal, 0) }}</td>
                </tr>
            @endforeach
        </table>
        <div class="stub-total">
            <span>รวมจำนวนที่ต้องจ่าย</span>
            <span class="qty-sum">{{ number_format($sale->items->sum('quantity'), 0) }}</span>
        </div>

        <div class="line"></div>
        <div class="row total"><span>รวมทั้งสิ้น</span><span>{{ number_format($sale->total, 0) }}</span></div>

        @if($sale->isCredit())
            <div class="center" style="margin-top:6px; color:#b8860b; font-weight:600;">** ขายแบบเครดิต **</div>
        @elseif($sale->isSplit())
            <div class="row"><span>ชำระโดย</span><span>เงินสด + เงินโอน</span></div>
            <div class="row"><span>เงินสด</span><span>{{ number_format($sale->cash_amount, 0) }}</span></div>
            <div class="row"><span>เงินโอน</span><span>{{ number_format($sale->transfer_amount, 0) }}</span></div>
        @elseif($sale->payment_type === 'transfer')
            <div class="row"><span>ชำระโดย</span><span>เงินโอน</span></div>
            <div class="row"><span>ยอดชำระ</span><span>{{ number_format($sale->paid, 0) }}</span></div>
        @else
            <div class="row"><span>รับเงินสด</span><span>{{ number_format($sale->paid, 0) }}</span></div>
            <div class="row"><span>เงินทอน</span><span>{{ number_format($sale->change, 0) }}</span></div>
        @endif

        <div class="line"></div>

        {{-- บาร์น้ำเก็บไว้ ใช้ยืนยันว่าลูกค้ารับของไปครบตามบิล --}}
        <div class="sign">
            <div class="sign-line"></div>
            <div class="center"><small>ลงชื่อผู้รับสินค้า</small></div>
        </div>
        <div class="sign">
            <div class="sign-line"></div>
            <div class="center"><small>ลงชื่อผู้จ่ายสินค้า (บาร์น้ำ)</small></div>
        </div>
        <div class="center" style="margin-top:6px;"><small>เก็บไว้เป็นหลักฐาน — ห้ามทิ้ง</small></div>
    </div>

    {{-- ===== ใบที่ 2 (เฉพาะบิลเครดิต) =====
         พิมพ์เป็นแผ่นใหม่ ให้เครื่องพิมพ์ตัดกระดาษระหว่างใบเอง
         เก็บไว้เป็นหลักฐานว่าเชียร์เบียร์รับของไปแล้ว ยังไม่ได้จ่ายเงิน --}}
    @if($sale->isCredit() && $sale->seller)
        @php($settled = $sale->isCreditSettled())
        <div class="sheet-gap"></div>
        <div class="receipt page-break">
            {{-- บิลที่ยกเลิกแล้วพิมพ์ไม่ได้อยู่แล้ว (PosController::receipt) --}}
            <div class="tear-title">
                เชียร์เบียร์รับของไปแล้ว ({{ $settled ? 'ชำระแล้ว' : 'ยังไม่ชำระ' }})
            </div>

            <div class="center">
                <h3>{{ $currentEvent->name ?? 'POS คอนเสิร์ต' }}</h3>
                <div>{{ $sale->station->name }}</div>
                <div class="line"></div>
            </div>

            <div class="row"><span>เลขที่บิล</span><span><b>{{ $sale->bill_no }}</b></span></div>
            <div class="row"><span>วันที่</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
            <div class="line"></div>

            <div class="center seller-name">
                {{ $sale->seller->code }} {{ $sale->seller->name }}
            </div>

            <div class="line"></div>
            <table class="stub-table">
                @foreach($sale->items as $item)
                    <tr>
                        <td class="qty">{{ number_format($item->quantity, 0) }}</td>
                        <td class="name">{{ $item->product_name }}</td>
                    </tr>
                @endforeach
            </table>
            <div class="stub-total">
                <span>รวมจำนวน</span>
                <span class="qty-sum">{{ number_format($sale->items->sum('quantity'), 0) }}</span>
            </div>

            <div class="line"></div>
            <div class="row total">
                <span>{{ $settled ? 'ยอดที่ชำระแล้ว' : 'ยอดค้างชำระ' }}</span>
                <span>{{ number_format($sale->total, 0) }}</span>
            </div>

            <div class="sign" style="margin-top:12px;">
                <div class="sign-line"></div>
                <div class="center"><small>ลงชื่อเชียร์เบียร์ผู้รับของ</small></div>
            </div>
            <div class="sign">
                <div class="sign-line"></div>
                <div class="center"><small>ลงชื่อผู้จ่ายของ ({{ $sale->user->name ?? 'บาร์น้ำ' }})</small></div>
            </div>

            <div class="tear-note center">
                @if($settled)
                    ชำระเรียบร้อยแล้ว — เก็บไว้เป็นหลักฐาน
                @else
                    นำใบนี้มาเคลียร์เงินที่ <b>{{ $sale->station->name }}</b> เท่านั้น<br>
                    เคลียร์ข้ามจุดไม่ได้
                @endif
            </div>
        </div>
    @endif

    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">🖨️ พิมพ์</button>
        <button class="btn btn-close" onclick="window.close()">ปิด</button>
        <div id="afterPrint" style="display:none; margin-top:10px; font-size:13px; color:#6c757d;">
            พิมพ์เรียบร้อยแล้ว — ปิดแท็บนี้ได้เลย
        </div>
    </div>

    <script>
        // เปิดขึ้นมาแล้วสั่งพิมพ์เลย (สำหรับเครื่องปรินท์สลิป)
        // ใส่ ?noprint=1 ท้าย URL ถ้าอยากดูเฉย ๆ ไม่ต้องสั่งพิมพ์
        (function () {
            if (new URLSearchParams(location.search).has('noprint')) return;

            var printed = false;
            function doPrint() {
                if (printed) return;
                printed = true;
                window.print();
            }

            // พิมพ์เสร็จแล้วปิดหน้าต่างเอง (ปิดได้เฉพาะหน้าที่ถูกเปิดด้วย window.open)
            var closed = false;
            function closeSelf() {
                if (closed) return;
                closed = true;
                window.close();
                // ถ้าเบราว์เซอร์ไม่ยอมปิด (เช่นเปิด URL ตรง ๆ) ให้โชว์ปุ่มไว้ใช้มือแทน
                setTimeout(function () {
                    document.getElementById('afterPrint').style.display = 'block';
                }, 300);
            }
            window.addEventListener('afterprint', function () { setTimeout(closeSelf, 200); });

            window.addEventListener('load', function () {
                // รอฟอนต์ Kanit โหลดเสร็จก่อน ไม่งั้นภาษาไทยจะพิมพ์ออกมาเป็นฟอนต์สำรอง
                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(doPrint);
                    setTimeout(doPrint, 2000); // กันเหนียวถ้าฟอนต์โหลดไม่ขึ้น
                } else {
                    doPrint();
                }
            });
        })();
    </script>
</body>
</html>
