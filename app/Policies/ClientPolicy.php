<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Client;
use App\Policies\Concerns\ChecksOwnership;

class ClientPolicy
{
  use ChecksOwnership;

  public function viewAny(User $user)
  {
    return $user->isAdmin();
  }

  public function view(User $user, Client $client)
  {
    return $user->isAdmin() || $this->ownedBy($user, $client);
  }

  public function create(User $user)
  {
    return $user->isAdmin();
  }

  public function update(User $user, Client $client)
  {
    return $user->isAdmin();
  }

  public function delete(User $user, Client $client)
  {
    return $user->isAdmin();
  }
}
