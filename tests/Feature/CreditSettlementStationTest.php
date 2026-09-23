<?php

namespace Tests\Feature;

use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\Event;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * เชียร์เบียร์ไปเอาของได้หลายจุด แต่เคลียร์บิลข้ามจุดไม่ได้
 */
class CreditSettlementStationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Station $posA;
    private Station $posB;
    private Seller $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create(['name' => 'งานทดสอบ', 'status' => 'active']);
        $this->posA = Station::create(['event_id' => $this->event->id, 'name' => 'POS-01', 'type' => 'pos']);
        $this->posB = Station::create(['event_id' => $this->event->id, 'name' => 'POS-02', 'type' => 'pos']);
        $this->seller = Seller::create([
            'event_id' => $this->event->id, 'code' => 'C01', 'name' => 'เชียร์ A',
        ]);
    }

    private function cashier(Station $station): User
    {
        return User::create([
            'name' => 'แคชเชียร์ '.$station->name,
            'email' => 'cashier'.$station->id.'@test.local',
            'password' => 'secret',
            'role' => 'cashier',
            'station_id' => $station->id,
        ]);
    }

    /** สร้างบิลเครดิตหนึ่งใบที่จุดที่กำหนด */
    private function creditAt(Station $station, float $amount): Credit
    {
        $sale = Sale::create([
            'event_id' => $this->event->id,
            'station_id' => $station->id,
            'bill_no' => 'B'.$station->id.'-'.$amount,
            'payment_type' => 'credit',
            'seller_id' => $this->seller->id,
            'total' => $amount,
            'status' => 'completed',
        ]);

        return Credit::create([
            'event_id' => $this->event->id,
            'seller_id' => $this->seller->id,
            'sale_id' => $sale->id,
            'amount' => $amount,
        ]);
    }

    public function test_เชียร์เบียร์ไปเอาของได้หลายจุด(): void
    {
        $a = $this->creditAt($this->posA, 100);
        $b = $this->creditAt($this->posB, 200);

        // ทั้งสองบิลเป็นของคนเดียวกัน คนละจุด — ระบบยอมให้เกิดขึ้นได้
        $this->assertSame(300.0, (float) $this->seller->fresh()->totalCredit());
        $this->assertNotSame($a->sale->station_id, $b->sale->station_id);
    }

    public function test_เคลียร์บิลข้ามจุดไม่ได้(): void
    {
        $a = $this->creditAt($this->posA, 100);
        $b = $this->creditAt($this->posB, 200);

        // แคชเชียร์ POS-01 เลือกบิลของสองจุดพร้อมกัน — ต้องถูกปฏิเสธ
        $res = $this->actingAs($this->cashier($this->posA))
            ->post(route('sellers.pay', $this->seller), [
                'credit_ids' => [$a->id, $b->id],
                'cash_amount' => 300,
            ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('ข้ามจุด', session('error'));
        $this->assertNull($a->fresh()->settled_at);
        $this->assertNull($b->fresh()->settled_at);
        $this->assertSame(0, CreditPayment::count());
    }

    public function test_แอดมินรับชำระไม่ได้(): void
    {
        $a = $this->creditAt($this->posA, 100);

        $admin = User::create([
            'name' => 'แอดมิน', 'email' => 'admin@test.local',
            'password' => 'secret', 'role' => 'admin',
        ]);

        // รับชำระเป็นงานของ POS เท่านั้น — เงินต้องเข้าลิ้นชักที่จุดนั้นจริง
        $this->actingAs($admin)
            ->post(route('sellers.pay', $this->seller), [
                'credit_ids' => [$a->id],
                'cash_amount' => 100,
            ])
            ->assertForbidden();

        $this->assertNull($a->fresh()->settled_at);
        $this->assertSame(0, CreditPayment::count());
    }

    public function test_แอดมินไม่เห็นปุ่มรับชำระ_แต่แคชเชียร์เห็น(): void
    {
        $this->creditAt($this->posA, 100);

        $admin = User::create([
            'name' => 'แอดมิน', 'email' => 'admin5@test.local',
            'password' => 'secret', 'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('sellers.show', $this->seller))
            ->assertOk()
            ->assertDontSee('รับชำระที่เลือก')
            ->assertSee('ดูอย่างเดียว');

        $this->actingAs($this->cashier($this->posA))
            ->get(route('sellers.show', $this->seller))
            ->assertOk()
            ->assertSee('รับชำระที่เลือก');
    }

    public function test_แคชเชียร์เคลียร์บิลของจุดอื่นไม่ได้(): void
    {
        $b = $this->creditAt($this->posB, 200);

        $res = $this->actingAs($this->cashier($this->posA))
            ->post(route('sellers.pay', $this->seller), [
                'credit_ids' => [$b->id],
                'cash_amount' => 200,
            ]);

        $res->assertSessionHas('error');
        $this->assertNull($b->fresh()->settled_at);
        $this->assertSame(0, CreditPayment::count());
    }

    public function test_เคลียร์บิลจุดตัวเองได้_และเงินลงจุดนั้น(): void
    {
        $a1 = $this->creditAt($this->posA, 100);
        $a2 = $this->creditAt($this->posA, 50);
        $b = $this->creditAt($this->posB, 200);

        $res = $this->actingAs($this->cashier($this->posA))
            ->post(route('sellers.pay', $this->seller), [
                'credit_ids' => [$a1->id, $a2->id],
                'cash_amount' => 150,
            ]);

        $res->assertSessionHas('success');
        $this->assertNotNull($a1->fresh()->settled_at);
        $this->assertNotNull($a2->fresh()->settled_at);
        $this->assertNull($b->fresh()->settled_at, 'บิลจุดอื่นต้องยังค้างอยู่');

        $payment = CreditPayment::sole();
        $this->assertSame($this->posA->id, $payment->station_id);
        $this->assertSame('150.00', $payment->amount);
    }

    public function test_แคชเชียร์เพิ่มเชียร์เบียร์ไม่ได้(): void
    {
        $res = $this->actingAs($this->cashier($this->posA))
            ->post(route('sellers.store'), [
                'code' => 'C99', 'name' => 'ห้ามเพิ่ม',
            ]);

        $res->assertForbidden();
        $this->assertSame(1, Seller::count(), 'ต้องไม่มีเชียร์เบียร์ถูกเพิ่ม');
    }

    public function test_แคชเชียร์แก้ไขเชียร์เบียร์ไม่ได้(): void
    {
        $res = $this->actingAs($this->cashier($this->posA))
            ->put(route('sellers.update', $this->seller), [
                'code' => 'CX', 'name' => 'ห้ามแก้', 'credit_limit' => 99999,
            ]);

        $res->assertForbidden();
        $this->assertSame('C01', $this->seller->fresh()->code);
        $this->assertSame('เชียร์ A', $this->seller->fresh()->name);
    }

    public function test_แอดมินเพิ่มและแก้ไขเชียร์เบียร์ได้(): void
    {
        $admin = User::create([
            'name' => 'แอดมิน', 'email' => 'admin2@test.local',
            'password' => 'secret', 'role' => 'admin',
        ]);

        $this->actingAs($admin)->post(route('sellers.store'), [
            'code' => 'C02', 'name' => 'เชียร์ B', 'credit_limit' => 5000,
        ])->assertSessionHas('success');

        $this->assertSame(2, Seller::count());

        $this->actingAs($admin)->put(route('sellers.update', $this->seller), [
            'code' => 'C01', 'name' => 'เชียร์ A แก้แล้ว', 'credit_limit' => 8000,
        ])->assertSessionHas('success');

        $this->assertSame('เชียร์ A แก้แล้ว', $this->seller->fresh()->name);
    }

    public function test_แคชเชียร์ยังรับชำระได้ตามปกติ(): void
    {
        $a = $this->creditAt($this->posA, 120);

        $this->actingAs($this->cashier($this->posA))
            ->post(route('sellers.pay', $this->seller), [
                'credit_ids' => [$a->id],
                'cash_amount' => 120,
            ])->assertSessionHas('success');

        $this->assertNotNull($a->fresh()->settled_at);
    }

    public function test_หน้ารายชื่อ_แคชเชียร์ไม่เห็นปุ่มเพิ่มแก้ไข_แอดมินเห็น(): void
    {
        $this->actingAs($this->cashier($this->posA))
            ->get(route('sellers.index'))
            ->assertOk()
            ->assertDontSee('เพิ่มเชียร์เบียร์')
            ->assertDontSee('editSeller(', false)
            ->assertSee('แอดมินเท่านั้น');

        $admin = User::create([
            'name' => 'แอดมิน', 'email' => 'admin3@test.local',
            'password' => 'secret', 'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('sellers.index'))
            ->assertOk()
            ->assertSee('เพิ่มเชียร์เบียร์')
            ->assertSee('editSeller(', false);
    }

    public function test_แอดมินเห็นเมนูจัดการเชียร์เบียร์(): void
    {
        $admin = User::create([
            'name' => 'แอดมิน', 'email' => 'admin4@test.local',
            'password' => 'secret', 'role' => 'admin',
        ]);

        // เมนูต้องมีลิงก์ไปหน้าเชียร์เบียร์
        $this->actingAs($admin)
            ->get(route('sellers.index'))
            ->assertOk()
            ->assertSee('จัดการเชียร์เบียร์')
            ->assertSee(route('sellers.index'), false);

        // แคชเชียร์เห็นเมนูเดิม (ไม่ใช่เมนูจัดการ)
        $this->actingAs($this->cashier($this->posA))
            ->get(route('sellers.index'))
            ->assertOk()
            ->assertDontSee('จัดการเชียร์เบียร์');
    }

    public function test_แคชเชียร์เห็นเฉพาะบิลค้างของจุดตัวเอง(): void
    {
        $this->creditAt($this->posA, 100);
        $this->creditAt($this->posB, 200);

        $res = $this->actingAs($this->cashier($this->posA))
            ->get(route('sellers.show', $this->seller));

        $res->assertOk();
        $open = $res->viewData('openCredits');
        $this->assertCount(1, $open);
        $this->assertSame($this->posA->id, $open->first()->sale->station_id);
    }
}
