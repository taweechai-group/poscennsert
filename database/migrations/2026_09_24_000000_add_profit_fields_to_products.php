<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มฟิลด์สำหรับคิดกำไรแยกตามประเภทสินค้า
 *
 * - is_returnable = true  : สินค้าคืนของได้ (น้ำ/เบียร์) → คิดต้นทุนเฉพาะที่ขายจริง (cost/หน่วย × จำนวนขาย)
 * - is_returnable = false : สินค้าคืนไม่ได้ (น้ำแข็ง) → คิดต้นทุนทั้งก้อนจาก total_cost (ที่เหลือละลายทิ้ง = ต้นทุนจม)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // คืนของได้ไหม (ค่าเริ่มต้น = คืนได้ เหมือนน้ำ/เบียร์ทั่วไป)
            $table->boolean('is_returnable')->default(true)->after('cost');
            // ทุนรวมที่ลงไปทั้งหมด — ใช้เฉพาะสินค้าคืนไม่ได้ (แอดมินกรอกเอง)
            $table->decimal('total_cost', 12, 2)->default(0)->after('is_returnable');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_returnable', 'total_cost']);
        });
    }
};
