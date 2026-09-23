<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    /** ล็อกอินด้วย PIN — ค้นหา user ที่ PIN ตรง (active) */
    public function login(Request $request)
    {
        $request->validate(['pin' => 'required|string']);

        $pin = trim($request->pin);

        // ไม่เก็บ PIN แบบ plaintext จึงต้องวน hash check กับ user ที่ active
        $user = User::where('is_active', true)
            ->whereNotNull('pin')
            ->get()
            ->first(fn (User $u) => Hash::check($pin, $u->pin));

        if (! $user) {
            return back()->withErrors(['pin' => 'PIN ไม่ถูกต้อง'])->withInput();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
