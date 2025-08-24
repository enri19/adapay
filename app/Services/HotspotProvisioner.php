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

    $existing = HotspotUser::where('order_id', $orderId)->first();
    if ($existing) return $existing;

    $payment = Payment::where('order_id', $orderId)->first();
    if (!$payment || $payment->status !== Payment::S_PAID) return null;

    $username = 'hv-' . strtolower(Str::random(6));
    $password = strtoupper(Str::random(8));
    $profile  = $order->voucher->profile ?? 'default';

    return DB::transaction(function () use ($order, $username, $password, $profile) {
      // idempotency guard bila ada racing: cek lagi di dalam TX
      $again = HotspotUser::where('order_id', $order->order_id)->lockForUpdate()->first();
      if ($again) return $again;

      return HotspotUser::create([
        'order_id'           => $order->order_id,
        'hotspot_voucher_id' => $order->hotspot_voucher_id,
        'username'           => $username,
        'password'           => $password,
        'profile'            => $profile,
        'duration_minutes'   => $order->voucher->duration_minutes,
      ]);
    });
  }

  /**
   * Dorong user ke Mikrotik yang sesuai client. Default: log-only.
   * Nyalakan push nyata dengan set enable_push=1 di Client (atau config fallback).
   */
  public function pushToMikrotik(HotspotUser $u): void
  {
    $order = HotspotOrder::where('order_id', $u->order_id)->with('voucher')->first();
    $clientId = $this->resolveClientId($u->order_id, $order);
    $router = $this->resolveRouter($clientId);

    $limitUptime = $u->duration_minutes . 'm';
    $voucherCode = $order && $order->voucher
      ? ($order->voucher->code ?? $order->voucher->name ?? '')
      : '';
    $comment = 'Voucher ' . $voucherCode . ' via order ' . $u->order_id . ' [' . $clientId . ']';

    // Selalu log rencana push
    Log::info('Hotspot user create (plan)', [
      'order_id'  => $u->order_id,
      'client_id' => $clientId,
      'router'    => $router ? ($router['host'] ?? 'n/a') : 'n/a',
      'username'  => $u->username,
      'profile'   => $u->profile,
      'limit'     => $limitUptime,
    ]);

    Log::info('Mikrotik client class', ['class' => get_class($client)]);

    // Jika push belum diaktifkan, selesai di sini (log-only)
    if (!$router || empty($router['enable_push'])) {
      return;
    }

    try {
      // Siapkan client Mikrotik dinamis
      $client = $this->mt;

      if (method_exists($this->mt, 'withConfig')) {
        $maybe = $this->mt->withConfig([
          'host' => $router['host'] ?? null,
          'port' => $router['port'] ?? 8728,
          'user' => $router['user'] ?? null,
          'pass' => $router['pass'] ?? null,
        ]);
        if ($maybe) $client = $maybe;
      } elseif (method_exists($this->mt, 'connect')) {
        $client->connect(
          $router['host'] ?? null,
          (int) ($router['port'] ?? 8728),
          $router['user'] ?? null,
          $router['pass'] ?? null
        );
      } elseif (method_exists(app(), 'makeWith')) {
        try {
          $client = app()->makeWith(MikrotikClient::class, ['config' => $router]);
        } catch (\Throwable $e) {
          // fallback tetap pakai $this->mt
        }
      }

      $client->createHotspotUser($u->username, $u->password, $u->profile, $comment, $limitUptime);
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
