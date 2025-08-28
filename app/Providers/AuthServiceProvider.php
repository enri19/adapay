<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// SESUAIKAN model di bawah dengan milikmu
use App\Models\Client;
use App\Models\Payment;
use App\Models\Order;

use App\Policies\ClientPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\OrderPolicy;

class AuthServiceProvider extends ServiceProvider
{
  protected $policies = [
    Client::class => ClientPolicy::class,
    Payment::class => PaymentPolicy::class,
    Order::class => OrderPolicy::class,
  ];

  public function boot()
  {
    $this->registerPolicies();

    // Admin boleh semua tanpa cek lebih lanjut
    Gate::before(function ($user, $ability) {
      return $user && $user->isAdmin() ? true : null;
    });

    // Gate sederhana (opsional) untuk routing
    Gate::define('is-admin', function ($user) {
      return $user->isAdmin();
    });
  }
}
