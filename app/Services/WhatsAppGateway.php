<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppGateway
{
  public function send(string $phoneNumber, string $message, ?array $extraPayload = null): array
  {
    $cfg = [
      'uri'      => rtrim((string) config('wa.uri'), '/'),
      'token'    => (string) config('wa.token'),
      'user_id'  => config('wa.user_id'),
      'from'     => config('wa.from'),
      'timeout'  => (int) config('wa.timeout', 15),
      'insecure' => (bool) config('wa.insecure', false),
      // ubah sesuai gateway kamu; bisa /messages, /send-message, dll.
      'path'     => '/'.ltrim((string) config('wa.path', '/send-message'), '/'),
      'debug'    => (bool) config('wa.debug', false),
    ];

    // Pastikan ada scheme biar gak ke-parse sebagai relative
    if (! Str::startsWith($cfg['uri'], ['http://','https://'])) {
      $cfg['uri'] = 'https://'.$cfg['uri'];
    }

    $to = $this->normalizePhone($phoneNumber);

    $payload = array_merge(array_filter([
      'userId'  => $cfg['user_id'],
      'from'    => $cfg['from'],
      'to'      => $to,
      'message' => $message,
    ], static fn($v) => $v !== null && $v !== ''), $extraPayload ?? []);

    $req = Http::timeout($cfg['timeout'])
      ->retry(2, 200)                 // 2x retry dengan 200ms delay
      ->withToken($cfg['token'])
      ->acceptJson()
      ->asJson()
      ->baseUrl($cfg['uri']);

    if ($cfg['insecure']) {
      $req = $req->withoutVerifying(); // untuk self-signed sementara
    }

    try {
      $res  = $req->post($cfg['path'], $payload);
      $body = $this->decodeBody($res);
      $ok   = $this->isAccepted($res->status(), $body); // VALIDASI KETAT

      if ($cfg['debug']) {
        Log::info('wa.http', [
          'url'    => rtrim($cfg['uri'],'/').$cfg['path'],
          'status' => $res->status(),
          'ok'     => $ok,
          'body'   => $this->redact($body),
        ]);
      }

      return [
        'ok'     => $ok,
        'status' => $res->status(),
        'data'   => $body,
        'error'  => $ok ? null : ($body ?: $res->body()),
      ];
    } catch (\Throwable $e) {
      if ($cfg['debug']) {
        Log::error('wa.http.exception', ['err' => $e->getMessage()]);
      }
      return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $e->getMessage()];
    }
  }

  private function decodeBody($res)
  {
    try {
      $json = $res->json();
      if ($json !== null) return $json;
    } catch (\Throwable $e) { /* ignore */ }
    $txt = $res->body();
    $tmp = json_decode($txt, true);
    return $tmp ?? ['raw' => $txt];
  }

  // TANDA SUKSES: fleksibel untuk berbagai gateway (ok/success/sent/queued + id)
  private function isAccepted(int $status, $body): bool
  {
    if ($status < 200 || $status >= 300) return false;

    $candidates = [
      data_get($body, 'ok'),
      data_get($body, 'success'),
      data_get($body, 'status'),
      data_get($body, 'message'),
      data_get($body, 'data.status'),
      data_get($body, 'data.message'),
    ];

    foreach ($candidates as $sig) {
      if (is_bool($sig) && $sig === true) return true;
      if (is_string($sig) && in_array(strtolower($sig), ['ok','success','sent','queued','accepted','created'], true)) {
        return true;
      }
    }

    $id = data_get($body, 'id') ?? data_get($body,'data.id') ?? data_get($body,'message_id') ?? data_get($body,'data.message_id');
    return !empty($id);
  }

  private function normalizePhone(string $p): string
  {
    $p = preg_replace('/\D+/', '', $p ?? '');
    if ($p === '') return $p;
    if (Str::startsWith($p, '0')) $p = '62'.substr($p, 1);
    if (Str::startsWith($p, '8')) $p = '62'.$p;
    return ltrim($p, '+');
  }

  // Hindari bocorin token/id sensitif di log
  private function redact($body)
  {
    if (!is_array($body)) return $body;
    $copy = $body;
    foreach (['token','auth','authorization','access_token'] as $k) {
      if (isset($copy[$k])) $copy[$k] = '***';
    }
    return $copy;
  }
}
