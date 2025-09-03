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
    return view('admin.users.form', [
      'user' => new User(),
      'allClients' => Client::orderBy('client_id')->get(),
    ]);
  }

  public function store(Request $r)
  {
    $data = $r->validate([
      'name'        => ['required','string','max:120'],
      'email'       => ['required','email','max:190','unique:users,email'],
      'role'        => ['required', Rule::in(['superadmin','admin','user'])], // tambahkan superadmin kalau dipakai
      // === many-to-many ===
      'client_ids'  => ['sometimes','array'],
      'client_ids.*'=> ['string','max:12','distinct','exists:clients,client_id'],
      // === legacy (opsional) ===
      'client_id'   => ['nullable','string','max:12','exists:clients,client_id'],
      'password'    => ['required','string','min:6','max:190','confirmed'],
    ]);

    // Buat user (tanpa kolom legacy client_id)
    $user = User::create([
      'name'     => $data['name'],
      'email'    => $data['email'],
      'role'     => $data['role'],
      'password' => Hash::make($data['password']),
    ]);

    // Sinkron relasi pivot:
    // - jika client_ids[] ada → pakai itu
    // - jika hanya client_id legacy → konversi ke array
    $toSync = [];
    if (!empty($data['client_ids'])) {
      $toSync = array_values(array_unique(array_map('strval', $data['client_ids'])));
    } elseif (!empty($data['client_id'])) {
      $toSync = [(string) $data['client_id']];
    }
    $user->clients()->sync($toSync);

    return redirect()->route('admin.users.index')->with('ok','User dibuat.');
  }

  public function edit(User $user)
  {
    return view('admin.users.form', [
      'user' => $user,
      'allClients' => Client::orderBy('client_id')->get(),
    ]);
  }

  public function update(Request $r, User $user)
  {
    $data = $r->validate([
      'name'        => ['required','string','max:120'],
      'email'       => ['required','email','max:190', Rule::unique('users','email')->ignore($user->id)],
      'role'        => ['required', Rule::in(['superadmin','admin','user'])],
      // === many-to-many ===
      'client_ids'  => ['sometimes','array'],
      'client_ids.*'=> ['string','max:12','distinct','exists:clients,client_id'],
      // === legacy (opsional) ===
      'client_id'   => ['nullable','string','max:12','exists:clients,client_id'],
      'password'    => ['nullable','string','min:6','max:190','confirmed'],
    ]);

    // Proteksi: tidak boleh menurunkan role admin terakhir
    if ($this->isDemotingLastAdmin($user, $data['role'])) {
      return back()->withInput()->with('error','Tidak bisa menurunkan role admin terakhir.');
    }

    // Update field dasar
    $payload = [
      'name'  => $data['name'],
      'email' => $data['email'],
      'role'  => $data['role'],
    ];
    if (!empty($data['password'])) {
      $payload['password'] = Hash::make($data['password']);
    }
    $user->update($payload);

    // Sinkron relasi pivot (lihat prioritas seperti di store)
    $toSync = [];
    if (!empty($data['client_ids'])) {
      $toSync = array_values(array_unique(array_map('strval', $data['client_ids'])));
    } elseif (!empty($data['client_id'])) {
      $toSync = [(string) $data['client_id']];
    }
    $user->clients()->sync($toSync);

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

  /**
   * Hitung jumlah admin aktif.
   */
  private function adminCount(): int
  {
    return \App\Models\User::where('role', 'admin')->count();
  }

  /**
   * Cegah penurunan role admin terakhir menjadi non-admin.
   */
  private function isDemotingLastAdmin(\App\Models\User $user, string $newRole): bool
  {
    // gunakan method dari trait: $user->isAdmin()
    $isChangingRole = $newRole !== (string) $user->role;
    if (!$isChangingRole) {
      return false;
    }
    $demotingFromAdminToNon = $user->isAdmin() && $newRole !== 'admin';
    if (!$demotingFromAdminToNon) {
      return false;
    }
    return $this->adminCount() <= 1;
  }
}
