<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.form_login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
    
            $user = Auth::user();
    
            if ($user->role === 'super_admin') {
                flash()->addSuccess('تم تسجيل بنجاح.');
                return redirect()->route('superadmin.dashboard');
            }
    
            if ($user->role === 'admin') {
                flash()->addSuccess('تم تسجيل بنجاح.');
                return redirect()->route('admin.dashboard');
            }
    
            // fallback route
            return redirect('/');
        }
        flash()->addError('البيانات غير مطابقة في سجلاتنا.');
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.form');
    }
}
