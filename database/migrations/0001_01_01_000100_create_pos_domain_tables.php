<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * โครงสร้างฐานข้อมูลหลักของระบบ POS งานคอนเสิร์ต
 * ทุกตารางผูกกับ event_id เพื่อแยกข้อมูลตามงาน
 */
return new class extends Migration
{
    public function up(): void
    {
        // งาน/อีเวนต์ — ข้อมูลทั้งหมดแยกตาม event
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // เช่น "คอนเสิร์ต แอน อรดี & ปู พงษ์สิทธิ์"
            $table->string('location')->nullable();
            $table->date('event_date')->nullable();
            $table->string('status')->default('active');  // active | closed
            $table->timestamps();
        });

        // จุดขาย + คลังกลาง (คลังกลางคือ station ชนิด warehouse)
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // POS-01, POS-02, คลังกลาง
            $table->string('type')->default('pos');       // pos | warehouse
            $table->string('code')->nullable();           // รหัสสั้นๆ
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // สินค้า (แยกตาม event)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // เบียร์, น้ำเปล่า, น้ำแข็ง
            $table->string('sku')->nullable();
            $table->string('unit')->default('หน่วย');     // กระป๋อง, ขวด, ถุง
            $table->decimal('price', 10, 2)->default(0);  // ราคาขาย/หน่วย
            $table->decimal('cost', 10, 2)->default(0);   // ต้นทุน/หน่วย
            $table->string('image')->nullable();          // path รูปสินค้า (storage)
            $table->string('icon')->default('bi-box');    // bootstrap icon (fallback ถ้าไม่มีรูป)
            $table->string('color')->default('#0d6efd');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // สต๊อกคงเหลือ แยกตาม station + product (แหล่งความจริงของยอดคงเหลือ)
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['station_id', 'product_id']);
        });

        // Audit log ทุกการเคลื่อนไหวของสต๊อก
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // in | out | sale | transfer_in | transfer_out | adjust
            $table->integer('quantity');                  // +เข้า / -ออก
            $table->integer('balance_after')->nullable(); // ยอดคงเหลือหลังทำรายการ
            $table->string('ref_type')->nullable();       // sale | requisition | manual
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // ใบเบิก/โอนสินค้า (จุดขายขอเบิกจากคลังกลาง)
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('code');                       // REQ-0001
            $table->foreignId('from_station_id')->constrained('stations'); // คลังกลาง
            $table->foreignId('to_station_id')->constrained('stations');   // จุดที่ขอเบิก
            $table->string('status')->default('pending'); // pending | approved | rejected | delivered
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity_requested');
            $table->integer('quantity_delivered')->nullable();
            $table->timestamps();
        });

        // พนักงานเชียร์เบียร์ (เดินขาย มีรหัส/QR — ขายก่อน เคลียร์เงินทีหลัง)
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('code');                       // C01
            $table->string('name');
            $table->string('phone')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);  // % คอมมิชชั่น
            $table->decimal('credit_limit', 12, 2)->default(0);    // วงเงินเครดิต (0 = ไม่จำกัด)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // การขาย (บิล)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained();
            $table->string('bill_no');                    // เลขที่บิล
            $table->foreignId('user_id')->nullable()->constrained();      // แคชเชียร์
            $table->foreignId('seller_id')->nullable()->constrained();    // เชียร์เบียร์ (ถ้ามี)
            $table->string('payment_type')->default('cash'); // cash | credit (เครดิตเชียร์เบียร์)
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('change', 12, 2)->default(0);
            $table->string('status')->default('completed'); // completed | void
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');               // snapshot
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        // เครดิตของเชียร์เบียร์ (ยอดค้างที่ต้องมาเคลียร์)
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained();
            $table->decimal('amount', 12, 2);             // ยอดเครดิตที่เกิด
            $table->timestamp('settled_at')->nullable();  // เคลียร์เมื่อไหร่ (null = ยังค้าง)
            $table->foreignId('credit_payment_id')->nullable(); // อ้างอิงการชำระที่เคลียร์บิลนี้
            $table->timestamps();
        });

        // การชำระเครดิตคืน
        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained();
            $table->decimal('amount', 12, 2);            // ยอดรวมที่ชำระ
            $table->decimal('cash_amount', 12, 2)->default(0);      // แยก: เงินสด
            $table->decimal('transfer_amount', 12, 2)->default(0);  // แยก: เงินโอน
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stations');
        Schema::dropIfExists('events');
    }
};
