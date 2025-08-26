<?php

namespace App\Services;

use App\Models\HotspotOrder;
use App\Models\HotspotUser;
use App\Models\Payment;
use App\Models\Client;
use App\Services\Mikrotik\MikrotikClient;
use App\Support\OrderId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class HotspotProvisioner
{
  /** @var MikrotikClient */
  private $mt;

  public function __construct(MikrotikClient $mt)
  {
    $this->mt = $mt;
  }

  /**
   * Ambil client_id dari kolom order (jika ada) atau dari prefix order_id (ORD-<CLIENT>-...)
   */
  private function resolveClientId(string $orderId, ?HotspotOrder $order = null): string
  {
    if ($order && !empty($order->client_id)) {
      return strtoupper($order->client_id);
    }
    $cid = OrderId::client($orderId);
    return $cid ?: 'DEFAULT';
  }

  /**
   * Ambil konfigurasi router dari DB clients (aktif) atau fallback ke config/mikrotik.php
   * Return shape:
   *   ['host'=>..., 'port'=>8728, 'user'=>..., 'pass'=>..., 'profile'=>'default', 'enable_push'=>bool]
   */
  private function resolveRouter(string $clientId): ?array
  {
    $c = Client::where('client_id', strtoupper($clientId))
      ->where('is_active', true)
      ->first();

    if ($c) {
      return [
        'host'        => $c->router_host,
        'port'        => (int) ($c->router_port ?: 8728),
        'user'        => $c->router_user,
        'pass'        => $c->router_pass,
        'profile'     => $c->default_profile ?: 'default',
        'enable_push' => (bool) $c->enable_push,
      ];
    }

    // fallback ke config lama
    $cfg = config('mikrotik.clients.' . strtoupper($clientId))
      ?? config('mikrotik.clients.DEFAULT')
      ?? null;

    if ($cfg) {
      return [
        'host'        => $cfg['host'] ?? null,
        'port'        => (int) ($cfg['port'] ?? 8728),
        'user'        => $cfg['user'] ?? null,
        'pass'        => $cfg['pass'] ?? null,
        'profile'     => $cfg['profile'] ?? 'default',
        'enable_push' => (bool) (config('mikrotik.enable_push') ?? env('MIKROTIK_ENABLE_PUSH', false)),
      ];
    }

    return null;
  }

  /**
   * Buat akun hotspot (DB) untuk order PAID. Idempotent (kalau sudah ada, return existing).
   * @return HotspotUser|null
   */
  public function provision(string $orderId)
  {
    $order = HotspotOrder::where('order_id', $orderId)
      ->with('voucher')
      ->first();
    if (!$order) return null;

    // sudah ada? langsung pakai
    $existing = HotspotUser::where('order_id', $orderId)->first();
    if ($existing) return $existing;

    // hanya jika sudah PAID
    $payment = Payment::where('order_id', $orderId)->first();
    if (!$payment || $payment->status !== Payment::S_PAID) return null;

    // --- baca mode dari clients.auth_mode ---
    $client = \App\Models\Client::where('client_id', $order->client_id ?: 'DEFAULT')->first();
    $mode = $client && isset($client->auth_mode) ? strtolower((string)$client->auth_mode) : 'code';
    if (!in_array($mode, ['code','userpass'], true)) $mode = 'code';

    // --- generate kredensial (ALL UPPERCASE) ---
    if ($mode === 'userpass') {
      $username = 'HV-' . strtoupper(\Illuminate\Support\Str::random(6));
      $password = strtoupper(\Illuminate\Support\Str::random(8));
    } else {
      // mode "code": username == password == KODE (tanpa karakter rancu)
      $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpakan I,O,0,1
      $code = '';
      for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
      }
      $username = $code;
      $password = $code;
    }

    $profile = ($order->voucher && $order->voucher->profile)
      ? $order->voucher->profile
      : (($client && isset($client->default_profile) && $client->default_profile) ? $client->default_profile : 'default');

    return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $username, $password, $profile) {
      // idempotency guard bila ada racing
      $again = HotspotUser::where('order_id', $order->order_id)->lockForUpdate()->first();
      if ($again) return $again;

      return HotspotUser::create([
        'order_id'           => $order->order_id,
        'hotspot_voucher_id' => $order->hotspot_voucher_id,
        'client_id'          => $order->client_id,
        'username'           => strtoupper($username),
        'password'           => strtoupper($password),
        'profile'            => $profile,
        'duration_minutes'   => $order->voucher ? $order->voucher->duration_minutes : null,
      ]);
    });
  }

  /**
   * Dorong user ke Mikrotik yang sesuai client. Default: log-only.
   * Nyalakan push nyata dengan set enable_push=1 di Client (atau config fallback).
   */
  public function pushToMikrotik(HotspotUser $u): void
  {
      $order    = HotspotOrder::where('order_id', $u->order_id)->with('voucher')->first();
      $clientId = $this->resolveClientId($u->order_id, $order);
      $router   = $this->resolveRouter($clientId);

      $limitUptime = $u->duration_minutes . 'm';
      $voucherCode = $order && $order->voucher
          ? ($order->voucher->code ?? $order->voucher->name ?? '')
          : '';
      $comment = 'Voucher ' . $voucherCode . ' via order ' . $u->order_id . ' [' . $clientId . ']';

      Log::info('Hotspot user create (plan)', [
          'order_id'  => $u->order_id,
          'client_id' => $clientId,
          'router'    => $router ? ($router['host'] ?? 'n/a') : 'n/a',
          'username'  => $u->username,
          'profile'   => $u->profile,
          'limit'     => $limitUptime,
      ]);

      // Push dimatikan atau data router belum lengkap → jangan lanjut
      if (!$router || empty($router['enable_push'])) {
          Log::info('Client not provision (push disabled)');
          return;
      }
      if (empty($router['host']) || empty($router['user']) || empty($router['pass'])) {
          Log::warning('Router config incomplete', ['client_id' => $clientId, 'router' => $router]);
          return;
      }

      try {
          // Selalu mulai dari instance yang sudah di-inject
          $mtClient = $this->mt;

          // Prefer withConfig() kalau tersedia (return instance baru)
          if (is_object($mtClient) && method_exists($mtClient, 'withConfig')) {
              $maybe = $mtClient->withConfig([
                  'host' => $router['host'],
                  'port' => (int)($router['port'] ?? 8728),
                  'user' => $router['user'],
                  'pass' => $router['pass'],
              ]);
              if ($maybe) {
                  $mtClient = $maybe;
              }
          }
          // Kalau tidak ada withConfig() tapi ada connect(), panggil ke $this->mt lalu pakai object yang sama
          elseif (is_object($mtClient) && method_exists($mtClient, 'connect')) {
              $this->mt->connect(
                  $router['host'],
                  (int)($router['port'] ?? 8728),
                  $router['user'],
                  $router['pass']
              );
              $mtClient = $this->mt; // pastikan variabel terisi
          }
          // Terakhir, coba resolve via container dengan makeWith(config)
          elseif (method_exists(app(), 'makeWith')) {
              try {
                  $mtClient = app()->makeWith(\App\Services\Mikrotik\MikrotikClient::class, ['config' => $router]);
              } catch (\Throwable $e) {
                  // tetap pakai $this->mt
              }
          }

          Log::info('Mikrotik client class', ['class' => is_object($mtClient) ? get_class($mtClient) : gettype($mtClient)]);

          // (Opsional) kalau driver punya ping/test, boleh panggil di sini
          if (is_object($mtClient) && method_exists($mtClient, 'ping')) {
              try { $mtClient->ping(); } catch (\Throwable $e) { /* abaikan */ }
          }

          // Buat / update user
          $mtClient->createHotspotUser($u->username, $u->password, $u->profile, $comment, $limitUptime);

          Log::info('Mikrotik push success', ['order_id' => $u->order_id, 'client_id' => $clientId]);
      } catch (\Throwable $e) {
          Log::error('Mikrotik push failed', [
              'order_id'  => $u->order_id,
              'client_id' => $clientId,
              'err'       => $e->getMessage(),
          ]);
      }
  }
}
