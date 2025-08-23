<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HotspotUser extends Model
{
  protected $fillable = ['order_id','hotspot_voucher_id','username','password','profile','duration_minutes'];
  public function voucher() { return $this->belongsTo(HotspotVoucher::class, 'hotspot_voucher_id'); }
}
