<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Seller;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Event ตัวอย่าง ----------
        $event = Event::create([
            'name' => 'คอนเสิร์ต แอน อรดี & ปู พงษ์สิทธิ์',
            'location' => 'ลานบิ๊กซี ศรีสะเกษ',
            'event_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        // ---------- จุดขาย POS 1-4 + คลังกลาง ----------
        $warehouse = Station::create([
            'event_id' => $event->id,
            'name' => 'คลังกลาง',
            'type' => 'warehouse',
            'code' => 'WH',
        ]);

        $posStations = [];
        for ($i = 1; $i <= 4; $i++) {
            $posStations[$i] = Station::create([
                'event_id' => $event->id,
                'name' => 'POS-0'.$i,
                'type' => 'pos',
                'code' => 'P0'.$i,
            ]);
        }

        // ---------- ผู้ใช้ + PIN ----------
        // Admin
        User::create([
            'name' => 'ผู้ดูแลระบบ',
            'email' => 'admin@pos.test',
            'password' => Hash::make('password'),
            'pin' => '9999',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // คลังกลาง (มือถือ)
        User::create([
            'name' => 'เจ้าหน้าที่คลัง',
            'email' => 'warehouse@pos.test',
            'password' => Hash::make('password'),
            'pin' => '8888',
            'role' => 'warehouse',
            'station_id' => $warehouse->id,
            'is_active' => true,
        ]);

        // แคชเชียร์ประจำจุด POS 1-4 (PIN 1001-1004)
        foreach ($posStations as $i => $st) {
            User::create([
                'name' => 'แคชเชียร์ จุด '.$i,
                'email' => "cashier{$i}@pos.test",
                'password' => Hash::make('password'),
                'pin' => (string) (1000 + $i),
                'role' => 'cashier',
                'station_id' => $st->id,
                'is_active' => true,
            ]);
        }

        // ---------- เชียร์เบียร์ (เดินขาย มีรหัส) ----------
        $sellers = [
            ['code' => 'C01', 'name' => 'สมชาย', 'commission_rate' => 10, 'credit_limit' => 5000],
            ['code' => 'C02', 'name' => 'สมหญิง', 'commission_rate' => 10, 'credit_limit' => 3000],
            ['code' => 'C03', 'name' => 'อนุชา', 'commission_rate' => 12, 'credit_limit' => 0], // ไม่จำกัด
        ];
        foreach ($sellers as $s) {
            Seller::create(array_merge($s, ['event_id' => $event->id]));
        }
    }
}
