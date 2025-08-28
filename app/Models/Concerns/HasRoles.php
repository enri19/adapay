<?php

namespace App\Models\Concerns;

trait HasRoles
{
  public function isAdmin()
  {
    return (string) $this->role === 'admin';
  }

  public function isUser()
  {
    return (string) $this->role === 'user';
  }
}
