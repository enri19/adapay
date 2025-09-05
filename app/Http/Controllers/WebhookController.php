<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Services\HotspotProvisioner;
use App\Support\OrderId;

class WebhookController extends Controller
{
  private function normalizeIncoming(string $tx): string
  {
    switch (strtolower($tx)) {
      case 'capture':        // cc paid (fraud status accept)
      case 'settlement':     // semua metode settle
        return 'PAID';
      case 'pending':
        return 'PENDING';
      case 'expire':
        return 'EXPIRED';
      case 'cancel':
      case 'deny':           // treat as cancelled on our side
        return 'CANCELLED';
      case 'refund':
        return 'REFUND';
      case 'partial_refund':
        return 'PARTIAL_REFUND';
      case 'challenge':
        return 'CHALLENGE';
      default:
        return strtoupper($tx);
    }
  }

  private function arr($x): array
  {
    if (is_array($x)) return $x;
    if (is_string($x)) {
      $d = json_decode($x, true);
      if (json_last_error() === JSON_ERROR_NONE) return $d ?: [];
    }
    return json_decode(json_encode($x), true) ?: [];
  }

  // Handle Midtrans Webhook
  public function handleMidtransWebhook(Request $r)
  {
    try {
      $raw = $r->getContent();
      $payload = json_decode($raw, true);
      if (!is_array($payload)) $payload = $this->arr($r->all());

      $orderId = isset($payload['order_id']) ? (string)$payload['order_id'] : null;
      if (!$orderId) {
        Log::warning('Webhook tanpa order_id', ['ip' => $r->ip(), 'ct' => $r->header('Content-Type')]);
        return response()->json(['ok' => true]); // jangan 4xx ke Midtrans
      }

      // Validasi signature Midtrans (optional tapi disarankan)
      $statusCode   = (string)($payload['status_code'] ?? '');
      $grossAmount  = (string)($payload['gross_amount'] ?? '');
      $serverKey = (string)(config('midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', ''));
      $signatureValid = $this->validateSignature($orderId, $statusCode, $grossAmount, $serverKey, $payload);

      if (!$signatureValid) {
        logger()->warning('Midtrans signature INVALID', ['order_id' => $orderId]);
        return response()->json(['ok' => true]);
      }

      $this->processPayment($payload, $orderId, 'midtrans');

      return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
      Log::error('midtrans.webhook.exception', ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
      return response()->json(['ok' => true]);
    }
  }

  // Handle DANA Webhook
  public function handleDanaWebhook(Request $request)
  {
    try {
      $payload = $request->all();
      $orderId = $payload['order_id'] ?? '';
      $signature = $payload['signature_key'] ?? '';

      if (empty($orderId) || empty($signature)) {
        Log::warning('DANA Webhook: Missing order_id or signature');
        return response()->json(['ok' => true]);
      }

      // Verifikasi signature DANA
      $signatureValid = $this->verifyDanaSignature($payload, $orderId);
      if (!$signatureValid) {
        Log::warning('DANA Webhook: Invalid signature');
        return response()->json(['ok' => true]);
      }

      // Handle valid webhook payload
      $status = strtolower($payload['transaction_status'] ?? 'pending');
      $providerRef = $payload['transaction_id'] ?? null;

      $this->processPayment($payload, $orderId, 'dana');

      return response()->json(['ok' => true]);

    } catch (\Throwable $e) {
      Log::error('DANA Webhook Error', ['message' => $e->getMessage()]);
      return response()->json(['ok' => true]);
    }
  }

  private function verifyDanaSignature($payload, $orderId)
  {
    $privateKey = file_get_contents(config('dana.private_key_path'));
    $publicKey  = file_get_contents(config('dana.public_key_path'));

    // Signature validation logic
    // Extract signature from payload and compare with expected signature using your private/public key
    // (This is just a simplified example, actual implementation depends on DANA's requirements)

    $expectedSignature = hash_hmac('sha256', $orderId . $payload['gross_amount'], $privateKey);

    return hash_equals($expectedSignature, $payload['signature_key']);
  }

  private function normalizeStatus($raw)
  {
    switch ($raw) {
      case 'settlement':
      case 'capture':
      case 'success':
          return Payment::S_PAID;
      case 'expire':
          return Payment::S_EXPIRED;
      case 'cancel':
      case 'deny':
          return Payment::S_FAILED;
      case 'refund':
          return Payment::S_REFUNDED;
      default:
          return Payment::S_PENDING;
    }
  }

  private function processPayment(array $payload, string $orderId, string $provider)
  {
    $status = $this->normalizeIncoming((string)($payload['transaction_status'] ?? ''));
    $providerRef = isset($payload['transaction_id']) ? (string)$payload['transaction_id'] : null;

    DB::transaction(function () use ($orderId, $payload, $status, $providerRef, $provider) {
      // lock row agar konsisten
      $p = Payment::where('order_id', $orderId)->lockForUpdate()->first();

      // Prepare previous values
      $prevRaw = $p ? $this->arr($p->raw) : [];
      $prevStatus = $p ? (string)$p->status : null;
      $prevProviderRef = $p ? (string)$p->provider_ref : null;
      $prevPaidAt = $p ? $p->paid_at : null;

      // Merge new payload with previous values
      $newRaw = $this->arr($payload);
      $mergedRaw = array_replace_recursive($prevRaw, $newRaw);

      // Determine final status
      $finalStatus = $status;

      // Update or create payment record
      Payment::updateOrCreate(
        ['order_id' => $orderId],
        [
          'provider'     => $provider,
          'provider_ref' => $providerRef ?: $prevProviderRef,
          'status'       => $finalStatus,
          'raw'          => $mergedRaw,
          'paid_at'      => ($finalStatus === 'PAID') ? ($prevPaidAt ?: now()) : $prevPaidAt,
        ]
      );

      $becamePaid = ($prevStatus !== 'PAID' && $finalStatus === 'PAID');

      if (!empty($becamePaid)) {
        // Satu-satunya trigger: delegasikan ke satu job orkestra
        \App\Jobs\PaymentBecamePaid::dispatch($orderId)->onQueue('router');
      }

      // WAJIB 200 agar Midtrans tidak retry
      return response()->json(['ok' => true]);
    });
  }

  private function validateSignature(string $orderId, string $statusCode, string $grossAmount, string $serverKey, array $payload): bool
  {
    $signatureKey = (string)($payload['signature_key'] ?? '');
    $expectedSig = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    return hash_equals($expectedSig, $signatureKey);
  }
}
