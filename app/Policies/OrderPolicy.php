<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;
use App\Policies\Concerns\ChecksOwnership;

class OrderPolicy
{
  use ChecksOwnership;

  public function viewAny(User $user)
  {
    return true;
  }

  public function view(User $user, Order $order)
  {
    return $user->isAdmin() || $this->ownedBy($user, $order);
  }

  public function create(User $user)
  {
    return true;
  }

  public function update(User $user, Order $order)
  {
    return $user->isAdmin() || $this->ownedBy($user, $order);
  }

  public function delete(User $user, Order $order)
  {
    return $user->isAdmin() || $this->ownedBy($user, $order);
  }
}
