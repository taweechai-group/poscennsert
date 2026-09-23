<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('cash_amount', 12, 2)->default(0)->after('payment_type');      // แยก: เงินสด
            $table->decimal('transfer_amount', 12, 2)->default(0)->after('cash_amount');   // แยก: เงินโอน
        });

        // เติมย้อนหลังให้บิลเดิม (เครดิตปล่อยเป็น 0 ทั้งคู่)
        DB::table('sales')->where('payment_type', 'cash')->update(['cash_amount' => DB::raw('total')]);
        DB::table('sales')->where('payment_type', 'transfer')->update(['transfer_amount' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['cash_amount', 'transfer_amount']);
        });
    }
};
