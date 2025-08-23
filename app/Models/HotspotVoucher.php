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

  public static function listForPortal(string $clientId)
  {
    $base = static::query()->where('is_active', true);
    $has = (clone $base)->where('client_id', $clientId)->exists();

    return $has
      ? (clone $base)->where('client_id', $clientId)->orderBy('price')->get()
      : (clone $base)->where(function($w) use($clientId){
          $w->where('client_id', $clientId)
            ->orWhere('client_id', 'DEFAULT')
            ->orWhereNull('client_id');
        })->orderBy('price')->get();
  }
}
