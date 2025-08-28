<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppGateway
{
  public function send(string $phoneNumber, string $message, ?array $extraPayload = null): array
  {
    $baseUri = rtrim(config('wa.uri'), '/');
    $token   = config('wa.token');
    $userId  = config('wa.user_id');
    $timeout = (int) config('wa.timeout', 15);

    $payload = array_merge([
      'userId'  => $userId,
      'to'      => $phoneNumber,
      'message' => $message,
    ], $extraPayload ?? []);

    try {
      $resp = Http::timeout($timeout)
        ->retry(2, 200)
        ->withToken($token)
        ->acceptJson()
        ->asJson()
        ->post("{$baseUri}/send-message", $payload);

      return [
        'ok'     => $resp->successful(),
        'status' => $resp->status(),
        'data'   => $resp->json(),
        'error'  => $resp->successful() ? null : ($resp->json() ?? $resp->body()),
      ];
    } catch (\Throwable $e) {
      return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $e->getMessage()];
    }
  }
}
