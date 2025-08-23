<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotspotVoucher extends Model
{
  protected $guarded = [];
  protected $casts = [
    'price' => 'integer',
    'duration_minutes' => 'integer',
    'is_active' => 'boolean',
  ];

  public function scopeForClient($q, string $clientId)
  {
    $cid = strtoupper(preg_replace('/[^A-Z0-9]/','', $clientId)) ?: 'DEFAULT';
    return $q->where(function($w) use ($cid) {
      $w->where('client_id', $cid)
        ->orWhereNull('client_id')
        ->orWhere('client_id', 'DEFAULT');
    });
  }
}
