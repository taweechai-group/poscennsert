<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * บันทึกต้นทุน/หน่วย ของแต่ละครั้งที่รับสินค้าเข้า (lot cost)
 * เก็บไว้ที่ movement เพื่อให้ย้อนดูต้นทุนรายล็อตได้ และใช้คำนวณต้นทุนเฉลี่ยถ่วงน้ำหนัก
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('quantity'); // ต้นทุน/หน่วย ณ ตอนรับเข้า
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
