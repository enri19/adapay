<?php

namespace App\Payments;

class Payment
{
  public static function provider(): PaymentProvider
  {
    $name = config('app.payment_provider', env('PAYMENT_PROVIDER', 'midtrans'));
    switch ($name) {
      case 'dana':
        return app(\App\Payments\Providers\DanaAdapter::class);
      case 'midtrans':
      default:
        return app(\App\Payments\Providers\MidtransAdapter::class);
    }
  }
}
