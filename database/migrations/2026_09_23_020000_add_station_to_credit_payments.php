<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เครดิตหนึ่งใบชำระ = จุดขายเดียวเสมอ (ห้ามเคลียร์บิลข้ามจุด)
     * เก็บ station_id ไว้ตรงๆ เพื่อให้รายงานรู้ว่าเงินเข้าจุดไหน โดยไม่ต้อง join ย้อน
     */
    public function up(): void
    {
        Schema::table('credit_payments', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('seller_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('credit_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
