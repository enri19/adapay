<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksOwnership
{
  protected function ownedBy(User $user, $model)
  {
    // 1) Kalau model punya user_id
    if (isset($model->user_id) && (int) $model->user_id === (int) $user->id) {
      return true;
    }
    // 2) Kalau model punya client_id dan user juga punya client_id
    if (isset($model->client_id) && !is_null($user->client_id) && (int) $model->client_id === (int) $user->client_id) {
      return true;
    }
    return false;
  }
}
