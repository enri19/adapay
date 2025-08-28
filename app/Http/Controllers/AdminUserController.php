<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Client;

class AdminUserController extends Controller
{
  public function index(Request $r)
  {
    $role   = (string) $r->query('role', '');
    $client = (string) $r->query('client_id', '');
    $q      = trim((string) $r->query('q', ''));

    $rows = User::query()
      ->when($role !== '', fn($qq) => $qq->where('role', $role))
      ->when($client !== '', fn($qq) => $qq->where('client_id', $client))
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($w) use ($q) {
          $w->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%");
        });
      })
      ->orderBy('role')->orderBy('name')
      ->paginate(20)
      ->appends($r->only('role','client_id','q'));

    $clients = Client::orderBy('client_id')->get(['client_id','name']);

    return view('admin.users.index', compact('rows','clients','role','client','q'));
  }

  public function create()
  {
    $user = new User;
    $clients = Client::orderBy('client_id')->get(['client_id','name']);
    return view('admin.users.form', compact('user','clients'));
  }

  public function store(Request $r)
  {
    $data = $r->validate([
      'name'      => ['required','string','max:120'],
      'email'     => ['required','email','max:190','unique:users,email'],
      'role'      => ['required', Rule::in(['admin','user'])],
      'client_id' => ['nullable','string','max:64','exists:clients,client_id'],
      'password'  => ['required','string','min:6','max:190','confirmed'],
    ]);

    // Non-admin boleh punya client_id; admin biasanya null
    $payload = [
      'name'      => $data['name'],
      'email'     => $data['email'],
      'role'      => $data['role'],
      'client_id' => $data['client_id'] !== null ? (string) $data['client_id'] : null,
      'password'  => Hash::make($data['password']),
    ];

    User::create($payload);
    return redirect()->route('admin.users.index')->with('ok','User dibuat.');
  }

  public function edit(User $user)
  {
    $clients = Client::orderBy('client_id')->get(['client_id','name']);
    return view('admin.users.form', compact('user','clients'));
  }

  public function update(Request $r, User $user)
  {
    $data = $r->validate([
      'name'      => ['required','string','max:120'],
      'email'     => ['required','email','max:190', Rule::unique('users','email')->ignore($user->id)],
      'role'      => ['required', Rule::in(['admin','user'])],
      'client_id' => ['nullable','string','max:64','exists:clients,client_id'],
      'password'  => ['nullable','string','min:6','max:190','confirmed'],
    ]);

    // Proteksi: tidak boleh menurunkan role admin terakhir
    $isDemotingLastAdmin = $this->isAdmin($user) && $data['role'] !== 'admin' && $this->adminCount() <= 1;
    if ($isDemotingLastAdmin) {
      return back()->withInput()->with('error','Tidak bisa menurunkan role admin terakhir.');
    }

    // Update
    $user->name = $data['name'];
    $user->email = $data['email'];
    $user->role = $data['role'];
    $user->client_id = $data['client_id'] !== null ? (string) $data['client_id'] : null;

    if (!empty($data['password'])) {
      $user->password = Hash::make($data['password']);
    }

    $user->save();

    return redirect()->route('admin.users.index')->with('ok','User diupdate.');
  }

  public function destroy(Request $r, User $user)
  {
    // Proteksi: tidak bisa hapus diri sendiri
    if ($r->user()->id === $user->id) {
      return back()->with('error','Tidak bisa menghapus akun sendiri.');
    }

    // Proteksi: tidak boleh menghapus admin terakhir
    if ($this->isAdmin($user) && $this->adminCount() <= 1) {
      return back()->with('error','Tidak bisa menghapus admin terakhir.');
    }

    $user->delete();
    return back()->with('ok','User dihapus.');
  }

  private function isAdmin(User $user): bool
  {
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    if (isset($user->role)) return (string) $user->role === 'admin';
    return false;
  }

  private function adminCount(): int
  {
    return (int) User::where('role','admin')->count();
  }
}
