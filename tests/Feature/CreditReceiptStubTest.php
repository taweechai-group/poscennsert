<?php

namespace Tests\Feature;

use App\Models\Credit;
use App\Models\Event;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ใบที่ 2 — หลักฐานว่าเชียร์เบียร์รับของไปแล้ว (เฉพาะบิลเครดิต)
 * พิมพ์แยกแผ่น ให้เครื่องพิมพ์ตัดกระดาษเอง (page-break)
 */
class CreditReceiptStubTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Station $pos;
    private User $cashier;
    private Seller $seller;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create(['name' => 'งานทดสอบ', 'status' => 'active']);
        $this->pos = Station::create(['event_id' => $this->event->id, 'name' => 'POS-01', 'type' => 'pos']);
        $this->cashier = User::create([
            'name' => 'แคชเชียร์', 'email' => 'c@test.local', 'password' => 'secret',
            'role' => 'cashier', 'station_id' => $this->pos->id,
        ]);
        $this->seller = Seller::create([
            'event_id' => $this->event->id, 'code' => 'C01', 'name' => 'สมหญิง',
        ]);
        $this->product = Product::create([
            'event_id' => $this->event->id, 'name' => 'เบียร์ช้าง', 'price' => 100,
        ]);
    }

    private function makeSale(string $paymentType): Sale
    {
        $sale = Sale::create([
            'event_id' => $this->event->id,
            'station_id' => $this->pos->id,
            'bill_no' => 'B0001',
            'user_id' => $this->cashier->id,
            'seller_id' => $paymentType === 'credit' ? $this->seller->id : null,
            'payment_type' => $paymentType,
            'total' => 300,
            'paid' => $paymentType === 'credit' ? 0 : 300,
            'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => 'เบียร์ช้าง',
            'price' => 100,
            'quantity' => 3,
            'subtotal' => 300,
        ]);

        return $sale;
    }

    public function test_บิลเครดิตมีใบที่สองแยกแผ่น(): void
    {
        $sale = $this->makeSale('credit');
        Credit::create([
            'event_id' => $this->event->id,
            'seller_id' => $this->seller->id,
            'sale_id' => $sale->id,
            'amount' => 300,
        ]);

        $res = $this->actingAs($this->cashier)->get(route('pos.receipt', $sale));

        $res->assertOk()
            ->assertSee('page-break', false)
            ->assertSee('เชียร์เบียร์รับของไปแล้ว (ยังไม่ชำระ)')
            ->assertSee('ยอดค้างชำระ')
            ->assertSee('C01')
            ->assertSee('สมหญิง')
            ->assertSee('ลงชื่อเชียร์เบียร์ผู้รับของ')
            ->assertSee('เคลียร์ข้ามจุดไม่ได้');
    }

    public function test_บิลเงินสดมีแผ่นเดียว(): void
    {
        $sale = $this->makeSale('cash');

        $html = $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $sale))->assertOk()->getContent();

        $this->assertStringNotContainsString('เชียร์เบียร์รับของไปแล้ว', $html);
        // ไม่มี page-break = เครื่องพิมพ์ตัดครั้งเดียวจบ
        $this->assertSame(1, substr_count($html, 'class="receipt'), 'บิลเงินสดต้องมีแผ่นเดียว');
        $this->assertStringNotContainsString('page-break"', $html);
    }

    public function test_บิลเครดิตพิมพ์สองแผ่น_มีคำสั่งตัดกระดาษ(): void
    {
        $sale = $this->makeSale('credit');
        Credit::create([
            'event_id' => $this->event->id, 'seller_id' => $this->seller->id,
            'sale_id' => $sale->id, 'amount' => 300,
        ]);

        $html = $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $sale))->assertOk()->getContent();

        // สองแผ่น: ใบเสร็จ + ใบหลักฐานเชียร์เบียร์
        $this->assertSame(2, substr_count($html, 'class="receipt'), 'บิลเครดิตต้องมีสองแผ่น');
        // แผ่นที่สองต้องสั่งขึ้นหน้าใหม่ เครื่องพิมพ์จะได้ตัดกระดาษ
        $this->assertStringContainsString('class="receipt page-break"', $html);
        $this->assertStringContainsString('page-break-before: always', $html);
    }

    public function test_บิลเครดิตที่ชำระแล้ว_ใบที่สองบอกว่าชำระแล้ว(): void
    {
        $sale = $this->makeSale('credit');
        Credit::create([
            'event_id' => $this->event->id,
            'seller_id' => $this->seller->id,
            'sale_id' => $sale->id,
            'amount' => 300,
            'settled_at' => now(),
        ]);

        $this->actingAs($this->cashier)->get(route('pos.receipt', $sale))
            ->assertOk()
            ->assertSee('เชียร์เบียร์รับของไปแล้ว (ชำระแล้ว)')
            ->assertSee('ยอดที่ชำระแล้ว')
            ->assertDontSee('ยอดค้างชำระ')
            ->assertDontSee('เคลียร์ข้ามจุดไม่ได้');
    }

    public function test_ใบที่สองแสดงรายการและจุดขาย(): void
    {
        $sale = $this->makeSale('credit');
        Credit::create([
            'event_id' => $this->event->id, 'seller_id' => $this->seller->id,
            'sale_id' => $sale->id, 'amount' => 300,
        ]);

        $html = $this->actingAs($this->cashier)
            ->get(route('pos.receipt', $sale))->getContent();

        $stub = substr($html, strpos($html, 'page-break"'));

        // ใบที่สองต้องมีข้อมูลครบพอให้ยันยอดตอนมาเคลียร์เงิน
        $this->assertStringContainsString('B0001', $stub, 'ต้องมีเลขที่บิล');
        $this->assertStringContainsString('POS-01', $stub, 'ต้องมีจุดขาย');
        $this->assertStringContainsString('เบียร์ช้าง', $stub, 'ต้องมีรายการสินค้า');
        $this->assertStringContainsString('300', $stub, 'ต้องมียอด');
    }
}
