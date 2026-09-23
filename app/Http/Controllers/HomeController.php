<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /** ส่งผู้ใช้ไปหน้าเริ่มต้นตาม role */
    public function index()
    {
        $user = Auth::user();

        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'warehouse' => redirect()->route('warehouse.index'),
            default => redirect()->route('pos.index'),
        };
    }
}
