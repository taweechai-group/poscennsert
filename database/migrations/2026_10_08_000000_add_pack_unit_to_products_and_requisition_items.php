<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หน่วยเบิก (แพ็ก) — สินค้าบางตัวเบิกเป็นแพ็ก เช่น เบียร์ 12 ขวด/แพ็ก
 * เบิก 2 แพ็ก => 24 ขวด (เก็บยอดจริงเป็นขวด แต่จำว่าเบิกกี่แพ็กไว้แสดงหมายเหตุ)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // จำนวนหน่วยย่อยต่อ 1 แพ็ก (0 = ไม่ใช้ระบบแพ็ก เบิกตามหน่วยปกติ)
            $table->unsignedInteger('pack_size')->default(0)->after('unit');
            // ชื่อหน่วยแพ็ก เช่น แพ็ก, ลัง, กล่อง
            $table->string('pack_unit', 20)->nullable()->after('pack_size');
        });

        Schema::table('requisition_items', function (Blueprint $table) {
            // จำนวนแพ็กที่เบิก (null = เบิกเป็นหน่วยย่อยตรงๆ ไม่ผ่านแพ็ก) — ไว้แสดงหมายเหตุให้คลัง
            $table->unsignedInteger('pack_quantity')->nullable()->after('quantity_requested');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn('pack_quantity');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pack_size', 'pack_unit']);
        });
    }
};
