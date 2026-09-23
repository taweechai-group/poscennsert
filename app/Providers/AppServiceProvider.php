<?php

namespace App\Providers;

use App\Models\Event;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // ชื่องานคอนเสิร์ตดึงจากตาราง events ให้ทุกหน้าใช้ร่วมกัน
        View::composer('*', function ($view) {
            $view->with('currentEvent', $this->currentEvent());
        });
    }

    /**
     * งานที่กำลังใช้งานอยู่ (แคชไว้ในหน่วยความจำต่อ 1 request)
     * คืนค่า null ได้ถ้ายังไม่ได้ migrate หรือยังไม่มีข้อมูล
     */
    protected function currentEvent(): ?Event
    {
        static $event;
        static $resolved = false;

        if (! $resolved) {
            $resolved = true;

            try {
                $event = Event::where('status', 'active')->latest('id')->first()
                    ?? Event::latest('id')->first();
            } catch (Throwable) {
                $event = null;
            }
        }

        return $event;
    }
}
