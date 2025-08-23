<?php

namespace App\Payments\Providers;

use App\Models\Payment as PaymentModel;
use App\Payments\PaymentProvider;
use Illuminate\Http\Request;

class MidtransAdapter implements PaymentProvider
{
  public function __construct()
  {
    \Midtrans\Config::$serverKey    = config('midtrans.server_key');
    \Midtrans\Config::$isProduction = (bool) config('midtrans.is_production');
    \Midtrans\Config::$is3ds        = true;       // opsional (kartu, bukan QR)
    \Midtrans\Config::$isSanitized  = true;       // <- ini yang benar
  }

  public function createQris(string $orderId, int $amount, array $customer = [], array $opts = []): array
  {
    $params = [
      'payment_type' => 'qris',
      'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => $amount,
      ],
      'customer_details' => array_filter([
        'first_name' => $customer['name'] ?? null,
        'email'      => $customer['email'] ?? null,
        'phone'      => $customer['phone'] ?? null,
      ]),
    ];

    if (!empty($opts['expiry_minutes'])) {
      $params['custom_expiry'] = [
        'expiry_duration' => (int) $opts['expiry_minutes'],
        'unit' => 'minute',
      ];
    }

    $resp = \Midtrans\CoreApi::charge($params);
    $respArr = $this->toArray($resp);

    $rawStatus = strtolower($respArr['transaction_status'] ?? 'pending');
    $status = $this->normalizeStatus($rawStatus);

    return [
      'order_id'     => $orderId,
      'provider_ref' => $respArr['transaction_id'] ?? null,
      'status'       => $status,
      'qr_string'    => $respArr['qr_string'] ?? null,
      'raw'          => $respArr,
    ];
  }

  public function normalizeStatus(string $raw): string
  {
      switch (strtolower($raw)) {
          case 'settlement':
          case 'capture':
          case 'success':
              return PaymentModel::S_PAID;
          case 'expire':
          case 'expired':
              return PaymentModel::S_EXPIRED;
          case 'deny':
          case 'cancel':
          case 'failure':
              return PaymentModel::S_FAILED;
          case 'refund':
          case 'partial_refund':
              return PaymentModel::S_REFUNDED;
          default:
              return PaymentModel::S_PENDING;
      }
  }

  public function handleWebhook(Request $request): array
  {
      $payload = $request->all();

      $orderId    = (string) ($payload['order_id'] ?? '');
      $statusCode = (string) ($payload['status_code'] ?? '');
      $gross      = (string) ($payload['gross_amount'] ?? '');
      $sigRecv    = (string) ($payload['signature_key'] ?? '');

      if (!$orderId || !$statusCode || !$gross || !$sigRecv) {
          abort(400, 'Invalid payload');
      }

      $expected = hash('sha512', $orderId.$statusCode.$gross.config('midtrans.server_key'));
      if (!hash_equals($expected, $sigRecv)) {
          abort(403, 'Invalid signature');
      }

      $rawStatus = strtolower($payload['transaction_status'] ?? 'pending');
      $status    = $this->normalizeStatus($rawStatus);
      $paidAt    = in_array($rawStatus, ['capture','settlement','success'], true) ? now() : null;

      return [
          'order_id'     => $orderId,
          'provider_ref' => $payload['transaction_id'] ?? null,
          'status'       => $status,
          'paid_at'      => $paidAt ? $paidAt->toIso8601String() : null,
          'raw'          => (array) $payload,
      ];
  }

  public function getStatus(string $orderId): array
  {
    $resp = \Midtrans\Transaction::status($orderId);
    return $this->toArray($resp);
  }

  public function createEwallet(string $channel, string $orderId, int $amount, array $customer = [], array $opts = []): array
  {
    $channel = strtolower($channel);
    if (!in_array($channel, ['gopay','shopeepay'], true)) {
      throw new \InvalidArgumentException('Unsupported e-wallet channel');
    }

    $params = [
      'payment_type' => $channel,
      'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => $amount,
      ],
      'customer_details' => array_filter([
        'first_name' => $customer['name'] ?? null,
        'email'      => $customer['email'] ?? null,
        'phone'      => $customer['phone'] ?? null,
      ]),
    ];

    $base = $opts['callback_url'] ?? url('/payments/return');
    $callback = strpos($base, 'order_id=') === false ? $base.'?order_id='.$orderId : $base;
    if ($channel === 'gopay') {
      $params['gopay'] = ['enable_callback' => true, 'callback_url' => $callback];
    } else {
      $params['shopeepay'] = ['callback_url' => $callback];
    }

    try {
      $respArr = $this->chargeWithRetry($params);
    } catch (\Throwable $e) {
      $msg = $e->getMessage();
      if (strpos($msg, '"status_code":"402"') !== false) {
        throw new \RuntimeException('CHANNEL_INACTIVE: '.$msg, 0, $e);
      }
      if (stripos($msg, 'pop id') !== false || stripos($msg, 'PoP') !== false) {
        throw new \RuntimeException('POP_REQUIRED: '.$msg, 0, $e);
      }
      throw $e; // termasuk UPSTREAM_TEMPORARY (500)
    }

    $rawStatus = strtolower($respArr['transaction_status'] ?? 'pending');
    $status = $this->normalizeStatus($rawStatus);

    $deeplink = null; $qrUrl = null; $webCheckout = null;
    foreach ((array)($respArr['actions'] ?? []) as $a) {
      $a = $this->toArray($a);
      $name = strtolower($a['name'] ?? '');
      $url  = $a['url'] ?? null;
      if (in_array($name, ['deeplink-redirect','mobile_deeplink_checkout_url'], true)) $deeplink = $url;
      if (in_array($name, ['generate-qr-code','generate-qr-code-v2','qr_checkout'], true)) $qrUrl = $url;
      if (in_array($name, ['desktop_web_checkout_url','web_checkout'], true)) $webCheckout = $url;
    }

    return [
      'order_id'     => $orderId,
      'provider_ref' => $respArr['transaction_id'] ?? null,
      'status'       => $status,
      'raw'          => $respArr,
      'actions'      => [
        'deeplink_url'     => $deeplink,
        'qr_code_url'      => $qrUrl,
        'web_checkout_url' => $webCheckout,
      ],
    ];
  }

  private function toArray($data): array {
    return is_array($data) ? $data : json_decode(json_encode($data), true);
  }

  // Tambah helper charge dengan retry & 409 handling
  private function chargeWithRetry(array $params, int $maxAttempts = 3): array {
    $attempt = 0;
    $delays = [200000, 500000, 1200000]; // usleep: 200ms, 500ms, 1.2s
    $orderId = $params['transaction_details']['order_id'] ?? null;

    while (true) {
      try {
        $resp = \Midtrans\CoreApi::charge($params);
        return $this->toArray($resp);
      } catch (\Throwable $e) {
        $msg = $e->getMessage();

        // 409: order_id already used → ambil status saja
        if (strpos($msg, '"status_code":"409"') !== false && $orderId) {
          $status = \Midtrans\Transaction::status($orderId);
          return $this->toArray($status);
        }

        // 500: upstream sementara → retry beberapa kali, lalu fail dengan kode khusus
        if (strpos($msg, '"status_code":"500"') !== false || stripos($msg, 'recover') !== false) {
          if ($attempt < $maxAttempts - 1) {
            usleep($delays[$attempt] ?? 500000);
            $attempt++;
            continue;
          }
          throw new \RuntimeException('UPSTREAM_TEMPORARY: '.$msg, 0, $e);
        }

        // 402/PoP biarkan ditangani oleh caller yang sudah kita buat sebelumnya
        throw $e;
      }
    }
  }
}
