<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasRoles;

class User extends Authenticatable
{
  use Notifiable, HasRoles;

  protected $fillable = [
    'name', 'email', 'password',
    'role', 'client_id',
  ];

  protected $hidden = ['password', 'remember_token'];

  protected $casts = [
    'email_verified_at' => 'datetime',
  ];

  public function client()
  {
    return $this->belongsTo(Client::class);
  }
}
