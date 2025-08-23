<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
  protected $guarded = [];
  protected $casts = [
    'enable_push' => 'boolean',
    'is_active'   => 'boolean',
    'router_port' => 'integer',
  ];
}
