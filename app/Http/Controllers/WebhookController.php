<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
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
            // Jangan paksa ke PENDING—biarkan apa adanya untuk di-handle mergeStatus
            return strtoupper($tx);
        }
    }

    // aman ubah mixed → array
    private function arr($x): array {
        if (is_array($x)) return $x;
        if (is_string($x)) { $d = json_decode($x, true); if (json_last_error()===JSON_ERROR_NONE) return $d ?: []; }
        return json_decode(json_encode($x), true) ?: [];
    }

    public function handle(Request $r, HotspotProvisioner $prov)
    {
        try {
            // payload: support JSON & form-encoded
            $raw = $r->getContent();
            $payload = json_decode($raw, true);
            if (!is_array($payload)) $payload = $this->arr($r->all());

            $orderId = isset($payload['order_id']) ? (string)$payload['order_id'] : null;
            if (!$orderId) {
                Log::warning('Webhook tanpa order_id', ['ip'=>$r->ip(), 'ct'=>$r->header('Content-Type')]);
                return response()->json(['ok' => true]); // jangan 4xx ke Midtrans
            }

            // Validasi signature Midtrans (optional tapi disarankan)
            $statusCode   = (string)($payload['status_code']   ?? '');
            $grossAmount  = (string)($payload['gross_amount']  ?? '');
            $signatureKey = (string)($payload['signature_key'] ?? '');
            $serverKey    = (string) Config::get('services.midtrans.server_key', '');

            $expectedSig = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
            $signatureValid = ($serverKey !== '' && $signatureKey !== '' && hash_equals($expectedSig, $signatureKey));

            if (!$signatureValid) {
                // Tetap balas 200 agar Midtrans tidak retry; jangan update order
                Log::warning('Midtrans signature INVALID', ['order_id'=>$orderId]);
                return response()->json(['ok' => true]);
            }

            $incomingAppStatus = $this->normalizeIncoming((string)($payload['transaction_status'] ?? ''));
            $providerRef       = isset($payload['transaction_id']) ? (string)$payload['transaction_id'] : null;

            DB::transaction(function () use ($orderId, $payload, $incomingAppStatus, $providerRef) {
                // lock row agar konsisten
                $p = Payment::where('order_id', $orderId)->lockForUpdate()->first();

                // siapkan nilai existing dengan aman saat $p = null
                $prevRaw        = $p ? $this->arr($p->raw)        : [];
                $prevActions    = $p ? $this->arr($p->actions)    : [];
                $prevStatus     = $p ? (string)$p->status         : null;
                $prevProviderRef= $p ? (string)$p->provider_ref   : null;
                $prevPaidAt     = $p ? $p->paid_at                : null;

                // raw baru + pertahankan actions lama jika payload tak bawa
                $newRaw = $this->arr($payload);
                if (empty($newRaw['actions']) && !empty($prevActions)) {
                    $newRaw['actions'] = $prevActions;
                }

                // merge raw lama + baru (baru overwrite)
                $mergedRaw = array_replace_recursive($prevRaw, $newRaw);

                // tentukan status final
                if (method_exists(Payment::class, 'mergeStatus')) {
                    $finalStatus = Payment::mergeStatus($prevStatus, $incomingAppStatus);
                } else {
                    $finalStatus = $incomingAppStatus;
                }

                $clientId = OrderId::client($orderId) ?: 'DEFAULT';

                Payment::updateOrCreate(
                    ['order_id' => $orderId],
                    [
                        'client_id'    => $clientId,
                        'provider'     => 'midtrans',
                        'provider_ref' => $providerRef ?: $prevProviderRef,
                        'status'       => $finalStatus,
                        'raw'          => $mergedRaw,
                        'actions'      => !empty($newRaw['actions']) ? $newRaw['actions'] : ($p ? $p->actions : null),
                        'paid_at'      => ($finalStatus === 'PAID')
                                          ? ($prevPaidAt ?: now())
                                          : $prevPaidAt,
                    ]
                );
            });

            // Post-commit: provision kalau sudah PAID (tanpa bikin gagal webhook)
            $p = Payment::where('order_id', $orderId)->first();
            if ($p && $p->status === 'PAID') {
                try {
                    $u = $prov->provision($orderId);
                    if ($u) $prov->queuePushToMikrotik($u);
                } catch (\Throwable $e) {
                    Log::error('Provision after webhook gagal', ['order_id'=>$orderId, 'err'=>$e->getMessage()]);
                }
            }

            // WAJIB 200 agar Midtrans tidak retry
            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            // Jangan kirim 500 ke Midtrans, cukup log error internal
            Log::error('midtrans.webhook.exception', ['msg'=>$e->getMessage(), 'line'=>$e->getLine()]);
            return response()->json(['ok' => true]);
        }
    }
}
