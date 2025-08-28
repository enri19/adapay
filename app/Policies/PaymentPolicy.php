<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;
use App\Policies\Concerns\ChecksOwnership;

class PaymentPolicy
{
  use ChecksOwnership;

  public function viewAny(User $user)
  {
    return true; // user bisa lihat list, nanti filter di controller (lihat bagian 5)
  }

  public function view(User $user, Payment $payment)
  {
    return $user->isAdmin() || $this->ownedBy($user, $payment);
  }

  public function create(User $user)
  {
    return true;
  }

  public function update(User $user, Payment $payment)
  {
    return $user->isAdmin() || $this->ownedBy($user, $payment);
  }

  public function delete(User $user, Payment $payment)
  {
    return $user->isAdmin() || $this->ownedBy($user, $payment);
  }
}
