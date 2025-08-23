<?php

namespace App\Payments;

use Illuminate\Http\Request;

interface PaymentProvider
{
  /** @return array{order_id:string, provider_ref:?string, status:string, qr_string:?string, raw:array} */
  public function createQris(string $orderId, int $amount, array $customer = [], array $opts = []): array;

  /** @return array{order_id:string, provider_ref:?string, status:string, raw:array, actions?:array} */
  public function createEwallet(string $channel, string $orderId, int $amount, array $customer = [], array $opts = []): array;

  public function normalizeStatus(string $raw): string;

  /** @return array{order_id:string, provider_ref:?string, status:string, paid_at:?string, raw:array} */
  public function handleWebhook(Request $request): array;

  /** @return array Raw status payload from provider */
  public function getStatus(string $orderId): array;
}
