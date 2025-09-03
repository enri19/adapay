<?php

namespace App\Http\Controllers\Concerns;

trait ResolvesRoleAndClient
{
  /** ── Role helpers ─────────────────────────────────────────────── */
  private function userIsAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isAdmin')) return (bool) $user->isAdmin();
    return (string)($user->role ?? 'user') === 'admin';
  }

  private function userIsSuperAdmin($user): bool
  {
    if (!$user) return false;
    if (method_exists($user, 'isSuperAdmin')) return (bool) $user->isSuperAdmin();
    return (string)($user->role ?? 'user') === 'superadmin';
  }

  /** ── Normalizer ───────────────────────────────────────────────── */
  private function normalizeClientId($v): string
  {
    // Hapus strtoupper() bila DB kamu simpan lowercase/mixed case.
    return strtoupper(trim((string) $v));
  }

  /** ── Ambil daftar client yang diizinkan untuk user (M2M) ──────── */
  private function getUserAllowedClientIds($user): array
  {
    if (!$user) return [];

    // Jika model User sudah punya accessor allowed_client_ids (dari relasi M2M)
    if (isset($user->allowed_client_ids)) {
      return array_values(array_unique(array_map([$this, 'normalizeClientId'], (array) $user->allowed_client_ids)));
    }

    // Fallback: coba tarik langsung dari relasi M2M
    if (method_exists($user, 'clients')) {
      $ids = $user->clients()
        ->pluck('clients.client_id')
        ->map(function ($v) { return $this->normalizeClientId($v); })
        ->unique()
        ->values()
        ->all();
      return $ids;
    }

    // BENAR-BENAR fallback (legacy single client col) → kalau ada, kembalikan tunggal
    if (!empty($user->client_id)) {
      return [$this->normalizeClientId($user->client_id)];
    }

    return [];
  }

  /**
   * Resolusi filter client versi BARU (recommended):
   * - Admin/Superadmin:
   *     - jika ?client_id=... ada → validasi (exist) lalu kembalikan [client_id]
   *     - kalau tidak ada → kembalikan [] (artinya "semua")
   * - User biasa:
   *     - jika ?client_id=... ada → hanya boleh jika termasuk allowed; kalau tidak, abort(403)
   *     - kalau tidak ada → kembalikan seluruh allowed; jika kosong, abort(403)
   */
  private function resolveClientIds($user, $queryClientId = '')
  {
    $q = $this->normalizeClientId($queryClientId ?? '');

    if ($this->userIsAdmin($user) || $this->userIsSuperAdmin($user)) {
      // Admin bebas memilih; kosong = semua
      if ($q === '') return [];
      return [$q];
    }

    // User biasa: harus terikat ke minimal satu client
    $allowed = $this->getUserAllowedClientIds($user);
    if (empty($allowed)) {
      abort(403, 'User belum terikat ke client mana pun.');
    }

    if ($q !== '') {
      if (!in_array($q, $allowed, true)) {
        abort(403, 'Client tidak diizinkan untuk user ini.');
      }
      return [$q];
    }

    // Tanpa query → seluruh client yang diizinkan
    return $allowed;
  }

  /**
   * Versi LEGACY (kompat lama) yang mengembalikan SATU client_id (string).
   * - Admin: ?client_id=... → kembalikan nilainya; kosong → '' (artinya semua)
   * - User:  ?client_id=... → kalau valid kembalikan itu; kosong → ambil allowed[0]
   *
   * NOTE:
   *   Untuk query Eloquent yang lama: ->when($cid !== '', fn($q)=>$q->where('client_id',$cid))
   *   Disarankan migrasi ke resolveClientIds() dan whereIn().
   */
  private function resolveClientId($user, $queryClientId = '')
  {
    $list = $this->resolveClientIds($user, $queryClientId);

    // Admin & tidak ada filter → semua
    if (($this->userIsAdmin($user) || $this->userIsSuperAdmin($user)) && empty($list)) {
      return '';
    }

    // User / admin dengan filter → ambil pertama
    return $list[0] ?? '';
  }

  /** Kompat untuk kode lama */
  private function requireUserClientId($user)
  {
    // Dulu mengembalikan satu id & abort kalau kosong.
    $cid = $this->resolveClientId($user, null);
    if ($cid === '') {
      abort(403, 'User belum terikat ke client.');
    }
    return $cid;
  }
}
