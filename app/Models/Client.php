<?php
// app/Models/Client.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
  protected $guarded = [];

  protected $casts = [
    'enable_push'    => 'boolean',
    'is_active'      => 'boolean',
    'router_port'    => 'integer',
    'hotspot_portal' => 'string',
    'admin_fee_flat' => 'integer',
  ];

  public function users()
  {
    return $this->belongsToMany(
      User::class,
      'user_client',
      'client_id', // pivot -> clients.client_id
      'user_id',   // pivot -> users.id
      'client_id', // local key clients
      'id'         // related key users
    );
  }

  public function setHotspotPortalAttribute($value): void
  {
    $v = $value === null ? null : trim((string) $value);
    $this->attributes['hotspot_portal'] = ($v === '') ? null : $v;
  }

  public function scopeVisibleTo($q, User $viewer)
  {
    if ($viewer->isSuperAdmin()) {
      return $q;
    }

    return $q->whereHas('users', function ($uq) use ($viewer) {
      $uq->where('users.id', $viewer->id);
    });
  }

  public function getHotspotPortalEffectiveAttribute()
  {
    return $this->hotspot_portal ?: config('hotspot.portal_default');
  }

  public function getHotspotPortalHostAttribute()
  {
    $url = $this->hotspot_portal ?: config('hotspot.portal_default');
    return $url ? parse_url($url, PHP_URL_HOST) : null;
  }
}
