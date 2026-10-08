<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * บัตร VIP แลกเบียร์ฟรี — ติ๊กว่าสินค้าตัวไหนใช้แลก VIP ได้ (ปกติคือเบียร์ตัวเดียวในงาน)
 * กด VIP 4/8 ที่ POS → ใส่สินค้าตัวนี้ลงตะกร้า 4/8 หน่วย ราคา 0 แล้วตัดสต๊อก/คิดต้นทุนปกติ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_vip_eligible')->default(false)->after('is_returnable');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_vip_eligible');
        });
    }
};
