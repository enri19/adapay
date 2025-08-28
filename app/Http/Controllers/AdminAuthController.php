<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
  public function showLogin(Request $r)
  {
    if (Auth::check()) {
      return redirect()->route('admin.dashboard');
    }
    return view('admin.login');
  }

  public function login(Request $r)
  {
    $data = $r->validate([
      'email' => 'required|email',
      'password' => 'required|string',
      'remember' => 'nullable', // jangan pakai boolean, checkbox sering kirim "on"
    ]);

    $remember = $r->boolean('remember'); // handle "on"/"1"/true
    if (Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $remember)) {
      $r->session()->regenerate();
      return redirect()->route('admin.dashboard');
    }

    return back()->withInput()->with('error', 'Email atau password salah.');
  }

  public function logout(Request $r)
  {
    Auth::logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();
    return redirect()->route('admin.login')->with('ok','Anda telah logout.');
  }

  /**
   * Kompatibel: isAdmin() method, atau role === 'admin'
   */
  private function userIsAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    if (isset($user->role)) return (string)$user->role === 'admin';
    return false;
  }
}
