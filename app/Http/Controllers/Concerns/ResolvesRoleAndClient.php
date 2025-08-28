<?php

namespace App\Http\Controllers\Concerns;

trait ResolvesRoleAndClient
{
  private function userIsAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    return (string)($user->role ?? 'user') === 'admin';
  }

  /**
   * Admin: boleh pakai query ?client_id=... (boleh kosong = semua)
   * User : wajib terikat ke client; abort 403 jika kosong.
   * NOTE: hapus strtoupper() kalau DB kamu simpan client_id bukan uppercase.
   */
  private function resolveClientId($user, $queryClientId = '')
  {
    if ($this->userIsAdmin($user)) {
      return strtoupper((string)($queryClientId ?? ''));
    }
    $cid = strtoupper((string)($user->client_id ?? ''));
    if ($cid === '') {
      abort(403, 'User belum terikat ke client.');
    }
    return $cid;
  }

  // Kompat untuk kode lama
  private function requireUserClientId($user)
  {
    return $this->resolveClientId($user, null);
  }
}
