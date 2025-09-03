<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasRoles;

class User extends Authenticatable
{
  use Notifiable, HasRoles;

  protected $fillable = [
    'name', 'email', 'password',
    'role', // hapus 'client_id' legacy biar bersih
  ];

  protected $hidden = ['password', 'remember_token'];

  protected $casts = [
    'email_verified_at' => 'datetime',
  ];

  public function clients()
  {
    return $this->belongsToMany(
      Client::class,
      'user_client',
      'user_id',    // pivot -> users.id
      'client_id',  // pivot -> clients.client_id (varchar)
      'id',         // local key users
      'client_id'   // related key clients
    );
  }

  // === Util ===
  public function getAllowedClientIdsAttribute(): array
  {
    return $this->clients()
      ->pluck('clients.client_id')
      ->map(static function ($v) { return strtoupper((string) $v); })
      ->unique()
      ->values()
      ->all();
  }

  public function hasClientId($clientId): bool
  {
    $cid = strtoupper((string) $clientId);
    return $cid !== '' && in_array($cid, $this->allowed_client_ids, true);
  }

  // Scope: hanya user yg share minimal 1 client dgn $viewer
  public function scopeVisibleTo($q, User $viewer)
  {
    if ($viewer->isSuperAdmin()) {
      return $q;
    }

    $allowed = $viewer->clients()
      ->pluck('clients.client_id')
      ->map(static function ($v) { return strtoupper((string) $v); })
      ->unique()
      ->values();

    if ($allowed->isEmpty()) {
      return $q->whereRaw('1=0');
    }

    return $q->whereHas('clients', function ($uq) use ($allowed) {
      $uq->whereIn('clients.client_id', $allowed->all());
    });
  }

  // Admin boleh kelola user dalam client yang sama (kecuali superadmin)
  public function scopeManageableBy($q, User $actor)
  {
    if ($actor->isSuperAdmin()) {
      return $q;
    }

    if ($actor->isAdmin()) {
      $allowed = $actor->clients()
        ->pluck('clients.client_id')
        ->map(static function ($v) { return strtoupper((string) $v); })
        ->unique()
        ->values();

      if ($allowed->isEmpty()) {
        return $q->whereRaw('1=0');
      }

      return $q->whereHas('clients', function ($uq) use ($allowed) {
        $uq->whereIn('clients.client_id', $allowed->all());
      })->where('role', '!=', 'superadmin');
    }

    return $q->whereRaw('1=0');
  }
}
