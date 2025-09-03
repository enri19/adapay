<?php

namespace App\Models\Concerns;

trait HasRoles
{
  public function isSuperAdmin(): bool
  {
    return (string) $this->role === 'superadmin';
  }

  public function isAdmin(): bool
  {
    return (string) $this->role === 'admin';
  }

  public function isUser(): bool
  {
    return (string) $this->role === 'user';
  }
}
