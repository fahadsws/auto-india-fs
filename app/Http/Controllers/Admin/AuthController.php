<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin() { return view('admin.auth.login'); }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($cred + ['is_active' => true], $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records, or the account is disabled.'])->onlyInput('email');
        }
        $r->session()->regenerate();

        if (! Auth::user()->can('admin.access')) {
            Auth::logout();
            return back()->withErrors(['email' => 'You do not have access to the admin panel.']);
        }
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
