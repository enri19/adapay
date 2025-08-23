<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HotspotVoucher extends Model
{
  protected $fillable = ['code','name','duration_minutes','price','profile'];
}
