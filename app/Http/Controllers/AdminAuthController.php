<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
  public function showLogin()
  {
    if (Auth::check() && Auth::user()->is_admin) {
      return redirect()->route('admin.dashboard');
    }
    return view('admin.login');
  }

  public function login(Request $r)
  {
    $data = $r->validate([
      'email' => 'required|email',
      'password' => 'required|string',
      'remember' => 'nullable|boolean',
    ]);

    if (Auth::attempt(['email'=>$data['email'], 'password'=>$data['password']], (bool)($data['remember'] ?? false))) {
      if (Auth::user()->is_admin) {
        $r->session()->regenerate();
        return redirect()->route('admin.dashboard');
      }
      Auth::logout();
    }

    return back()->withInput()->with('error','Email atau password salah / bukan admin.');
  }

  public function logout(Request $r)
  {
    Auth::logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();
    return redirect()->route('admin.login')->with('ok','Anda telah logout.');
  }
}
