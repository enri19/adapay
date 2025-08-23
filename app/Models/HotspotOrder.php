<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HotspotOrder extends Model
{
  protected $fillable = ['order_id','client_id','hotspot_voucher_id','buyer_name','buyer_email','buyer_phone'];
  public function voucher() { return $this->belongsTo(HotspotVoucher::class, 'hotspot_voucher_id'); }
}
