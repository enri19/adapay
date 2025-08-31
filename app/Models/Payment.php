<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
  use HasFactory;

  protected $fillable = [
    'order_id','client_id','provider','provider_ref','amount','currency','status','qr_string','raw','paid_at'
  ];

  protected $casts = [
    'raw'     => 'array',
    'actions' => 'array',
    'amount'  => 'integer',
    'paid_at' => 'datetime',
  ];

  public const S_PENDING  = 'PENDING';
  public const S_PAID     = 'PAID';
  public const S_EXPIRED  = 'EXPIRED';
  public const S_FAILED   = 'FAILED';
  public const S_REFUNDED = 'REFUNDED';

  /**
   * Prevent status downgrade (e.g., keep PAID if incoming is PENDING)
   */
  public static function mergeStatus(?string $current, string $incoming): string
  {
    $rank = [
      self::S_FAILED => 0,
      self::S_PENDING => 1,
      self::S_EXPIRED => 2,
      self::S_PAID => 3,
      self::S_REFUNDED => 4, // treat refund as terminal highest
    ];
    $cur = $current && isset($rank[$current]) ? $rank[$current] : -1;
    $inc = $rank[$incoming] ?? -1;
    return $inc >= $cur ? $incoming : $current;
  }

  public function getAdminFeeAttribute(): int
  {
    $gross = (int) ($this->amount ?? 0);
    if ($gross <= 0) return 0;

    return \App\Support\AdminFee::forClient($this->client_id);
  }

  public function getNetForClientAttribute(): int
  {
    $gross = (int) ($this->amount ?? 0);
    return \App\Support\AdminFee::netForClient($gross, $this->client_id);
  }
}