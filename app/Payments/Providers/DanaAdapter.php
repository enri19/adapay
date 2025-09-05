<?php

namespace App\Payments\Providers;

use App\Models\Payment as PaymentModel;
use App\Payments\PaymentProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DanaAdapter implements PaymentProvider
{
  private array $cfg;

  public function __construct()
  {
    $this->cfg = config('dana'); // konsisten ambil dari config
  }

  public function createQris(string $orderId, int $amount, array $customer = [], array $opts = []): array
  {
    $body = $this->buildCreateOrderBody($orderId, $amount, $customer, $opts);
    $resp = $this->signedPost('/payment-gateway/v1.0/create-order', $body);

    $ok = ($resp['responseCode'] ?? null) === '2005400';
    return [
      'order_id'     => $orderId,
      'provider_ref' => $resp['referenceNo'] ?? null,
      'status'       => $ok ? PaymentModel::S_PENDING : PaymentModel::S_FAILED,
      'qr_string'    => null,
      'raw'          => $resp,
      'actions'      => [
        'web_checkout_url' => $resp['webRedirectUrl'] ?? null,
        'deeplink_url'     => $resp['deeplinkUrl'] ?? null,
        'qr_code_url'      => $resp['qrCodeUrl'] ?? null,
      ],
    ];
  }

  public function createEwallet(string $channel, string $orderId, int $amount, array $customer = [], array $opts = []): array
  {
    if (strtolower($channel) !== 'dana') {
      throw new \InvalidArgumentException('Unsupported e-wallet channel for DanaAdapter');
    }

    $body = $this->buildCreateOrderBody($orderId, $amount, $customer, $opts, [
      'payOptionDetails' => [[ 'payMethod' => 'DANA_BALANCE' ]],
    ]);

    $resp = $this->signedPost('/payment-gateway/v1.0/create-order', $body);

    $ok = ($resp['responseCode'] ?? null) === '2005400';
    return [
      'order_id'     => $orderId,
      'provider_ref' => $resp['referenceNo'] ?? null,
      'status'       => $ok ? PaymentModel::S_PENDING : PaymentModel::S_FAILED,
      'raw'          => $resp,
      'actions'      => [
        'web_checkout_url' => $resp['webRedirectUrl'] ?? null,
        'deeplink_url'     => $resp['deeplinkUrl'] ?? null,
        'qr_code_url'      => $resp['qrCodeUrl'] ?? null,
      ],
    ];
  }

  public function normalizeStatus(string $raw): string
  {
    $r = strtolower($raw);
    if (in_array($r, ['success','paid','capture','settlement'])) return PaymentModel::S_PAID;
    if (in_array($r, ['pending','process','in_progress']))       return PaymentModel::S_PENDING;
    if (in_array($r, ['refund','partial_refund']))               return PaymentModel::S_REFUNDED;
    if (in_array($r, ['expire','expired']))                      return PaymentModel::S_EXPIRED;
    if (in_array($r, ['cancel','failure','failed','deny']))      return PaymentModel::S_FAILED;
    return PaymentModel::S_PENDING;
  }

  public function handleWebhook(Request $request): array
  {
    $rawBody   = $request->getContent();
    $signature = (string) $request->header('X-SIGNATURE');
    $timestamp = (string) $request->header('X-TIMESTAMP');
    $method    = strtoupper($request->getMethod());
    $path      = $request->getPathInfo();

    $minified  = $this->minifyJson($rawBody);
    $hex       = hash('sha256', $minified);
    $toVerify  = "{$method}:{$path}:{$hex}:{$timestamp}";

    $pub = @file_get_contents($this->cfg['public_key']);
    if (!$pub) abort(500, 'Missing DANA public key');

    $ok = openssl_verify($toVerify, base64_decode($signature), $pub, OPENSSL_ALGO_SHA256) === 1;
    if (!$ok) abort(403, 'Invalid signature');

    $json      = json_decode($rawBody, true) ?: [];
    $respCode  = (string)($json['responseCode'] ?? '');
    $txnStat   = strtolower((string)($json['transactionStatus'] ?? ''));
    $rawStatus = $txnStat ?: ($respCode === '2005600' ? 'success' : 'pending');
    $status    = $this->normalizeStatus($rawStatus);

    $orderId   = (string)($json['originalPartnerReferenceNo'] ?? $json['partnerReferenceNo'] ?? '');
    $paidAt    = $status === PaymentModel::S_PAID ? now()->toIso8601String() : null;

    return [
      'order_id'     => $orderId,
      'provider_ref' => $json['originalReferenceNo'] ?? ($json['referenceNo'] ?? null),
      'status'       => $status,
      'paid_at'      => $paidAt,
      'raw'          => $json,
    ];
  }

  public function getStatus(string $orderId): array
  {
    $body = [
      'originalPartnerReferenceNo' => $orderId,
      'serviceCode' => 'PAYMENT_GATEWAY',
    ];
    return $this->signedPost('/payment-gateway/v1.0/query-payment', $body);
  }

  // =============== helpers ===============

  private function buildCreateOrderBody(string $orderId, int $amount, array $customer, array $opts, array $extra = []): array
  {
    $success = $opts['success_url'] ?? url('/payments/return?status=success&order_id='.$orderId);
    $failed  = $opts['failed_url']  ?? url('/payments/return?status=failed&order_id='.$orderId);

    $base = [
      'partnerReferenceNo' => $orderId,
      'amount' => [
        'value'    => (string) intval($amount),
        'currency' => 'IDR',
      ],
      'urlParams' => [
        'merchantReturnUrl' => [
          'successUrl' => $success,
          'failedUrl'  => $failed,
        ],
      ],
    ];

    if ($this->cfg['channel_id'] || $this->cfg['merchant_id']) {
      $base['additionalInfo']['merchantInfo'] = array_filter([
        'channelId'  => $this->cfg['channel_id'],
        'merchantId' => $this->cfg['merchant_id'],
      ]);
    }

    return array_replace_recursive($base, $extra);
  }

  private function signedPost(string $relativePath, array $body): array
  {
    $tsJkt  = Carbon::now('Asia/Jakarta')->format('Y-m-d\TH:i:sP');
    $json   = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $min    = $this->minifyJson($json);
    $hex    = hash('sha256', $min);
    $toSign = "POST:{$relativePath}:{$hex}:{$tsJkt}";

    $priv = @file_get_contents($this->cfg['private_key']);
    if (!$priv) abort(500, 'Missing DANA private key');

    $sigBin = '';
    openssl_sign($toSign, $sigBin, $priv, OPENSSL_ALGO_SHA256);
    $signature = base64_encode($sigBin);

    $headers = [
      'Content-Type' => 'application/json',
      'X-TIMESTAMP'  => $tsJkt,
      'X-CLIENT-KEY' => $this->cfg['partner_id'],
      'X-SIGNATURE'  => $signature,
      'X-ORIGIN'     => $this->cfg['origin'],
    ];

    $url = rtrim($this->cfg['base_url'], '/').$relativePath;
    $res = Http::withHeaders($headers)->post($url, $body);

    return $res->json() ?: ['http_code' => $res->status(), 'raw' => $res->body()];
  }

  private function minifyJson(string $json): string
  {
    $arr = json_decode($json, true);
    return json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }
}
