<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot ต้นทุน/หน่วย ณ เวลาที่ขาย ลงในแต่ละรายการบิล
 *
 * เดิมรายงานกำไรคิดจาก products.cost "ปัจจุบัน" ทำให้บิลเก่าถูกคิดต้นทุนใหม่ย้อนหลัง
 * เมื่อมีการรับของล็อตใหม่ราคาต่างกัน (ต้นทุนเฉลี่ยขยับ) → กำไรเพี้ยน
 * เก็บต้นทุน ณ เวลาขายไว้ที่นี่ เพื่อให้กำไรของแต่ละบิลคงที่ ไม่โดนแก้ย้อนหลัง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('price'); // ต้นทุน/หน่วย ณ ตอนขาย (null = บิลเก่าก่อนมีฟีเจอร์นี้)
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
