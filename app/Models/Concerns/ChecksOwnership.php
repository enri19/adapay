<?php
// app/Policies/Concerns/ChecksOwnership.php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksOwnership
{
  protected function ownedBy(User $user, $model): bool
  {
    // a) Kalau model punya user_id langsung
    if (isset($model->user_id) && (int) $model->user_id === (int) $user->id) {
      return true;
    }

    // b) Kalau model punya client_id (varchar) dan user punya akses ke client tsb
    if (isset($model->client_id)) {
      return $user->hasClientId((string) $model->client_id);
    }

    return false;
  }
}
