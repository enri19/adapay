<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Client;

class AdminPaymentController extends Controller
{
  public function index(Request $r)
  {
    $user = $r->user();
    $isAdmin = $this->userIsAdmin($user);

    // Admin boleh pilih via query; user dipaksa ke client miliknya
    $clientParam = strtoupper((string) $r->query('client_id', ''));
    $client = $isAdmin ? $clientParam : $this->requireUserClientId($user);

    $status = strtoupper((string) $r->query('status', ''));
    $from   = $r->query('from');
    $to     = $r->query('to');
    $q      = trim((string) $r->query('q', ''));

    $rows = Payment::query()
      // Non-admin: kunci ke client miliknya
      ->when(!$isAdmin, function ($qq) use ($client) {
        $qq->where('client_id', $client);
      })
      // Filter client (admin bebas, user sudah dipaksa di atas)
      ->when($client !== '', function ($qq) use ($client) {
        $qq->where('client_id', $client);
      })
      // Filter status
      ->when($status !== '', function ($qq) use ($status) {
        $qq->where('status', $status);
      })
      // Tanggal
      ->when($from, function ($qq) use ($from) {
        $qq->whereDate('created_at', '>=', $from);
      })
      ->when($to, function ($qq) use ($to) {
        $qq->whereDate('created_at', '<=', $to);
      })
      // Pencarian bebas
      ->when($q !== '', function ($qq) use ($q) {
        $qq->where(function ($w) use ($q) {
          $w->where('order_id', 'like', "%{$q}%")
            ->orWhere('provider_ref', 'like', "%{$q}%");
        });
      })
      ->orderByDesc('created_at')
      ->paginate(20)
      ->appends($r->only('client_id','status','from','to','q'));

    // Dropdown clients:
    // - Admin: semua
    // - User: hanya client miliknya sendiri
    $clientsQuery = Client::query()->orderBy('client_id');
    if (!$isAdmin) {
      $clientsQuery->where('client_id', $client);
    }
    $clients = $clientsQuery->get();

    return view('admin.payments.index', compact('rows','clients','client','status','from','to','q'));
  }

  private function userIsAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    if (isset($user->role)) return (string) $user->role === 'admin';
    return false;
  }

  private function requireUserClientId($user): string
  {
    // ADMIN: tidak perlu client filter → jangan abort, kembalikan '' agar query tidak di-filter client
    if ($this->userIsAdmin($user)) {
      return '';
    }

    // USER: wajib terikat client
    $clientId = strtoupper((string) ($user->client_id ?? ''));
    if ($clientId === '') {
      abort(403, 'User belum terikat ke client.');
    }
    return $clientId;
  }
}
